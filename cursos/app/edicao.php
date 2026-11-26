<?php
declare(strict_types=1);

/** Quiz guardado -> texto de autoria ("pergunta / * certa / - errada"). */
function quiz_para_texto(array $quiz): string
{
    $blocos = [];
    foreach ($quiz as $p) {
        $l = [$p['pergunta']];
        foreach ($p['opcoes'] as $i => $o) $l[] = ($i === (int) $p['correta'] ? '* ' : '- ') . $o;
        $blocos[] = implode("\n", $l);
    }
    return implode("\n\n", $blocos);
}

function aula_com_curso(string $id): ?array
{
    return um('SELECT a.*, m.curso_id FROM aulas a JOIN modulos m ON m.id = a.modulo_id WHERE a.id = ?', [$id]);
}

/** Renumera a ordem (1, 2, 3...) depois de mover ou excluir. */
function renumerar(string $tabela, string $pai, string $paiId): void
{
    $ids = array_column(todos("SELECT id FROM $tabela WHERE $pai = ? ORDER BY ordem, id", [$paiId]), 'id');
    foreach ($ids as $i => $id) q("UPDATE $tabela SET ordem = ? WHERE id = ?", [$i + 1, $id]);
}

/** Troca de posição com o vizinho de cima ou de baixo. */
function mover(string $tabela, string $pai, string $id, string $direcao): ?string
{
    $item = um("SELECT id, $pai AS pai, ordem FROM $tabela WHERE id = ?", [$id]);
    if (!$item) return null;
    renumerar($tabela, $pai, $item['pai']);
    $item = um("SELECT id, $pai AS pai, ordem FROM $tabela WHERE id = ?", [$id]);
    $alvo = (int) $item['ordem'] + ($direcao === 'cima' ? -1 : 1);
    $viz = um("SELECT id FROM $tabela WHERE $pai = ? AND ordem = ?", [$item['pai'], $alvo]);
    if ($viz) {
        q("UPDATE $tabela SET ordem = ? WHERE id = ?", [$item['ordem'], $viz['id']]);
        q("UPDATE $tabela SET ordem = ? WHERE id = ?", [$alvo, $id]);
    }
    return $item['pai'];
}

// ---------- Ações ----------

function acao_salvar_aula(): void
{
    $admin = exigir_admin();
    $a = aula_com_curso(post('aula_id'));
    if (!$a) ir('admin');
    $f = fn(string $m) => voltar('erro', $m, 'editar_aula', ['id' => $a['id']]);
    $ex = um('SELECT * FROM exercicios WHERE aula_id = ?', [$a['id']]);
    $temEntregas = $ex && valor('SELECT 1 FROM entregas WHERE exercicio_id = ?', [$ex['id']]);

    $titulo = post('titulo');
    $tipo = post('tipo');
    if ($titulo === '' || !isset(ROTULO_TIPO_AULA[$tipo])) $f('Informe título e tipo da aula.');
    if ($temEntregas && $tipo !== 'EXERCICIO') $f('Esta aula já tem entregas de alunos: ela precisa continuar sendo um exercício.');
    $video = post('video_url') ?: null;
    $sim = post('simulador_url') ?: null;
    if ($tipo === 'VIDEO' && !($video && embed_video($video))) $f('Aula em vídeo precisa de um link do YouTube ou do Vimeo.');
    if ($video && !embed_video($video)) $f('Link de vídeo não reconhecido (use YouTube ou Vimeo).');
    if ($tipo === 'SIMULADOR' && !$sim) $f('Aula de simulador precisa do link do projeto.');
    if ($sim && !embed_simulador($sim)) $f('Link de simulador não reconhecido. Use tinkercad.com/things/... ou wokwi.com/projects/...');
    $quiz = $tipo === 'QUIZ' ? parse_quiz((string) ($_POST['quiz'] ?? '')) : [];
    if ($tipo === 'QUIZ' && !$quiz) $f('Quiz sem perguntas válidas. Marque a opção correta com * e as demais com -.');
    $rubrica = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string) ($_POST['rubrica'] ?? '')))), 'strlen'));
    $enunciado = trim((string) ($_POST['enunciado'] ?? ''));
    if ($tipo === 'EXERCICIO' && ($enunciado === '' || !$rubrica)) $f('Exercício precisa de enunciado e de pelo menos um critério de avaliação.');
    $tipoEntrega = in_array(post('tipo_entrega'), ['LINK_SIMULADOR', 'CODIGO', 'LINK_VIDEO'], true) ? post('tipo_entrega') : 'LINK_SIMULADOR';
    $conteudo = trim(str_replace("\r", '', (string) ($_POST['conteudo'] ?? '')));
    $notas = trim(str_replace("\r", '', (string) ($_POST['notas'] ?? '')));
    if (strlen($conteudo) > 200000 || strlen($notas) > 100000) $f('Texto muito longo.');
    // Mudar de módulo: só para outro módulo do mesmo curso, entrando no fim.
    $novoModulo = post('modulo_id');
    if ($novoModulo !== $a['modulo_id'] && !valor('SELECT 1 FROM modulos WHERE id = ? AND curso_id = ?', [$novoModulo, $a['curso_id']])) $f('Módulo inválido.');

    transacao(function () use ($a, $ex, $titulo, $tipo, $conteudo, $video, $sim, $quiz, $notas, $enunciado, $rubrica, $tipoEntrega, $novoModulo) {
        $ordem = (int) $a['ordem'];
        if ($novoModulo !== $a['modulo_id']) $ordem = (int) valor('SELECT COALESCE(MAX(ordem), 0) FROM aulas WHERE modulo_id = ?', [$novoModulo]) + 1;
        q('UPDATE aulas SET titulo = ?, tipo = ?, conteudo = ?, video_url = ?, simulador_url = ?, quiz = ?, obrigatoria = ?, duracao_min = ?, modulo_id = ?, ordem = ? WHERE id = ?',
            [$titulo, $tipo, $conteudo, $video, $sim, $quiz ? json_encode($quiz, JSON_UNESCAPED_UNICODE) : null,
                ($_POST['opcional'] ?? '') === 'on' ? 0 : 1, max(0, (int) post('duracao_min')), $novoModulo, $ordem, $a['id']]);
        if ($novoModulo !== $a['modulo_id']) renumerar('aulas', 'modulo_id', $a['modulo_id']);
        if ($notas !== '') q('INSERT INTO aula_notas (aula_id, texto) VALUES (?, ?) ON DUPLICATE KEY UPDATE texto = VALUES(texto)', [$a['id'], $notas]);
        else q('DELETE FROM aula_notas WHERE aula_id = ?', [$a['id']]);
        if ($tipo === 'EXERCICIO') {
            $vals = [$enunciado, json_encode($rubrica, JSON_UNESCAPED_UNICODE), $tipoEntrega, (int) post('max_tentativas') ?: null];
            if ($ex) q('UPDATE exercicios SET enunciado = ?, rubrica = ?, tipo_entrega = ?, max_tentativas = ? WHERE id = ?', array_merge($vals, [$ex['id']]));
            else q('INSERT INTO exercicios (id, aula_id, enunciado, rubrica, tipo_entrega, max_tentativas) VALUES (?,?,?,?,?,?)', array_merge([novo_id(), $a['id']], $vals));
        } elseif ($ex) {
            q('DELETE FROM exercicios WHERE id = ?', [$ex['id']]);
        }
    });
    auditar($admin['id'], null, 'aula.editada', 'Aula', $a['id']);
    $destino = post('depois') === 'ver' ? ['aula', ['id' => $a['id']]] : ['editar_aula', ['id' => $a['id']]];
    voltar('ok', 'Aula salva.', $destino[0], $destino[1]);
}

function acao_mover_aula(): void
{
    exigir_admin();
    $a = aula_com_curso(post('aula_id'));
    if (!$a) ir('admin');
    mover('aulas', 'modulo_id', $a['id'], post('direcao'));
    ir('admin_curso', ['id' => $a['curso_id']]);
}

function acao_mover_modulo(): void
{
    exigir_admin();
    $curso = mover('modulos', 'curso_id', post('modulo_id'), post('direcao'));
    $curso ? ir('admin_curso', ['id' => $curso]) : ir('admin');
}

function acao_renomear_modulo(): void
{
    exigir_admin();
    $m = um('SELECT * FROM modulos WHERE id = ?', [post('modulo_id')]);
    if (!$m) ir('admin');
    if (post('titulo') === '') voltar('erro', 'Informe o nome do módulo.', 'admin_curso', ['id' => $m['curso_id']]);
    q('UPDATE modulos SET titulo = ? WHERE id = ?', [post('titulo'), $m['id']]);
    voltar('ok', 'Módulo renomeado.', 'admin_curso', ['id' => $m['curso_id']]);
}

function acao_excluir_modulo(): void
{
    exigir_admin();
    $m = um('SELECT * FROM modulos WHERE id = ?', [post('modulo_id')]);
    if (!$m) ir('admin');
    if (valor('SELECT 1 FROM aulas WHERE modulo_id = ?', [$m['id']])) voltar('erro', 'Só é possível excluir um módulo vazio. Exclua ou mova as aulas antes.', 'admin_curso', ['id' => $m['curso_id']]);
    q('DELETE FROM modulos WHERE id = ?', [$m['id']]);
    renumerar('modulos', 'curso_id', $m['curso_id']);
    voltar('ok', 'Módulo excluído.', 'admin_curso', ['id' => $m['curso_id']]);
}
