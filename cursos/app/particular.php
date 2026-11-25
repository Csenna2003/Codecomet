<?php
declare(strict_types=1);

// ---------- Regras ----------

/** Quem pode ver o acompanhamento de um aluno. */
function pode_ver_aluno(array $u, string $alunoId): bool
{
    if ($u['id'] === $alunoId || eh_admin($u)) return true;
    $geridas = array_keys(orgs_geridas($u));
    if ($geridas && valor('SELECT 1 FROM membros WHERE usuario_id = ? AND org_id IN (' . marcas($geridas) . ')', array_merge([$alunoId], $geridas))) return true;
    if (valor('SELECT 1 FROM turma_alunos ta JOIN turmas t ON t.id = ta.turma_id WHERE ta.usuario_id = ? AND t.professor_id = ?', [$alunoId, $u['id']])) return true;
    return (bool) valor('SELECT 1 FROM particulares WHERE aluno_id = ? AND professor_id = ?', [$alunoId, $u['id']]);
}

function da_aulas_particulares(array $u): bool
{
    return eh_admin($u) || (bool) valor('SELECT 1 FROM particulares WHERE professor_id = ? AND ativo = 1', [$u['id']]);
}

/** Aula particular que o usuário pode alterar: a do próprio professor, ou qualquer uma para o admin. */
function aula_particular_do_professor(array $u, string $id): ?array
{
    $a = um('SELECT * FROM aulas_particulares WHERE id = ?', [$id]);
    if (!$a || ($a['professor_id'] !== $u['id'] && !eh_admin($u))) return null;
    return $a;
}

function hora(?string $dt): string
{
    return $dt ? date('H:i', strtotime($dt)) : '—';
}

function duracao(?string $ini, ?string $fim): string
{
    if (!$ini || !$fim) return '—';
    $min = max(0, (int) round((strtotime($fim) - strtotime($ini)) / 60));
    return intdiv($min, 60) . 'h' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT);
}

const ROTULO_PARTICULAR = [
    'EM_ANDAMENTO' => 'Em andamento',
    'AGUARDANDO' => 'Aguardando o responsável',
    'CONFIRMADA' => 'Confirmada pelo responsável',
    'CONTESTADA' => 'Não confirmada pelo responsável',
];
const CLASSE_PARTICULAR = ['EM_ANDAMENTO' => '', 'AGUARDANDO' => 'alerta', 'CONFIRMADA' => 'ok', 'CONTESTADA' => 'erro'];

/** Novo link de confirmação (invalida o anterior). Só o hash fica no banco. */
function novo_link_confirmacao(string $aulaId): string
{
    $token = bin2hex(random_bytes(32));
    q('UPDATE aulas_particulares SET token_hash = ? WHERE id = ?', [hash('sha256', $token), $aulaId]);
    return url_absoluta('confirmar', ['t' => $token]);
}

/** Telefone em formato internacional para o link do WhatsApp (Brasil por padrão). */
function telefone_whatsapp(string $tel): string
{
    $d = preg_replace('/\D/', '', $tel);
    if (strlen($d) === 10 || strlen($d) === 11) $d = '55' . $d;
    return $d;
}

function mensagem_whatsapp(array $a, string $link): string
{
    return "Olá, {$a['responsavel_nome']}! A aula particular de {$a['aluno_nome']} com {$a['professor_nome']} em "
        . data_br($a['chegada']) . ' foi registrada (chegada ' . hora($a['chegada']) . ', saída ' . hora($a['saida']) . ").\n"
        . "Veja o conteúdo da aula e confirme por este link: $link";
}

function carregar_aula_particular(string $id): ?array
{
    return um(
        'SELECT ap.*, p.responsavel_nome, p.responsavel_telefone, al.nome AS aluno_nome, pr.nome AS professor_nome
         FROM aulas_particulares ap JOIN particulares p ON p.id = ap.particular_id
         JOIN usuarios al ON al.id = ap.aluno_id JOIN usuarios pr ON pr.id = ap.professor_id WHERE ap.id = ?',
        [$id]
    );
}

// ---------- Ações: administração ----------

function acao_criar_vinculo(): void
{
    $admin = exigir_admin();
    $falha = fn(string $m) => voltar('erro', $m, 'admin_particulares');
    $emailAluno = strtolower(post('aluno_email'));
    $nomeAluno = post('aluno_nome');
    $prof = um('SELECT id FROM usuarios WHERE email = ?', [strtolower(post('professor_email'))]);
    $respNome = post('responsavel_nome');
    $respTel = post('responsavel_telefone');
    if (!$prof) $falha('Professor não encontrado. Ele precisa ter uma conta na plataforma (Criar conta) com esse e-mail.');
    if (!filter_var($emailAluno, FILTER_VALIDATE_EMAIL)) $falha('Informe o e-mail do aluno (ou do responsável).');
    if (mb_strlen($respNome) < 2 || strlen(preg_replace('/\D/', '', $respTel)) < 10) $falha('Informe o nome e o telefone (com DDD) do responsável.');

    $aluno = um('SELECT id FROM usuarios WHERE email = ?', [$emailAluno]);
    $msgConta = '';
    if (!$aluno) {
        if (mb_strlen($nomeAluno) < 2) $falha('Este e-mail ainda não tem conta. Informe o nome do aluno para criarmos a conta.');
        $id = novo_id();
        // Conta criada sem senha conhecida: o aluno define a dele pelo link de nova senha.
        q('INSERT INTO usuarios (id, nome, email, senha_hash, responsavel_nome, criado_em) VALUES (?,?,?,?,?,?)',
            [$id, $nomeAluno, $emailAluno, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $respNome, agora()]);
        $aluno = ['id' => $id];
        $msgConta = ' Conta do aluno criada. Para ele acessar a plataforma, gere um link de nova senha em Administração.';
    }
    if ($aluno['id'] === $prof['id']) $falha('O aluno e o professor precisam ser pessoas diferentes.');
    $existe = um('SELECT id FROM particulares WHERE aluno_id = ? AND professor_id = ?', [$aluno['id'], $prof['id']]);
    if ($existe) {
        q('UPDATE particulares SET responsavel_nome = ?, responsavel_telefone = ?, ativo = 1 WHERE id = ?', [$respNome, $respTel, $existe['id']]);
    } else {
        q('INSERT INTO particulares (id, aluno_id, professor_id, responsavel_nome, responsavel_telefone, criado_em) VALUES (?,?,?,?,?,?)',
            [novo_id(), $aluno['id'], $prof['id'], $respNome, $respTel, agora()]);
    }
    auditar($admin['id'], null, 'particular.vinculo', 'Usuario', $aluno['id'], ['professor' => $prof['id']]);
    voltar('ok', 'Aluno particular cadastrado.' . $msgConta, 'admin_particulares');
}

function acao_alternar_vinculo(): void
{
    exigir_admin();
    q('UPDATE particulares SET ativo = 1 - ativo WHERE id = ?', [post('vinculo_id')]);
    voltar('ok', 'Cadastro atualizado.', 'admin_particulares');
}

// ---------- Ações: professor ----------

function acao_registrar_chegada(): void
{
    $u = exigir_usuario();
    $v = um('SELECT * FROM particulares WHERE id = ? AND ativo = 1', [post('vinculo_id')]);
    if (!$v || ($v['professor_id'] !== $u['id'] && !eh_admin($u))) ir('particulares');
    if (valor("SELECT 1 FROM aulas_particulares WHERE particular_id = ? AND status = 'EM_ANDAMENTO'", [$v['id']])) {
        voltar('erro', 'Já existe uma aula em andamento com este aluno. Finalize-a antes.', 'particulares');
    }
    // Por padrão o horário é o do servidor, no momento do clique. Horário digitado fica marcado como "informado".
    $manual = 0;
    $chegada = agora();
    if (post('data') !== '' || post('horario') !== '') {
        $d = DateTime::createFromFormat('Y-m-d H:i', post('data') . ' ' . post('horario'));
        if (!$d || $d > new DateTime('+5 minutes')) voltar('erro', 'Informe uma data e um horário de chegada válidos (não no futuro).', 'particulares');
        $chegada = $d->format('Y-m-d H:i:00');
        $manual = 1;
    }
    $id = novo_id();
    q('INSERT INTO aulas_particulares (id, particular_id, aluno_id, professor_id, chegada, chegada_manual, criado_em) VALUES (?,?,?,?,?,?,?)',
        [$id, $v['id'], $v['aluno_id'], $v['professor_id'], $chegada, $manual, agora()]);
    auditar($u['id'], null, 'particular.chegada', 'AulaParticular', $id, ['manual' => $manual]);
    voltar('ok', 'Chegada registrada às ' . hora($chegada) . '. Ao terminar a aula, preencha o conteúdo e finalize.', 'particulares');
}

function acao_finalizar_aula_particular(): void
{
    $u = exigir_usuario();
    $a = aula_particular_do_professor($u, post('aula_id'));
    if (!$a || $a['status'] !== 'EM_ANDAMENTO') voltar('erro', 'Esta aula não está mais em andamento.', 'particulares');
    $conteudo = post('conteudo');
    if (mb_strlen($conteudo) < 10) voltar('erro', 'Descreva o conteúdo apresentado na aula (pelo menos uma frase).', 'particulares');
    if (mb_strlen($conteudo) > 5000 || mb_strlen(post('observacoes')) > 2000) voltar('erro', 'Texto muito longo.', 'particulares');
    q("UPDATE aulas_particulares SET saida = ?, conteudo = ?, observacoes = ?, status = 'AGUARDANDO' WHERE id = ? AND status = 'EM_ANDAMENTO'",
        [agora(), $conteudo, post('observacoes') ?: null, $a['id']]);
    $_SESSION['link_confirmacao'][$a['id']] = novo_link_confirmacao($a['id']);
    auditar($u['id'], null, 'particular.finalizada', 'AulaParticular', $a['id']);
    voltar('ok', 'Aula finalizada. Agora envie o link de confirmação para o responsável.', 'particular_aula', ['id' => $a['id']]);
}

function acao_novo_link_particular(): void
{
    $u = exigir_usuario();
    $a = aula_particular_do_professor($u, post('aula_id'));
    if (!$a || $a['status'] !== 'AGUARDANDO') voltar('erro', 'Esta aula não está aguardando confirmação.', 'particulares');
    $_SESSION['link_confirmacao'][$a['id']] = novo_link_confirmacao($a['id']);
    voltar('ok', 'Novo link gerado. O link anterior deixou de funcionar.', 'particular_aula', ['id' => $a['id']]);
}

function acao_cancelar_aula_particular(): void
{
    $u = exigir_usuario();
    $a = aula_particular_do_professor($u, post('aula_id'));
    if (!$a || $a['status'] !== 'EM_ANDAMENTO') voltar('erro', 'Só é possível cancelar uma aula ainda em andamento.', 'particulares');
    q("DELETE FROM aulas_particulares WHERE id = ? AND status = 'EM_ANDAMENTO'", [$a['id']]);
    auditar($u['id'], null, 'particular.cancelada', 'AulaParticular', $a['id'], ['chegada' => $a['chegada']]);
    voltar('ok', 'Registro de chegada cancelado.', 'particulares');
}

// ---------- Ação pública: resposta do responsável ----------

function acao_responder_confirmacao(): void
{
    $token = post('t');
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) ir();
    $decisao = post('decisao') === 'CONFIRMADA' ? 'CONFIRMADA' : 'CONTESTADA';
    $nome = post('nome');
    $coment = post('comentario');
    if (mb_strlen($nome) < 2) voltar('erro', 'Informe o seu nome.', 'confirmar', ['t' => $token]);
    if ($decisao === 'CONTESTADA' && mb_strlen($coment) < 5) voltar('erro', 'Conte o que não confere, para a escola verificar.', 'confirmar', ['t' => $token]);
    $ok = q(
        "UPDATE aulas_particulares SET status = ?, resposta_nome = ?, resposta_comentario = ?, resposta_em = ?, resposta_ip = ?
         WHERE token_hash = ? AND status = 'AGUARDANDO'",
        [$decisao, mb_substr($nome, 0, 150), $coment ?: null, agora(), $_SERVER['REMOTE_ADDR'] ?? null, hash('sha256', $token)]
    )->rowCount();
    if (!$ok) voltar('erro', 'Esta aula já foi respondida ou o link não vale mais.', 'confirmar', ['t' => $token]);
    voltar('ok', $decisao === 'CONFIRMADA' ? 'Obrigado! A aula foi confirmada.' : 'Obrigado. Registramos que a aula não confere; a escola vai verificar.', 'confirmar', ['t' => $token]);
}
