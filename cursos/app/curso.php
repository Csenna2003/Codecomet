<?php
declare(strict_types=1);

/** Curso com módulos, aulas e exercícios, em ordem. */
function carregar_curso(string $campo, string $valor): ?array
{
    if (!in_array($campo, ['id', 'slug'], true)) return null;
    $c = um("SELECT * FROM cursos WHERE $campo = ?", [$valor]);
    if (!$c) return null;
    $c['modulos'] = todos('SELECT * FROM modulos WHERE curso_id = ? ORDER BY ordem', [$c['id']]);
    $aulas = todos(
        'SELECT a.* FROM aulas a JOIN modulos m ON m.id = a.modulo_id WHERE m.curso_id = ? ORDER BY m.ordem, a.ordem',
        [$c['id']]
    );
    $ex = [];
    if ($aulas) {
        $ids = array_column($aulas, 'id');
        foreach (todos('SELECT * FROM exercicios WHERE aula_id IN (' . marcas($ids) . ')', $ids) as $x) {
            $x['rubrica'] = json_decode($x['rubrica'], true) ?: [];
            $ex[$x['aula_id']] = $x;
        }
    }
    foreach ($c['modulos'] as &$m) {
        $m['aulas'] = [];
        foreach ($aulas as $a) {
            if ($a['modulo_id'] === $m['id']) {
                $a['exercicio'] = $ex[$a['id']] ?? null;
                $m['aulas'][] = $a;
            }
        }
    }
    unset($m);
    return $c;
}

function aulas_do_curso(array $curso): array
{
    $r = [];
    foreach ($curso['modulos'] as $m) foreach ($m['aulas'] as $a) $r[] = $a;
    return $r;
}

function acesso_ativo(string $uid, string $cursoId): ?array
{
    $a = um('SELECT * FROM acessos WHERE usuario_id = ? AND curso_id = ?', [$uid, $cursoId]);
    if (!$a || $a['status'] !== 'ATIVO') return null;
    if ($a['fim'] && strtotime($a['fim']) < time()) return null;
    // Escola desativada: os acessos que vieram da licença dela ficam suspensos.
    if ($a['origem'] === 'LICENCA_ESCOLA' && $a['org_id'] && !valor('SELECT ativa FROM organizacoes WHERE id = ?', [$a['org_id']])) return null;
    return $a;
}

/**
 * 'aluno': tem matrícula e registra progresso.
 * 'preview': equipe (admin da plataforma, ou equipe de escola com licença do curso)
 * vê todas as aulas sem ocupar vaga nem registrar progresso.
 */
function modo_acesso(array $u, string $cursoId): ?string
{
    if (acesso_ativo($u['id'], $cursoId)) return 'aluno';
    if (eh_admin($u)) return 'preview';
    $orgs = [];
    foreach ($u['membros'] as $m) if ($m['papel'] !== 'ALUNO') $orgs[] = $m['org_id'];
    if ($orgs && valor(
        'SELECT 1 FROM licencas WHERE curso_id = ? AND org_id IN (' . marcas($orgs) . ')',
        array_merge([$cursoId], $orgs)
    )) return 'preview';
    return null;
}

/** Liberação sequencial: a aula abre quando todas as obrigatórias anteriores estão concluídas. */
function estado_curso(string $uid, array $curso): array
{
    $aulas = aulas_do_curso($curso);
    $feitas = [];
    if ($aulas) {
        $ids = array_column($aulas, 'id');
        foreach (todos(
            'SELECT aula_id FROM progresso WHERE usuario_id = ? AND aula_id IN (' . marcas($ids) . ')',
            array_merge([$uid], $ids)
        ) as $p) $feitas[$p['aula_id']] = true;
    }
    $por = [];
    $bloqueado = false;
    $proxima = null;
    $obrig = 0;
    $obrigFeitas = 0;
    foreach ($aulas as $a) {
        $concluida = isset($feitas[$a['id']]);
        $por[$a['id']] = ['concluida' => $concluida, 'liberada' => !$bloqueado];
        if (!$concluida && !$bloqueado && $proxima === null) $proxima = $a['id'];
        if ($a['obrigatoria']) {
            $obrig++;
            if ($concluida) $obrigFeitas++;
            else $bloqueado = true;
        }
    }
    return [
        'por_aula' => $por,
        'percentual' => $obrig ? (int) round($obrigFeitas / $obrig * 100) : 0,
        'proxima' => $proxima,
    ];
}

function concluir_aula(string $uid, string $aulaId, string $cursoId): void
{
    q('INSERT IGNORE INTO progresso (usuario_id, aula_id, concluido_em) VALUES (?,?,?)', [$uid, $aulaId, agora()]);
    verificar_certificado($uid, $cursoId);
}

/** Emite o certificado com todas as aulas obrigatórias concluídas e todos os exercícios obrigatórios aprovados. */
function verificar_certificado(string $uid, string $cursoId): ?array
{
    $existente = um('SELECT * FROM certificados WHERE usuario_id = ? AND curso_id = ?', [$uid, $cursoId]);
    if ($existente) return $existente;
    $curso = carregar_curso('id', $cursoId);
    if (!$curso) return null;
    $obrig = array_values(array_filter(aulas_do_curso($curso), fn($a) => (int) $a['obrigatoria'] === 1));
    if (!$obrig) return null;
    $ids = array_column($obrig, 'id');
    $feitas = (int) valor(
        'SELECT COUNT(*) FROM progresso WHERE usuario_id = ? AND aula_id IN (' . marcas($ids) . ')',
        array_merge([$uid], $ids)
    );
    if ($feitas < count($obrig)) return null;

    $exs = [];
    foreach ($obrig as $a) if ($a['exercicio']) $exs[] = $a['exercicio']['id'];
    if ($exs) {
        $aprovados = (int) valor(
            "SELECT COUNT(DISTINCT exercicio_id) FROM entregas
             WHERE usuario_id = ? AND status = 'APROVADA' AND exercicio_id IN (" . marcas($exs) . ')',
            array_merge([$uid], $exs)
        );
        if ($aprovados < count($exs)) return null;
    }

    $acesso = um('SELECT org_id FROM acessos WHERE usuario_id = ? AND curso_id = ?', [$uid, $cursoId]);
    $id = novo_id();
    q(
        'INSERT IGNORE INTO certificados (id, codigo, usuario_id, curso_id, org_id, curso_versao, carga_horaria, emitido_em)
         VALUES (?,?,?,?,?,?,?,?)',
        [$id, 'CC-' . codigo_aleatorio(4) . '-' . codigo_aleatorio(4), $uid, $cursoId, $acesso['org_id'] ?? null,
            $curso['versao'], $curso['carga_horaria'], agora()]
    );
    auditar($uid, $acesso['org_id'] ?? null, 'certificado.emitido', 'Certificado', $id);
    $cert = um('SELECT * FROM certificados WHERE usuario_id = ? AND curso_id = ?', [$uid, $cursoId]);
    $al = um('SELECT nome, email FROM usuarios WHERE id = ?', [$uid]);
    if ($cert && $al && function_exists('enviar_email')) {
        enviar_email($al['email'], 'Parabéns! Seu certificado de ' . $curso['titulo'],
            'Olá, ' . explode(' ', $al['nome'])[0] . "!\n\nVocê concluiu o curso {$curso['titulo']} e o seu certificado já está disponível. Ele tem um código de verificação ({$cert['codigo']}) que qualquer pessoa pode conferir.",
            'Ver o certificado', url_absoluta('certificado', ['codigo' => $cert['codigo']]));
    }
    return $cert;
}

/**
 * Formato de autoria do quiz: blocos separados por linha em branco; a primeira linha
 * é a pergunta, "*" marca a opção correta e "-" as demais.
 */
function parse_quiz(string $texto): array
{
    $r = [];
    foreach (preg_split('/\R\s*\R/', str_replace("\r", '', $texto)) as $bloco) {
        $linhas = array_values(array_filter(array_map('trim', explode("\n", $bloco)), 'strlen'));
        if (count($linhas) < 3) continue;
        $opcoes = [];
        $correta = -1;
        foreach (array_slice($linhas, 1) as $l) {
            if ($l[0] === '*') $correta = count($opcoes);
            if ($l[0] === '*' || $l[0] === '-') $opcoes[] = trim(substr($l, 1));
        }
        if ($correta >= 0 && count($opcoes) >= 2) $r[] = ['pergunta' => $linhas[0], 'opcoes' => $opcoes, 'correta' => $correta];
    }
    return $r;
}
