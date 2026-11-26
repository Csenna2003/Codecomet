<?php
declare(strict_types=1);

/**
 * Contas criadas pela administração ou pela escola, com login e senha provisória.
 * Aluno sem e-mail entra com um nome de usuário (ex.: joao.silva); por dentro ele
 * fica guardado como joao.silva@sem-email.invalid (domínio reservado, nunca recebe e-mail).
 */
const SEM_EMAIL = '@sem-email.invalid';
const PALAVRAS_SENHA = ['robo', 'led', 'motor', 'chip', 'servo', 'placa', 'sensor', 'pixel', 'codigo', 'bit', 'byte', 'cubo',
    'luz', 'raio', 'onda', 'astro', 'cometa', 'lua', 'sol', 'nave', 'foguete', 'drone', 'trilha', 'mapa', 'ponte', 'torre',
    'mola', 'roda', 'eixo', 'polia', 'gear', 'volt', 'ohm', 'ima', 'bussola', 'radar', 'laser', 'prisma', 'cristal', 'planeta'];

function email_de_login(string $login): string
{
    $login = strtolower(trim($login));
    return str_contains($login, '@') ? $login : $login . SEM_EMAIL;
}

function login_de(string $email): string
{
    return str_ends_with($email, SEM_EMAIL) ? substr($email, 0, -strlen(SEM_EMAIL)) : $email;
}

function tem_email_real(string $email): bool
{
    return !str_ends_with($email, SEM_EMAIL);
}

function gerar_senha(): string
{
    return PALAVRAS_SENHA[random_int(0, count(PALAVRAS_SENHA) - 1)] . '-' . PALAVRAS_SENHA[random_int(0, count(PALAVRAS_SENHA) - 1)] . '-' . random_int(10, 99);
}

/** "João da Silva Souza" -> "joao.souza", sem repetir um login existente. */
function gerar_login(string $nome): string
{
    $partes = array_values(array_filter(explode('-', slugify($nome))));
    $base = $partes ? ($partes[0] . (count($partes) > 1 ? '.' . end($partes) : '')) : 'aluno';
    $base = substr($base, 0, 40);
    $login = $base;
    while (valor('SELECT 1 FROM usuarios WHERE email = ?', [$login . SEM_EMAIL])) $login = $base . random_int(10, 999);
    return $login;
}

/** Cria a conta com senha provisória. Retorna [id, login, senha]. */
function criar_conta_acesso(string $nome, ?string $email): array
{
    $login = $email ?: gerar_login($nome);
    $senha = gerar_senha();
    $id = novo_id();
    q('INSERT INTO usuarios (id, nome, email, senha_hash, criado_em) VALUES (?,?,?,?,?)',
        [$id, $nome, email_de_login($login), password_hash($senha, PASSWORD_DEFAULT), agora()]);
    q('INSERT IGNORE INTO senhas_provisorias (usuario_id, criado_em) VALUES (?,?)', [$id, agora()]);
    return [$id, $login, $senha];
}

function url_acesso(string $login): string
{
    return url_absoluta('entrar', ['login' => $login]);
}

function texto_acesso(string $nome, string $login, string $senha): string
{
    return "Olá, " . explode(' ', $nome)[0] . "! Seu acesso à " . nome_site() . ":\n"
        . "Link: " . url_acesso($login) . "\nLogin: $login\nSenha: $senha\n"
        . "No primeiro acesso, troque a senha em Meu painel → Meus dados.";
}

// ---------- Ações ----------

/**
 * Cria várias contas de uma vez (uma pessoa por linha: "Nome" ou "Nome; e-mail").
 * Admin: qualquer escola, turma, curso e papel. Coordenação: só a própria escola, alunos e professores.
 */
function acao_criar_acessos(): void
{
    $u = exigir_usuario();
    $admin = eh_admin($u);
    $org = post('org_id') ?: null;
    $turma = post('turma_id') ?: null;
    $volta = $admin ? ['admin_alunos', []] : ['escola', ['org' => (string) $org]];
    $f = fn(string $m) => voltar('erro', $m, $volta[0], $volta[1]);
    if ($turma) {
        $t = um('SELECT org_id FROM turmas WHERE id = ?', [$turma]);
        if (!$t || ($org && $t['org_id'] !== $org)) $f('A turma escolhida não é desta escola.');
        $org = $t['org_id'];
    }
    if (!$admin && (!$org || !isset(orgs_geridas($u)[$org]))) ir('painel');
    if ($org && !valor('SELECT 1 FROM organizacoes WHERE id = ?', [$org])) $f('Escola inválida.');
    $papeis = $admin ? ['ALUNO', 'PROFESSOR', 'COORDENADOR'] : ['ALUNO', 'PROFESSOR'];
    $papel = in_array(post('papel'), $papeis, true) ? post('papel') : 'ALUNO';
    if ($papel === 'COORDENADOR' && !$org) $f('Escolha a escola da coordenação.');
    $curso = $admin && post('curso_id') ? um("SELECT id, titulo FROM cursos WHERE id = ? AND status = 'PUBLICADO'", [post('curso_id')]) : null;
    if ($admin && post('curso_id') && !$curso) $f('O curso precisa estar publicado.');
    $origem = post('origem') === 'BOLSA' ? 'BOLSA' : 'MANUAL';

    $linhas = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string) ($_POST['pessoas'] ?? '')))), 'strlen'));
    if (!$linhas) $f('Escreva pelo menos um nome (um por linha).');
    if (count($linhas) > 200) $f('No máximo 200 pessoas por vez.');
    $pessoas = [];
    foreach ($linhas as $n => $l) {
        [$nome, $email] = array_pad(array_map('trim', preg_split('/[;\t]/', $l, 2)), 2, '');
        $email = strtolower($email);
        if (mb_strlen($nome) < 2) $f('Linha ' . ($n + 1) . ': informe o nome.');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $f('Linha ' . ($n + 1) . ": e-mail inválido ($email).");
        $pessoas[] = [$nome, $email];
    }

    $res = [];
    foreach ($pessoas as [$nome, $email]) {
        $existe = $email ? um('SELECT id, nome, email FROM usuarios WHERE email = ?', [$email]) : null;
        if ($existe) {
            $id = $existe['id'];
            $res[] = ['nome' => $existe['nome'], 'login' => login_de($existe['email']), 'senha' => null, 'obs' => 'Já tinha conta: continua com a senha dela'];
        } else {
            [$id, $login, $senha] = criar_conta_acesso($nome, $email ?: null);
            $res[] = ['nome' => $nome, 'login' => $login, 'senha' => $senha, 'obs' => ''];
        }
        if ($org) {
            $papelOrg = $papel === 'COORDENADOR' ? 'COORDENADOR' : $papel;
            q('INSERT IGNORE INTO membros (id, usuario_id, org_id, papel, criado_em) VALUES (?,?,?,?,?)', [novo_id(), $id, $org, $papelOrg, agora()]);
            if ($turma && $papel === 'ALUNO') q('INSERT IGNORE INTO turma_alunos (turma_id, usuario_id, entrou_em) VALUES (?,?,?)', [$turma, $id, agora()]);
        }
        if ($papel === 'PROFESSOR') q('INSERT IGNORE INTO professores (usuario_id, criado_em, atualizado_em) VALUES (?,?,?)', [$id, agora(), agora()]);
        if ($curso) {
            $ac = um('SELECT id FROM acessos WHERE usuario_id = ? AND curso_id = ?', [$id, $curso['id']]);
            if ($ac) q("UPDATE acessos SET origem = ?, status = 'ATIVO', org_id = NULL, licenca_id = NULL, inicio = ?, fim = NULL WHERE id = ?", [$origem, agora(), $ac['id']]);
            else q("INSERT INTO acessos (id, usuario_id, curso_id, origem, status, inicio) VALUES (?,?,?,?, 'ATIVO', ?)", [novo_id(), $id, $curso['id'], $origem, agora()]);
        }
        auditar($u['id'], $org, 'acesso.criado', 'Usuario', $id, ['papel' => $papel]);
    }
    if ($org) aplicar_licencas($org);
    $_SESSION['acessos_criados'] = [
        'pessoas' => $res,
        'contexto' => trim(implode(' · ', array_filter([
            $org ? (string) valor('SELECT nome FROM organizacoes WHERE id = ?', [$org]) : null,
            $turma ? 'Turma ' . valor('SELECT nome FROM turmas WHERE id = ?', [$turma]) : null,
            $curso ? 'Curso ' . $curso['titulo'] : null,
            ROTULO_PAPEL[$papel === 'COORDENADOR' ? 'COORDENADOR' : $papel],
        ]))),
        'telefone' => post('telefone'),
    ];
    ir('acessos_criados');
}

/** Nova senha provisória para quem esqueceu (admin: qualquer conta; coordenação: alunos e professores da escola). */
function acao_nova_senha_provisoria(): void
{
    $u = exigir_usuario();
    $alvo = um('SELECT * FROM usuarios WHERE id = ?', [post('usuario_id')]);
    if (!$alvo) ir('painel');
    if (!eh_admin($u)) {
        $org = post('org_id');
        $m = um('SELECT papel FROM membros WHERE usuario_id = ? AND org_id = ?', [$alvo['id'], $org]);
        if (!isset(orgs_geridas($u)[$org]) || !$m || $alvo['admin'] || !in_array($m['papel'], ['ALUNO', 'PROFESSOR'], true)) ir('painel');
    }
    $senha = gerar_senha();
    q('UPDATE usuarios SET senha_hash = ?, senha_versao = senha_versao + 1 WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $alvo['id']]);
    q('INSERT IGNORE INTO senhas_provisorias (usuario_id, criado_em) VALUES (?,?)', [$alvo['id'], agora()]);
    limpar_falhas_login($alvo['email']);
    auditar($u['id'], post('org_id') ?: null, 'senha.provisoria', 'Usuario', $alvo['id']);
    $_SESSION['acessos_criados'] = [
        'pessoas' => [['nome' => $alvo['nome'], 'login' => login_de($alvo['email']), 'senha' => $senha, 'obs' => 'Senha nova: a anterior deixou de valer']],
        'contexto' => 'Nova senha provisória',
        'telefone' => (string) valor('SELECT COALESCE(telefone, responsavel_telefone) FROM dados_aluno WHERE usuario_id = ?', [$alvo['id']]),
    ];
    ir('acessos_criados');
}
