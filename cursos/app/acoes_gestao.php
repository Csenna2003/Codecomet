<?php
declare(strict_types=1);

// ---------- Escola ----------

function acao_criar_turma(): void
{
    $org = post('org_id');
    exigir_gestao_org($org);
    $nome = post('nome');
    if ($nome === '') voltar('erro', 'Informe o nome da turma.', 'escola', ['org' => $org]);
    $prof = post('professor_id') ?: null;
    if ($prof && !valor("SELECT 1 FROM membros WHERE usuario_id = ? AND org_id = ? AND papel <> 'ALUNO'", [$prof, $org])) {
        voltar('erro', 'Professor inválido.', 'escola', ['org' => $org]);
    }
    q('INSERT INTO turmas (id, org_id, nome, periodo, professor_id, criado_em) VALUES (?,?,?,?,?,?)',
        [novo_id(), $org, $nome, post('periodo') ?: null, $prof, agora()]);
    voltar('ok', 'Turma criada.', 'escola', ['org' => $org]);
}

function acao_criar_convite(): void
{
    $org = post('org_id');
    exigir_gestao_org($org);
    $papel = post('papel') === 'PROFESSOR' ? 'PROFESSOR' : 'ALUNO';
    $turma = post('turma_id') ?: null;
    if ($turma && !valor('SELECT 1 FROM turmas WHERE id = ? AND org_id = ?', [$turma, $org])) voltar('erro', 'Turma inválida.', 'escola', ['org' => $org]);
    $max = (int) post('max_usos') ?: null;
    $codigo = codigo_aleatorio(8);
    q('INSERT INTO convites (id, codigo, org_id, turma_id, papel, max_usos, criado_em) VALUES (?,?,?,?,?,?,?)',
        [novo_id(), $codigo, $org, $papel === 'ALUNO' ? $turma : null, $papel, $max, agora()]);
    voltar('ok', "Código criado: $codigo", 'escola', ['org' => $org]);
}

function acao_desativar_convite(): void
{
    $org = post('org_id');
    exigir_gestao_org($org);
    q('UPDATE convites SET ativo = 0 WHERE id = ? AND org_id = ?', [post('convite_id'), $org]);
    voltar('ok', 'Código desativado.', 'escola', ['org' => $org]);
}

function acao_remover_membro(): void
{
    $org = post('org_id');
    $gestor = exigir_gestao_org($org);
    $alvo = post('usuario_id');
    if ($alvo === $gestor['id']) voltar('erro', 'Você não pode remover a si mesmo.', 'escola', ['org' => $org]);
    if (!valor('SELECT 1 FROM membros WHERE usuario_id = ? AND org_id = ?', [$alvo, $org])) voltar('erro', 'Pessoa não encontrada nesta escola.', 'escola', ['org' => $org]);
    remover_da_escola($alvo, $org);
    auditar($gestor['id'], $org, 'membro.removido', 'Usuario', $alvo);
    voltar('ok', 'Pessoa removida da escola. O histórico e os certificados dela foram mantidos.', 'escola', ['org' => $org]);
}

// ---------- Administração ----------

function acao_criar_org(): void
{
    $admin = exigir_admin();
    $nome = post('nome');
    if ($nome === '') voltar('erro', 'Informe o nome da escola.', 'admin');
    $id = novo_id();
    q('INSERT INTO organizacoes (id, nome, documento, criado_em) VALUES (?,?,?,?)', [$id, $nome, post('documento') ?: null, agora()]);
    auditar($admin['id'], $id, 'escola.criada', 'Organizacao', $id);
    $respNome = post('resp_nome');
    if ($respNome !== '') {
        // Acesso da coordenação criado na hora, com login e senha provisória.
        $_POST['org_id'] = $id;
        $_POST['papel'] = 'COORDENADOR';
        $_POST['pessoas'] = $respNome . (post('resp_email') !== '' ? '; ' . post('resp_email') : '');
        $_POST['turma_id'] = $_POST['curso_id'] = '';
        acao_criar_acessos();
    }
    // Código de uso único para quem vai administrar a escola.
    $codigo = codigo_aleatorio(8);
    q("INSERT INTO convites (id, codigo, org_id, papel, max_usos, criado_em) VALUES (?,?,?,'ADMIN_ORG',1,?)", [novo_id(), $codigo, $id, agora()]);
    voltar('ok', "Escola criada. Envie ao responsável o código de administrador: $codigo", 'admin');
}

function acao_criar_licenca(): void
{
    $admin = exigir_admin();
    $org = post('org_id');
    $curso = post('curso_id');
    $vagas = (int) post('vagas');
    $validadeTxt = post('validade');
    if (!valor('SELECT 1 FROM organizacoes WHERE id = ?', [$org]) || !valor('SELECT 1 FROM cursos WHERE id = ?', [$curso]) || $vagas < 1) {
        voltar('erro', 'Escolha a escola, o curso e um número de vagas válido.', 'admin');
    }
    $validade = null;
    if ($validadeTxt !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $validadeTxt);
        if (!$d) voltar('erro', 'Data de validade inválida.', 'admin');
        $validade = $d->format('Y-m-d') . ' 23:59:59';
    }
    $lic = um('SELECT id FROM licencas WHERE org_id = ? AND curso_id = ?', [$org, $curso]);
    if ($lic) {
        q('UPDATE licencas SET vagas_total = ?, validade = ? WHERE id = ?', [$vagas, $validade, $lic['id']]);
        $licId = $lic['id'];
        // Mantém o prazo dos acessos já concedidos igual ao da licença renovada.
        q('UPDATE acessos SET fim = ? WHERE licenca_id = ?', [$validade, $licId]);
    } else {
        $licId = novo_id();
        q('INSERT INTO licencas (id, org_id, curso_id, vagas_total, validade, criado_em) VALUES (?,?,?,?,?,?)', [$licId, $org, $curso, $vagas, $validade, agora()]);
    }
    aplicar_licencas($org);
    auditar($admin['id'], $org, 'licenca.definida', 'Licenca', $licId, ['vagas' => $vagas]);
    voltar('ok', 'Licença salva e vagas distribuídas aos alunos da escola.', 'admin');
}

function acao_criar_curso(): void
{
    exigir_admin();
    $titulo = post('titulo');
    if ($titulo === '') voltar('erro', 'Informe o título do curso.', 'admin');
    $slug = slugify($titulo) ?: 'curso';
    if (valor('SELECT 1 FROM cursos WHERE slug = ?', [$slug])) $slug .= '-' . strtolower(codigo_aleatorio(4));
    $id = novo_id();
    q('INSERT INTO cursos (id, slug, titulo, descricao, criado_em) VALUES (?,?,?,?,?)', [$id, $slug, $titulo, post('descricao'), agora()]);
    ir('admin_curso', ['id' => $id]);
}

function acao_atualizar_curso(): void
{
    exigir_admin();
    $id = post('curso_id');
    $status = post('status');
    $preco = (int) round(((float) str_replace(',', '.', post('preco'))) * 100);
    $carga = (int) post('carga_horaria');
    $nivel = post('nivel');
    if (!isset(ROTULO_STATUS_CURSO[$status]) || !isset(ROTULO_NIVEL[$nivel]) || $preco < 0 || $carga < 0 || post('titulo') === '') {
        voltar('erro', 'Confira título, preço, carga horária e status.', 'admin_curso', ['id' => $id]);
    }
    if ($status === 'PUBLICADO' && !valor('SELECT 1 FROM aulas a JOIN modulos m ON m.id = a.modulo_id WHERE m.curso_id = ?', [$id])) {
        voltar('erro', 'Adicione pelo menos uma aula antes de publicar.', 'admin_curso', ['id' => $id]);
    }
    q('UPDATE cursos SET titulo = ?, descricao = ?, nivel = ?, status = ?, preco_centavos = ?, carga_horaria = ? WHERE id = ?',
        [post('titulo'), post('descricao'), $nivel, $status, $preco, $carga, $id]);
    voltar('ok', 'Curso salvo.', 'admin_curso', ['id' => $id]);
}

function acao_criar_modulo(): void
{
    exigir_admin();
    $curso = post('curso_id');
    if (post('titulo') === '') voltar('erro', 'Informe o título do módulo.', 'admin_curso', ['id' => $curso]);
    $ordem = (int) valor('SELECT COALESCE(MAX(ordem), 0) FROM modulos WHERE curso_id = ?', [$curso]) + 1;
    q('INSERT INTO modulos (id, curso_id, ordem, titulo) VALUES (?,?,?,?)', [novo_id(), $curso, $ordem, post('titulo')]);
    voltar('ok', 'Módulo criado.', 'admin_curso', ['id' => $curso]);
}

function acao_criar_aula(): void
{
    exigir_admin();
    $mod = um('SELECT * FROM modulos WHERE id = ?', [post('modulo_id')]);
    if (!$mod) ir('admin');
    $falha = fn(string $m) => voltar('erro', $m, 'admin_curso', ['id' => $mod['curso_id']]);
    $titulo = post('titulo');
    $tipo = post('tipo');
    if ($titulo === '' || !isset(ROTULO_TIPO_AULA[$tipo])) $falha('Informe título e tipo da aula.');

    $video = post('video_url') ?: null;
    $sim = post('simulador_url') ?: null;
    if ($tipo === 'VIDEO' && !($video && embed_video($video))) $falha('Aula em vídeo precisa de um link do YouTube ou do Vimeo.');
    if ($video && !embed_video($video)) $falha('Link de vídeo não reconhecido (use YouTube ou Vimeo).');
    if ($tipo === 'SIMULADOR' && !$sim) $falha('Aula de simulador precisa do link do projeto.');
    if ($sim && !embed_simulador($sim)) $falha('Link de simulador não reconhecido. Use tinkercad.com/things/... ou wokwi.com/projects/...');

    $quiz = $tipo === 'QUIZ' ? parse_quiz((string) ($_POST['quiz'] ?? '')) : [];
    if ($tipo === 'QUIZ' && !$quiz) $falha('Quiz sem perguntas válidas. Marque a opção correta com * e as demais com -.');
    $rubrica = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string) ($_POST['rubrica'] ?? '')))), 'strlen'));
    $enunciado = post('enunciado');
    if ($tipo === 'EXERCICIO' && ($enunciado === '' || !$rubrica)) $falha('Exercício precisa de enunciado e de pelo menos um critério de avaliação.');
    $tipoEntrega = in_array(post('tipo_entrega'), ['LINK_SIMULADOR', 'CODIGO', 'LINK_VIDEO'], true) ? post('tipo_entrega') : 'LINK_SIMULADOR';

    $ordem = (int) valor('SELECT COALESCE(MAX(ordem), 0) FROM aulas WHERE modulo_id = ?', [$mod['id']]) + 1;
    $aulaId = novo_id();
    transacao(function () use ($aulaId, $mod, $ordem, $titulo, $tipo, $video, $sim, $quiz, $enunciado, $rubrica, $tipoEntrega) {
        q('INSERT INTO aulas (id, modulo_id, ordem, titulo, tipo, conteudo, video_url, simulador_url, quiz, obrigatoria, duracao_min)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$aulaId, $mod['id'], $ordem, $titulo, $tipo, trim((string) ($_POST['conteudo'] ?? '')), $video, $sim,
                $quiz ? json_encode($quiz, JSON_UNESCAPED_UNICODE) : null, ($_POST['opcional'] ?? '') === 'on' ? 0 : 1, (int) post('duracao_min')]);
        if ($tipo === 'EXERCICIO') {
            q('INSERT INTO exercicios (id, aula_id, enunciado, rubrica, tipo_entrega, max_tentativas) VALUES (?,?,?,?,?,?)',
                [novo_id(), $aulaId, $enunciado, json_encode($rubrica, JSON_UNESCAPED_UNICODE), $tipoEntrega, (int) post('max_tentativas') ?: null]);
        }
    });
    voltar('ok', 'Aula criada.', 'admin_curso', ['id' => $mod['curso_id']]);
}

function acao_excluir_aula(): void
{
    exigir_admin();
    $a = um('SELECT a.id, m.curso_id FROM aulas a JOIN modulos m ON m.id = a.modulo_id WHERE a.id = ?', [post('aula_id')]);
    if (!$a) ir('admin');
    // Excluir apagaria as entregas dos alunos junto.
    if (valor('SELECT 1 FROM entregas e JOIN exercicios x ON x.id = e.exercicio_id WHERE x.aula_id = ?', [$a['id']])) {
        voltar('erro', 'Esta aula já tem entregas de alunos e não pode ser excluída.', 'admin_curso', ['id' => $a['curso_id']]);
    }
    q('DELETE FROM aulas WHERE id = ?', [$a['id']]);
    voltar('ok', 'Aula excluída.', 'admin_curso', ['id' => $a['curso_id']]);
}

function acao_criar_trilha(): void
{
    exigir_admin();
    $titulo = post('titulo');
    $cursos = array_values(array_filter((array) ($_POST['cursos'] ?? []), 'is_string'));
    if ($titulo === '' || !$cursos) voltar('erro', 'Informe o título e escolha pelo menos um curso para a trilha.', 'admin');
    $id = novo_id();
    q('INSERT INTO trilhas (id, titulo, descricao, criado_em) VALUES (?,?,?,?)', [$id, $titulo, post('descricao'), agora()]);
    foreach ($cursos as $i => $c) {
        if (valor('SELECT 1 FROM cursos WHERE id = ?', [$c])) q('INSERT IGNORE INTO trilha_cursos (trilha_id, curso_id, ordem) VALUES (?,?,?)', [$id, $c, $i + 1]);
    }
    voltar('ok', 'Trilha criada.', 'admin');
}

function acao_excluir_trilha(): void
{
    exigir_admin();
    q('DELETE FROM trilhas WHERE id = ?', [post('trilha_id')]);
    voltar('ok', 'Trilha excluída.', 'admin');
}

function acao_confirmar_pagamento(): void
{
    $admin = exigir_admin();
    $id = post('pedido_id');
    $p = um("SELECT * FROM pedidos WHERE id = ? AND status = 'PENDENTE'", [$id]);
    if (!$p) voltar('erro', 'Este pedido não está mais pendente.', 'admin_pedidos');
    confirmar_pedido($id, 'pix-manual');
    auditar($admin['id'], null, 'pedido.confirmado', 'Pedido', $id, ['valor' => (int) $p['valor_centavos']]);
    $al = um('SELECT u.nome, u.email, c.titulo, c.slug FROM usuarios u JOIN cursos c ON c.id = ? WHERE u.id = ?', [$p['curso_id'], $p['usuario_id']]);
    if ($al) {
        enviar_email($al['email'], 'Pagamento confirmado: ' . $al['titulo'],
            'Olá, ' . explode(' ', $al['nome'])[0] . "!\n\nRecebemos o seu Pix de " . brl((int) $p['valor_centavos']) . " e o curso {$al['titulo']} já está liberado. Bons estudos!",
            'Começar o curso', url_absoluta('curso', ['slug' => $al['slug']]));
    }
    voltar('ok', 'Pagamento confirmado e curso liberado para o aluno.', 'admin_pedidos');
}

function acao_testar_email(): void
{
    $u = exigir_admin();
    $ok = enviar_email($u['email'], 'Teste de e-mail — ' . nome_site(),
        "Se você recebeu esta mensagem, o envio de e-mails da plataforma está funcionando.\n\nRemetente usado: " . remetente_email(),
        'Abrir a plataforma', url_absoluta('admin'));
    voltar($ok ? 'ok' : 'erro', $ok
        ? "E-mail de teste enviado para {$u['email']}. Confira a caixa de entrada e a de spam."
        : 'A hospedagem recusou o envio. Confira em cPanel → Contas de e-mail se o envio está habilitado.', 'admin');
}

function acao_cancelar_pedido(): void
{
    $admin = exigir_admin();
    $id = post('pedido_id');
    if (!q("UPDATE pedidos SET status = 'CANCELADO' WHERE id = ? AND status = 'PENDENTE'", [$id])->rowCount()) {
        voltar('erro', 'Este pedido não está mais pendente.', 'admin_pedidos');
    }
    auditar($admin['id'], null, 'pedido.cancelado', 'Pedido', $id);
    voltar('ok', 'Pedido cancelado.', 'admin_pedidos');
}
