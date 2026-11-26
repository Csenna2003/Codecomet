<?php
declare(strict_types=1);

/** Cada formulário envia "acao"; só funções com o prefixo acao_ podem ser chamadas. */
function executar_acao(string $acao): void
{
    if (!preg_match('/^[a-z_]+$/', $acao) || !function_exists("acao_$acao")) {
        http_response_code(400);
        exit('Ação inválida.');
    }
    ("acao_$acao")();
}

function email_valido(string $e): bool
{
    return (bool) filter_var($e, FILTER_VALIDATE_EMAIL);
}

function idade(string $nasc): int
{
    return (int) (new DateTime($nasc))->diff(new DateTime('today'))->y;
}

// ---------- Conta ----------

function acao_cadastrar(): void
{
    $nome = post('nome');
    $email = strtolower(post('email'));
    $senha = (string) ($_POST['senha'] ?? '');
    $nasc = post('nascimento');
    $codigo = post('codigo');
    $_SESSION['form_cadastro'] = ['nome' => $nome, 'email' => $email, 'nascimento' => $nasc, 'codigo' => $codigo];
    $falha = fn(string $m) => voltar('erro', $m, 'cadastro');

    if (mb_strlen($nome) < 2) $falha('Informe seu nome completo.');
    if (!email_valido($email)) $falha('E-mail inválido.');
    if (strlen($senha) < 8) $falha('A senha precisa ter pelo menos 8 caracteres.');
    $d = DateTime::createFromFormat('Y-m-d', $nasc);
    if (!$d || $d > new DateTime()) $falha('Informe a data de nascimento.');

    $menor = idade($nasc) < 18;
    $respNome = post('responsavel_nome');
    $respEmail = strtolower(post('responsavel_email'));
    if ($menor) {
        if (mb_strlen($respNome) < 2 || !email_valido($respEmail)) $falha('Menores de 18 anos precisam informar nome e e-mail do responsável.');
        if (($_POST['consentimento'] ?? '') !== 'on') $falha('O responsável precisa autorizar o cadastro do menor.');
    }
    if (valor('SELECT 1 FROM usuarios WHERE email = ?', [$email])) $falha('Já existe uma conta com este e-mail.');

    $id = novo_id();
    q(
        'INSERT INTO usuarios (id, nome, email, senha_hash, nascimento, responsavel_nome, responsavel_email, consentimento_em, criado_em)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$id, $nome, $email, password_hash($senha, PASSWORD_DEFAULT), $nasc,
            $menor ? $respNome : null, $menor ? $respEmail : null, $menor ? agora() : null, agora()]
    );
    unset($_SESSION['form_cadastro']);
    entrar_como(um('SELECT * FROM usuarios WHERE id = ?', [$id]));
    $primeiro = explode(' ', $nome)[0];
    enviar_email($email, 'Bem-vindo(a) à ' . nome_site() . '!',
        "Olá, $primeiro!\n\nSua conta foi criada com o e-mail $email. Para entrar de novo, use este e-mail e a senha que você escolheu.\n\nSe esquecer a senha, use a opção \"Esqueci minha senha\" na tela de entrada.",
        'Acessar a plataforma', url_absoluta('painel'));
    if ($menor) {
        enviar_email($respEmail, 'Cadastro de ' . $primeiro . ' na ' . nome_site(),
            "Olá, $respNome!\n\n$nome criou uma conta na plataforma de cursos " . nome_site() . " com o e-mail $email, informando você como responsável e com a sua autorização.\n\nSe você não reconhece este cadastro, responda para a escola ou fale com a nossa equipe.",
            'Conhecer a plataforma', url_absoluta('inicio'));
    }

    if ($codigo !== '') {
        $r = usar_convite($id, $codigo);
        if (isset($r['erro'])) voltar('erro', 'Conta criada, mas o código da escola não funcionou: ' . $r['erro'], 'painel');
    }
    voltar('ok', 'Conta criada. Bons estudos!', 'painel');
}

function acao_entrar(): void
{
    // Aceita e-mail ou nome de usuário (contas de aluno sem e-mail).
    $email = email_de_login(post('email'));
    $senha = (string) ($_POST['senha'] ?? '');
    if (login_bloqueado($email)) voltar('erro', 'Muitas tentativas. Aguarde 15 minutos ou peça um link de nova senha ao seu professor.', 'entrar');
    $u = um('SELECT * FROM usuarios WHERE email = ?', [$email]);
    // Mesma mensagem para e-mail inexistente e senha errada.
    if (!$u || !password_verify($senha, $u['senha_hash'])) {
        registrar_falha_login($email);
        voltar('erro', 'Login ou senha incorretos.', 'entrar');
    }
    limpar_falhas_login($email);
    entrar_como($u);
    ir('painel');
}

function acao_sair(): void
{
    encerrar_sessao();
    ir();
}

function acao_usar_codigo(): void
{
    $u = exigir_usuario();
    $r = usar_convite($u['id'], post('codigo'));
    if (isset($r['erro'])) voltar('erro', $r['erro'], 'painel');
    voltar('ok', "Você entrou em {$r['org']}.", 'painel');
}

/**
 * Link de nova senha. Admin da plataforma: qualquer conta, pelo e-mail.
 * Gestor de escola: só alunos e professores da própria escola.
 */
function acao_gerar_link_senha(): void
{
    $u = exigir_usuario();
    $orgId = post('org_id');
    if ($orgId !== '') {
        if (!eh_admin($u) && !isset(orgs_geridas($u)[$orgId])) ir('painel');
        $m = um(
            'SELECT m.papel, u.id, u.nome, u.admin FROM membros m JOIN usuarios u ON u.id = m.usuario_id WHERE m.usuario_id = ? AND m.org_id = ?',
            [post('usuario_id'), $orgId]
        );
        if (!$m || $m['admin'] || !in_array($m['papel'], ['ALUNO', 'PROFESSOR'], true)) {
            voltar('erro', 'Você só pode gerar nova senha para alunos e professores desta escola.', 'escola', ['org' => $orgId]);
        }
        $link = criar_link_senha($m['id'], $u['id']);
        auditar($u['id'], $orgId, 'senha.link_gerado', 'Usuario', $m['id']);
        $foi = email_link_senha($m['id'], $link);
        voltar('ok', ($foi ? "Enviamos o link por e-mail para {$m['nome']}. " : '') . "Se preferir, envie você mesmo (vale por 24 horas, um único uso): $link", 'escola', ['org' => $orgId]);
    }
    if (!eh_admin($u)) ir('painel');
    $alvo = um('SELECT id, nome FROM usuarios WHERE email = ?', [strtolower(post('email'))]);
    if (!$alvo) voltar('erro', 'Nenhuma conta com este e-mail.', 'admin');
    $link = criar_link_senha($alvo['id'], $u['id']);
    auditar($u['id'], null, 'senha.link_gerado', 'Usuario', $alvo['id']);
    $foi = email_link_senha($alvo['id'], $link);
    voltar('ok', ($foi ? "Enviamos o link por e-mail para {$alvo['nome']}. " : '') . "Se preferir, envie você mesmo (vale por 24 horas, um único uso): $link", 'admin');
}

/** "Esqueci minha senha": envia o link por e-mail. A resposta é a mesma exista ou não a conta. */
function acao_esqueci_senha(): void
{
    $email = strtolower(post('email'));
    $msg = 'Se houver uma conta com este e-mail, enviamos um link para criar uma nova senha. Confira também a caixa de spam.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) voltar('erro', 'Informe um e-mail válido.', 'esqueci');
    $chave = "senha:$email";
    $ip = 'senha-ip:' . ($_SERVER['REMOTE_ADDR'] ?? '');
    $desde = date('Y-m-d H:i:s', time() - 3600);
    $porEmail = (int) valor('SELECT COUNT(*) FROM tentativas_login WHERE chave = ? AND criado_em > ?', [$chave, $desde]);
    $porIp = (int) valor('SELECT COUNT(*) FROM tentativas_login WHERE chave = ? AND criado_em > ?', [$ip, $desde]);
    if ($porEmail >= 3 || $porIp >= 10) voltar('ok', $msg, 'entrar');
    q('INSERT INTO tentativas_login (chave, criado_em) VALUES (?,?), (?,?)', [$chave, agora(), $ip, agora()]);
    $u = um('SELECT id, nome FROM usuarios WHERE email = ?', [$email]);
    if ($u) {
        $link = criar_link_senha($u['id'], $u['id']);
        enviar_email($email, 'Criar nova senha — ' . nome_site(),
            'Olá, ' . explode(' ', $u['nome'])[0] . "!\n\nRecebemos um pedido para criar uma nova senha. O link vale por 24 horas e pode ser usado uma vez.\n\nSe não foi você, ignore este e-mail: sua senha atual continua valendo.",
            'Criar nova senha', $link);
    }
    voltar('ok', $msg, 'entrar');
}

function email_link_senha(string $uid, string $link): bool
{
    $al = um('SELECT nome, email FROM usuarios WHERE id = ?', [$uid]);
    return $al && enviar_email($al['email'], 'Criar nova senha — ' . nome_site(),
        'Olá, ' . explode(' ', $al['nome'])[0] . "!\n\nA equipe da escola gerou um link para você criar uma nova senha. Ele vale por 24 horas e pode ser usado uma vez.",
        'Criar nova senha', $link);
}

function acao_redefinir_senha(): void
{
    $token = post('token');
    $senha = (string) ($_POST['senha'] ?? '');
    $r = buscar_redefinicao($token);
    if (!$r) voltar('erro', 'Este link de nova senha não vale mais. Peça outro.', 'entrar');
    if (strlen($senha) < 8) voltar('erro', 'A senha precisa ter pelo menos 8 caracteres.', 'redefinir', ['token' => $token]);
    // Marca como usado só se ainda não foi: dois envios simultâneos não reutilizam o link.
    $ok = q('UPDATE redefinicoes SET usado_em = ? WHERE token_hash = ? AND usado_em IS NULL', [agora(), $r['token_hash']])->rowCount();
    if (!$ok) voltar('erro', 'Este link de nova senha não vale mais. Peça outro.', 'entrar');
    // Aumentar a versão derruba as sessões abertas com a senha antiga.
    q('UPDATE usuarios SET senha_hash = ?, senha_versao = senha_versao + 1 WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $r['usuario_id']]);
    q('DELETE FROM senhas_provisorias WHERE usuario_id = ?', [$r['usuario_id']]);
    limpar_falhas_login((string) valor('SELECT email FROM usuarios WHERE id = ?', [$r['usuario_id']]));
    voltar('ok', 'Senha alterada. Entre com a nova senha.', 'entrar');
}

// ---------- Aluno ----------

function acao_matricular(): void
{
    $u = exigir_usuario();
    $curso = um("SELECT * FROM cursos WHERE id = ? AND status = 'PUBLICADO'", [post('curso_id')]);
    if (!$curso) ir();
    if (acesso_ativo($u['id'], $curso['id'])) ir('curso', ['slug' => $curso['slug']]);

    // Reaproveita o pedido em aberto para o aluno não acumular cobranças do mesmo curso.
    $aberto = um("SELECT id FROM pedidos WHERE usuario_id = ? AND curso_id = ? AND status = 'PENDENTE'", [$u['id'], $curso['id']]);
    if ($aberto) {
        $pedidoId = $aberto['id'];
        q('UPDATE pedidos SET valor_centavos = ? WHERE id = ?', [$curso['preco_centavos'], $pedidoId]);
    } else {
        $pedidoId = novo_id();
        q('INSERT INTO pedidos (id, usuario_id, curso_id, valor_centavos, criado_em) VALUES (?,?,?,?,?)',
            [$pedidoId, $u['id'], $curso['id'], $curso['preco_centavos'], agora()]);
    }
    if ((int) $curso['preco_centavos'] === 0) {
        confirmar_pedido($pedidoId, 'gratuito');
        ir('curso', ['slug' => $curso['slug']]);
    }
    ir('pedido', ['id' => $pedidoId]);
}

/** O aluno avisa que fez o Pix; o pedido sobe para o topo da conferência. */
function acao_avisar_pagamento(): void
{
    $u = exigir_usuario();
    $id = post('pedido_id');
    $mudou = q("UPDATE pedidos SET avisou_pago_em = ? WHERE id = ? AND usuario_id = ? AND status = 'PENDENTE' AND avisou_pago_em IS NULL",
        [agora(), $id, $u['id']])->rowCount();
    if ($mudou) {
        $p = um('SELECT p.valor_centavos, c.titulo FROM pedidos p JOIN cursos c ON c.id = p.curso_id WHERE p.id = ?', [$id]);
        foreach (emails_admins() as $adm) {
            enviar_email($adm, 'Pix para conferir: ' . brl((int) $p['valor_centavos']) . ' — ' . $u['nome'],
                "{$u['nome']} ({$u['email']}) avisou que pagou o Pix de " . brl((int) $p['valor_centavos']) . " do curso {$p['titulo']}.\n\nIdentificador no extrato: $id\n\nConfira no extrato do banco e confirme o recebimento para liberar o curso.",
                'Conferir pagamentos', url_absoluta('admin_pedidos'));
        }
    }
    ir('pedido', ['id' => $id]);
}

/** Confere no servidor que o aluno tem matrícula e que a aula já foi liberada. */
function aula_do_aluno(string $aulaId): array
{
    $u = exigir_usuario();
    $aula = um('SELECT a.*, m.curso_id FROM aulas a JOIN modulos m ON m.id = a.modulo_id WHERE a.id = ?', [$aulaId]);
    if (!$aula) ir('painel');
    $curso = carregar_curso('id', $aula['curso_id']);
    $acesso = acesso_ativo($u['id'], $aula['curso_id']);
    if (!$curso || !$acesso) ir('painel');
    $estado = estado_curso($u['id'], $curso);
    if (empty($estado['por_aula'][$aula['id']]['liberada'])) ir('curso', ['slug' => $curso['slug']]);
    foreach (aulas_do_curso($curso) as $a) if ($a['id'] === $aula['id']) $aula['exercicio'] = $a['exercicio'];
    return [$u, $aula, $curso, $acesso];
}

function acao_marcar_concluida(): void
{
    [$u, $aula, $curso] = aula_do_aluno(post('aula_id'));
    // Quiz e exercício só concluem pelo fluxo próprio (acerto mínimo / envio).
    if (!in_array($aula['tipo'], ['QUIZ', 'EXERCICIO'], true)) concluir_aula($u['id'], $aula['id'], $curso['id']);
    ir('aula', ['id' => $aula['id']]);
}

function acao_responder_quiz(): void
{
    [$u, $aula, $curso] = aula_do_aluno(post('aula_id'));
    $perguntas = json_decode((string) $aula['quiz'], true) ?: [];
    if ($aula['tipo'] !== 'QUIZ' || !$perguntas) ir('aula', ['id' => $aula['id']]);
    $acertos = 0;
    foreach ($perguntas as $i => $p) {
        if (isset($_POST["q$i"]) && (int) $_POST["q$i"] === (int) $p['correta']) $acertos++;
    }
    $total = count($perguntas);
    $msg = "Você acertou $acertos de $total.";
    if ($acertos / $total >= 0.7) {
        concluir_aula($u['id'], $aula['id'], $curso['id']);
        voltar('ok', "$msg Quiz concluído!", 'aula', ['id' => $aula['id']]);
    }
    voltar('erro', "$msg É preciso acertar 70% — revise e tente de novo.", 'aula', ['id' => $aula['id']]);
}

function acao_enviar_entrega(): void
{
    [$u, $aula, $curso, $acesso] = aula_do_aluno(post('aula_id'));
    $ex = $aula['exercicio'];
    if (!$ex) ir('aula', ['id' => $aula['id']]);
    $falha = fn(string $m) => voltar('erro', $m, 'aula', ['id' => $aula['id']]);

    $link = post('link');
    $codigo = trim((string) ($_POST['codigo'] ?? ''));
    $obs = post('observacao');
    if ($ex['tipo_entrega'] === 'LINK_SIMULADOR' && !plataforma_de($link)) $falha('Cole o link do seu projeto no Tinkercad ou no Wokwi (https://...).');
    if ($ex['tipo_entrega'] === 'LINK_VIDEO' && !eh_https($link)) $falha('Cole o link do projeto ou do vídeo (https://...).');
    if ($ex['tipo_entrega'] === 'CODIGO' && strlen($codigo) < 10) $falha('Cole o código do seu programa.');
    if ($link !== '' && !eh_https($link)) $falha('O link precisa começar com https://');
    if (strlen($link) > 500 || strlen($codigo) > 100000 || mb_strlen($obs) > 2000) $falha('Texto muito longo.');

    $anteriores = todos('SELECT tentativa, status FROM entregas WHERE exercicio_id = ? AND usuario_id = ? ORDER BY tentativa DESC', [$ex['id'], $u['id']]);
    $status = array_column($anteriores, 'status');
    if (in_array('APROVADA', $status, true)) $falha('Este exercício já foi aprovado.');
    if (in_array('ENVIADA', $status, true)) $falha('Sua entrega anterior ainda está aguardando correção.');
    if ($ex['max_tentativas'] && count($anteriores) >= (int) $ex['max_tentativas']) $falha('Você atingiu o limite de tentativas. Fale com seu professor.');

    q(
        'INSERT INTO entregas (id, exercicio_id, usuario_id, org_id, tentativa, link, codigo, observacao, enviado_em) VALUES (?,?,?,?,?,?,?,?,?)',
        [novo_id(), $ex['id'], $u['id'], $acesso['org_id'], ((int) ($anteriores[0]['tentativa'] ?? 0)) + 1,
            $link ?: null, $codigo ?: null, $obs ?: null, agora()]
    );
    // Enviar já libera a próxima aula; o certificado continua exigindo aprovação.
    concluir_aula($u['id'], $aula['id'], $curso['id']);
    voltar('ok', 'Entrega enviada! Você será avisado aqui quando for corrigida.', 'aula', ['id' => $aula['id']]);
}

// ---------- Professor ----------

function acao_revisar_entrega(): void
{
    $u = exigir_usuario();
    $f = filtro_entregas($u);
    if (!$f) ir('painel');
    // O filtro vai na própria consulta: professor de outra escola recebe "não encontrado".
    $e = um(
        "SELECT e.*, x.rubrica, x.nota_maxima, m.curso_id FROM entregas e
         JOIN exercicios x ON x.id = e.exercicio_id JOIN aulas a ON a.id = x.aula_id JOIN modulos m ON m.id = a.modulo_id
         WHERE e.id = ? AND {$f[0]}",
        array_merge([post('entrega_id')], $f[1])
    );
    if (!$e) ir('professor');
    $falha = fn(string $m) => voltar('erro', $m, 'entrega', ['id' => $e['id']]);
    if ($e['status'] !== 'ENVIADA') $falha('Esta entrega já foi corrigida.');

    $decisao = post('decisao') === 'APROVADA' ? 'APROVADA' : 'PRECISA_AJUSTE';
    $notaTxt = post('nota');
    $max = (int) $e['nota_maxima'];
    if (!ctype_digit($notaTxt) || (int) $notaTxt > $max) $falha("A nota deve ser um número inteiro de 0 a $max.");
    $nota = (int) $notaTxt;
    $comentario = post('comentario');
    if ($decisao === 'PRECISA_AJUSTE' && mb_strlen($comentario) < 5) $falha('Explique ao aluno o que precisa ser ajustado.');
    $rubrica = json_decode($e['rubrica'], true) ?: [];
    $criterios = [];
    foreach ($rubrica as $i => $c) if (($_POST["c$i"] ?? '') === 'on') $criterios[] = $c;

    // Atualização condicional evita dupla correção simultânea.
    $ok = q("UPDATE entregas SET status = ?, nota = ?, revisado_em = ? WHERE id = ? AND status = 'ENVIADA'", [$decisao, $nota, agora(), $e['id']])->rowCount();
    if (!$ok) $falha('Esta entrega já foi corrigida.');
    q('INSERT INTO revisoes (id, entrega_id, revisor_id, decisao, nota, comentario, criterios, criado_em) VALUES (?,?,?,?,?,?,?,?)',
        [novo_id(), $e['id'], $u['id'], $decisao, $nota, $comentario, json_encode($criterios, JSON_UNESCAPED_UNICODE), agora()]);
    auditar($u['id'], $e['org_id'], 'entrega.' . strtolower($decisao), 'Entrega', $e['id'], ['nota' => $nota]);
    $al = um('SELECT u.nome, u.email, a.titulo, a.id AS aula_id FROM usuarios u, exercicios x JOIN aulas a ON a.id = x.aula_id WHERE u.id = ? AND x.id = ?', [$e['usuario_id'], $e['exercicio_id']]);
    if ($al) {
        enviar_email($al['email'], ($decisao === 'APROVADA' ? 'Entrega aprovada: ' : 'Ajuste pedido: ') . $al['titulo'],
            'Olá, ' . explode(' ', $al['nome'])[0] . "!\n\nSua entrega em \"{$al['titulo']}\" foi corrigida: " . ($decisao === 'APROVADA' ? 'aprovada' : 'o professor pediu um ajuste') . " (nota $nota de $max)."
            . ($comentario !== '' ? "\n\nComentário do professor: $comentario" : ''),
            'Ver a correção', url_absoluta('aula', ['id' => $al['aula_id']]));
    }
    if ($decisao === 'APROVADA') verificar_certificado($e['usuario_id'], $e['curso_id']);
    voltar('ok', 'Correção registrada.', 'professor');
}
