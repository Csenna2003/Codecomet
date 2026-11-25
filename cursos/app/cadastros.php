<?php
declare(strict_types=1);

const COMPETENCIAS = [
    'Arduino', 'ESP32', 'Robótica educacional (LEGO)', 'Eletrônica básica', 'Sensores e motores', 'Tinkercad', 'Wokwi',
    'Scratch', 'Python', 'C / C++', 'App Inventor', 'Internet das Coisas (IoT)', 'Impressão 3D', 'Programação em blocos',
    'Preparação para olimpíadas e torneios', 'Educação especial / inclusão',
];
const DIAS = ['seg' => 'Segunda', 'ter' => 'Terça', 'qua' => 'Quarta', 'qui' => 'Quinta', 'sex' => 'Sexta', 'sab' => 'Sábado', 'dom' => 'Domingo'];
const PERIODOS = ['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite'];
const MODALIDADES = ['PRESENCIAL' => 'Presencial', 'ONLINE' => 'On-line', 'AMBOS' => 'Presencial e on-line'];
const FORMAS_PAGAMENTO = ['PIX' => 'Pix', 'BOLETO' => 'Boleto', 'CARTAO' => 'Cartão', 'TRANSFERENCIA' => 'Transferência', 'DINHEIRO' => 'Dinheiro'];
const SITUACOES_FIN = ['EM_DIA' => 'Em dia', 'PENDENTE' => 'Pagamento pendente', 'INADIMPLENTE' => 'Inadimplente', 'ISENTO' => 'Isento / bolsista'];
const CLASSE_SITUACAO = ['EM_DIA' => 'ok', 'PENDENTE' => 'alerta', 'INADIMPLENTE' => 'erro', 'ISENTO' => ''];

// ---------- Utilidades ----------

function digitos(string $s): string
{
    return preg_replace('/\D/', '', $s);
}

function cpf_valido(string $cpf): bool
{
    $c = digitos($cpf);
    if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) return false;
    for ($t = 9; $t < 11; $t++) {
        $s = 0;
        for ($i = 0; $i < $t; $i++) $s += (int) $c[$i] * (($t + 1) - $i);
        if ((int) $c[$t] !== ((10 * $s) % 11) % 10) return false;
    }
    return true;
}

function cnpj_valido(string $cnpj): bool
{
    $c = digitos($cnpj);
    if (strlen($c) !== 14 || preg_match('/^(\d)\1{13}$/', $c)) return false;
    foreach ([12, 13] as $t) {
        $pesos = $t === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $s = 0;
        for ($i = 0; $i < $t; $i++) $s += (int) $c[$i] * $pesos[$i];
        $d = $s % 11 < 2 ? 0 : 11 - $s % 11;
        if ((int) $c[$t] !== $d) return false;
    }
    return true;
}

function formatar_doc(?string $d): string
{
    $d = digitos((string) $d);
    if (strlen($d) === 11) return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9);
    if (strlen($d) === 14) return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12);
    return $d;
}

/** "1.234,56", "150", "150,00" ou "150.5" -> centavos. Vazio -> null. Inválido -> false. */
function centavos_de(string $s)
{
    $s = trim(str_replace(['R$', ' '], '', $s));
    if ($s === '') return null;
    if (str_contains($s, ',')) $s = str_replace(['.', ','], ['', '.'], $s);
    if (!is_numeric($s) || (float) $s < 0) return false;
    return (int) round((float) $s * 100);
}

function valor_br(?int $centavos): string
{
    return $centavos === null ? '' : number_format($centavos / 100, 2, ',', '.');
}

/** Lê os campos financeiros comuns (aluno e escola) do formulário, já validados. */
function ler_financeiro(callable $falha): array
{
    $valor = centavos_de(post('fin_valor'));
    if ($valor === false) $falha('Valor inválido. Use, por exemplo, 250,00.');
    $dia = post('fin_dia_vencimento');
    if ($dia !== '' && (!ctype_digit($dia) || (int) $dia < 1 || (int) $dia > 31)) $falha('O dia de vencimento deve ser de 1 a 31.');
    $forma = post('fin_forma');
    $sit = post('fin_situacao');
    if ($forma !== '' && !isset(FORMAS_PAGAMENTO[$forma])) $falha('Forma de pagamento inválida.');
    if ($sit !== '' && !isset(SITUACOES_FIN[$sit])) $falha('Situação financeira inválida.');
    $email = strtolower(post('fin_email'));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $falha('E-mail financeiro inválido.');
    return [
        'fin_nome' => post('fin_nome') ?: null,
        'fin_email' => $email ?: null,
        'fin_telefone' => post('fin_telefone') ?: null,
        'fin_valor_centavos' => $valor,
        'fin_dia_vencimento' => $dia === '' ? null : (int) $dia,
        'fin_forma' => $forma ?: null,
        'fin_situacao' => $sit ?: null,
        'fin_observacoes' => post('fin_observacoes') ?: null,
    ];
}

/** INSERT ... ON DUPLICATE KEY UPDATE com nomes de coluna fixos no código. */
function gravar_linha(string $tabela, string $chave, string $id, array $campos): void
{
    $campos['atualizado_em'] = agora();
    $cols = array_keys($campos);
    foreach ($cols as $c) if (!preg_match('/^[a-z_]+$/', $c)) throw new InvalidArgumentException('coluna');
    $sql = "INSERT INTO $tabela ($chave, " . implode(', ', $cols) . ') VALUES (?' . str_repeat(', ?', count($cols)) . ') ON DUPLICATE KEY UPDATE '
        . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols));
    q($sql, array_merge([$id], array_values($campos)));
}

function eh_professor_cadastrado(array $u): bool
{
    return (bool) valor('SELECT 1 FROM professores WHERE usuario_id = ?', [$u['id']]);
}

/** Aceita o e-mail se não estiver em uso por outra conta. */
function email_livre(string $email, string $uid): bool
{
    return !valor('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?', [$email, $uid]);
}

/** Cria a conta (sem senha conhecida) e manda por e-mail o link para a pessoa criar a senha. */
function criar_conta_com_convite(string $nome, string $email, string $criadoPor, string $motivo): string
{
    $id = novo_id();
    q('INSERT INTO usuarios (id, nome, email, senha_hash, criado_em) VALUES (?,?,?,?,?)',
        [$id, $nome, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), agora()]);
    $link = criar_link_senha($id, $criadoPor);
    enviar_email($email, 'Sua conta na ' . nome_site(),
        'Olá, ' . explode(' ', $nome)[0] . "!\n\n$motivo\n\nPara entrar, crie uma senha pelo botão abaixo. O link vale por 24 horas; depois disso, use \"Esqueci minha senha\" na tela de entrada.",
        'Criar minha senha', $link);
    return $id;
}

/** Lê e valida os campos de endereço (CEP, endereço, bairro, cidade, UF). */
function ler_endereco(callable $falha): array
{
    $cep = digitos(post('cep'));
    if ($cep !== '' && strlen($cep) !== 8) $falha('CEP inválido (8 números).');
    $uf = strtoupper(post('uf'));
    if ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf)) $falha('UF inválida (use a sigla, ex.: SP).');
    return [
        'cep' => $cep ? substr($cep, 0, 5) . '-' . substr($cep, 5) : null,
        'endereco' => post('endereco') ?: null,
        'bairro' => post('bairro') ?: null,
        'cidade' => post('cidade') ?: null,
        'uf' => $uf ?: null,
    ];
}

function endereco_texto(?array $d): string
{
    if (!$d) return '';
    $partes = array_filter([$d['endereco'] ?? null, $d['bairro'] ?? null, trim(($d['cidade'] ?? '') . (!empty($d['uf']) ? '/' . $d['uf'] : '')), $d['cep'] ?? null]);
    return implode(' · ', $partes);
}

/** Campos de endereço para os formulários; o CEP preenche o resto (assets/app.js). */
function campos_endereco(?array $d, string $legenda = 'Endereço'): string
{
    $v = fn($k) => e($d[$k] ?? '');
    return '<fieldset class="pilha endereco"><legend>' . e($legenda) . '</legend><div class="campos">'
        . '<label>CEP<input name="cep" inputmode="numeric" maxlength="9" placeholder="00000-000" value="' . $v('cep') . '" data-cep></label>'
        . '<label style="grid-column: span 2">Rua, número e complemento<input name="endereco" value="' . $v('endereco') . '" data-end="logradouro"></label>'
        . '</div><div class="campos">'
        . '<label>Bairro<input name="bairro" value="' . $v('bairro') . '" data-end="bairro"></label>'
        . '<label>Cidade<input name="cidade" value="' . $v('cidade') . '" data-end="localidade"></label>'
        . '<label>UF<input name="uf" maxlength="2" value="' . $v('uf') . '" data-end="uf"></label>'
        . '</div></fieldset>';
}

// ---------- Ações: dados pessoais ----------

function acao_salvar_meus_dados(): void
{
    $u = exigir_usuario();
    $f = fn(string $m) => voltar('erro', $m, 'meus_dados');
    $nome = post('nome');
    $email = strtolower(post('email'));
    if (mb_strlen($nome) < 2) $f('Informe seu nome completo.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $f('E-mail inválido.');
    if (!email_livre($email, $u['id'])) $f('Este e-mail já é usado por outra conta.');
    $nasc = post('nascimento');
    if ($nasc !== '' && !DateTime::createFromFormat('Y-m-d', $nasc)) $f('Data de nascimento inválida.');
    $respEmail = strtolower(post('responsavel_email'));
    if ($respEmail !== '' && !filter_var($respEmail, FILTER_VALIDATE_EMAIL)) $f('E-mail do responsável inválido.');
    q('UPDATE usuarios SET nome = ?, email = ?, nascimento = ?, responsavel_nome = ?, responsavel_email = ? WHERE id = ?',
        [$nome, $email, $nasc ?: null, post('responsavel_nome') ?: null, $respEmail ?: null, $u['id']]);
    gravar_linha('dados_aluno', 'usuario_id', $u['id'], [
        'telefone' => post('telefone') ?: null,
        'responsavel_telefone' => post('responsavel_telefone') ?: null,
    ] + ler_endereco($f));
    voltar('ok', 'Dados atualizados.', 'meus_dados');
}

function acao_alterar_senha(): void
{
    $u = exigir_usuario();
    $nova = (string) ($_POST['nova'] ?? '');
    if (!password_verify((string) ($_POST['atual'] ?? ''), $u['senha_hash'])) voltar('erro', 'A senha atual não confere.', 'meus_dados');
    if (strlen($nova) < 8) voltar('erro', 'A nova senha precisa ter pelo menos 8 caracteres.', 'meus_dados');
    // Muda a versão (derruba as outras sessões) e mantém esta aberta.
    q('UPDATE usuarios SET senha_hash = ?, senha_versao = senha_versao + 1 WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
    entrar_como(um('SELECT * FROM usuarios WHERE id = ?', [$u['id']]));
    voltar('ok', 'Senha alterada. Outros aparelhos conectados precisarão entrar de novo.', 'meus_dados');
}

// ---------- Ações: administração de alunos e escolas ----------

function acao_salvar_aluno(): void
{
    $admin = exigir_admin();
    $id = post('usuario_id');
    $al = um('SELECT * FROM usuarios WHERE id = ?', [$id]);
    if (!$al) ir('admin_alunos');
    $f = fn(string $m) => voltar('erro', $m, 'editar_aluno', ['id' => $id]);
    $nome = post('nome');
    $email = strtolower(post('email'));
    if (mb_strlen($nome) < 2) $f('Informe o nome.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $f('E-mail inválido.');
    if (!email_livre($email, $id)) $f('Este e-mail já é usado por outra conta.');
    $nasc = post('nascimento');
    if ($nasc !== '' && !DateTime::createFromFormat('Y-m-d', $nasc)) $f('Data de nascimento inválida.');
    $respEmail = strtolower(post('responsavel_email'));
    if ($respEmail !== '' && !filter_var($respEmail, FILTER_VALIDATE_EMAIL)) $f('E-mail do responsável inválido.');
    foreach (['cpf' => 'CPF do aluno', 'responsavel_cpf' => 'CPF do responsável', 'fin_cpf' => 'CPF do responsável financeiro'] as $campo => $rot) {
        if (post($campo) !== '' && !cpf_valido(post($campo))) $f("$rot inválido.");
    }
    $fin = ler_financeiro($f);
    q('UPDATE usuarios SET nome = ?, email = ?, nascimento = ?, responsavel_nome = ?, responsavel_email = ? WHERE id = ?',
        [$nome, $email, $nasc ?: null, post('responsavel_nome') ?: null, $respEmail ?: null, $id]);
    gravar_linha('dados_aluno', 'usuario_id', $id, [
        'telefone' => post('telefone') ?: null,
        'cpf' => digitos(post('cpf')) ?: null,
        'responsavel_telefone' => post('responsavel_telefone') ?: null,
        'responsavel_cpf' => digitos(post('responsavel_cpf')) ?: null,
        'fin_cpf' => digitos(post('fin_cpf')) ?: null,
        'fin_endereco' => post('fin_endereco') ?: null,
    ] + ler_endereco($f) + $fin);
    auditar($admin['id'], null, 'aluno.editado', 'Usuario', $id);
    voltar('ok', 'Cadastro do aluno salvo.', 'editar_aluno', ['id' => $id]);
}

function acao_salvar_escola(): void
{
    $u = exigir_usuario();
    $org = post('org_id');
    $o = um('SELECT * FROM organizacoes WHERE id = ?', [$org]);
    if (!$o || (!eh_admin($u) && !isset(orgs_geridas($u)[$org]))) ir('painel');
    $f = fn(string $m) => voltar('erro', $m, 'editar_escola', ['id' => $org]);
    $nome = post('nome');
    if (mb_strlen($nome) < 2) $f('Informe o nome da escola.');
    $cnpj = digitos(post('documento'));
    if ($cnpj !== '' && !cnpj_valido($cnpj)) $f('CNPJ inválido.');
    foreach (['email' => 'E-mail da escola', 'contato_email' => 'E-mail do contato'] as $c => $rot) {
        if (post($c) !== '' && !filter_var(post($c), FILTER_VALIDATE_EMAIL)) $f("$rot inválido.");
    }
    $campos = [
        'razao_social' => post('razao_social') ?: null,
        'telefone' => post('telefone') ?: null,
        'email' => strtolower(post('email')) ?: null,
        'contato_nome' => post('contato_nome') ?: null,
        'contato_email' => strtolower(post('contato_email')) ?: null,
        'contato_telefone' => post('contato_telefone') ?: null,
    ] + ler_endereco($f);
    // Dados financeiros e a situação da escola só o administrador da plataforma altera.
    if (eh_admin($u)) {
        $campos += ler_financeiro($f);
        foreach (['fin_inicio', 'fin_fim'] as $c) {
            if (post($c) !== '' && !DateTime::createFromFormat('Y-m-d', post($c))) $f('Data do contrato inválida.');
            $campos[$c] = post($c) ?: null;
        }
        q('UPDATE organizacoes SET nome = ?, documento = ?, ativa = ? WHERE id = ?', [$nome, $cnpj ?: null, ($_POST['ativa'] ?? '') === 'on' ? 1 : 0, $org]);
    } else {
        q('UPDATE organizacoes SET nome = ?, documento = ? WHERE id = ?', [$nome, $cnpj ?: null, $org]);
    }
    gravar_linha('dados_escola', 'org_id', $org, $campos);
    auditar($u['id'], $org, 'escola.editada', 'Organizacao', $org);
    voltar('ok', 'Cadastro da escola salvo.', 'editar_escola', ['id' => $org]);
}

// ---------- Ações: professores ----------

function acao_cadastrar_professor(): void
{
    $admin = exigir_admin();
    $email = strtolower(post('email'));
    $nome = post('nome');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) voltar('erro', 'Informe o e-mail do professor.', 'professores');
    $p = um('SELECT id FROM usuarios WHERE email = ?', [$email]);
    $msg = 'Professor cadastrado.';
    if (!$p) {
        if (mb_strlen($nome) < 2) voltar('erro', 'Este e-mail ainda não tem conta. Informe o nome do professor para criarmos a conta.', 'professores');
        $p = ['id' => criar_conta_com_convite($nome, $email, $admin['id'], 'Você foi cadastrado(a) como professor(a) na plataforma ' . nome_site() . '. Lá você preenche suas competências e disponibilidade e registra as aulas.')];
        $msg = 'Professor cadastrado. Enviamos um e-mail para ele criar a senha.';
    }
    q('INSERT IGNORE INTO professores (usuario_id, criado_em, atualizado_em) VALUES (?,?,?)', [$p['id'], agora(), agora()]);
    q('UPDATE professores SET ativo = 1 WHERE usuario_id = ?', [$p['id']]);
    auditar($admin['id'], null, 'professor.cadastrado', 'Usuario', $p['id']);
    voltar('ok', $msg, 'perfil_professor', ['id' => $p['id']]);
}

function acao_salvar_professor(): void
{
    $u = exigir_usuario();
    $id = post('usuario_id');
    if ($id !== $u['id'] && !eh_admin($u)) ir('painel');
    if (!valor('SELECT 1 FROM professores WHERE usuario_id = ?', [$id])) ir('painel');
    $f = fn(string $m) => voltar('erro', $m, 'perfil_professor', ['id' => $id]);
    $comp = array_values(array_intersect(COMPETENCIAS, (array) ($_POST['competencias'] ?? [])));
    $disp = [];
    foreach (DIAS as $d => $_) foreach (PERIODOS as $p => $_2) if (isset($_POST['disp'][$d][$p])) $disp[] = "$d-$p";
    $mod = post('modalidade');
    if ($mod !== '' && !isset(MODALIDADES[$mod])) $f('Modalidade inválida.');
    if (post('cpf') !== '' && !cpf_valido(post('cpf'))) $f('CPF inválido.');
    $campos = [
        'telefone' => post('telefone') ?: null,
        'regioes' => post('regioes') ?: null,
        'bio' => post('bio') ?: null,
        'formacao' => post('formacao') ?: null,
        'competencias' => json_encode($comp, JSON_UNESCAPED_UNICODE),
        'outras_competencias' => post('outras_competencias') ?: null,
        'disponibilidade' => json_encode($disp),
        'disponibilidade_obs' => post('disponibilidade_obs') ?: null,
        'modalidade' => $mod ?: null,
        'cpf' => digitos(post('cpf')) ?: null,
        'chave_pix' => post('chave_pix') ?: null,
    ] + ler_endereco($f);
    // Valor da hora-aula e situação: só o administrador.
    if (eh_admin($u)) {
        $v = centavos_de(post('valor_hora'));
        if ($v === false) $f('Valor da hora-aula inválido. Use, por exemplo, 80,00.');
        $campos['valor_hora_centavos'] = $v;
        $campos['ativo'] = ($_POST['ativo'] ?? '') === 'on' ? 1 : 0;
    }
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($campos)));
    q("UPDATE professores SET $sets, atualizado_em = ? WHERE usuario_id = ?", array_merge(array_values($campos), [agora(), $id]));
    voltar('ok', 'Perfil do professor salvo.', 'perfil_professor', ['id' => $id]);
}

// ---------- Pedaços de formulário ----------

function opcoes(array $mapa, ?string $sel, string $vazio = '—'): string
{
    $h = '<option value="">' . e($vazio) . '</option>';
    foreach ($mapa as $k => $v) $h .= '<option value="' . e($k) . '"' . ((string) $sel === (string) $k ? ' selected' : '') . '>' . e($v) . '</option>';
    return $h;
}

/** Campos financeiros comuns a aluno e escola. */
function campos_financeiro(?array $d, string $rotuloValor): string
{
    $v = fn($k) => e($d[$k] ?? '');
    return '<div class="campos">'
        . '<label>' . e($rotuloValor) . ' (R$)<input name="fin_valor" inputmode="decimal" placeholder="0,00" value="' . e(valor_br(isset($d['fin_valor_centavos']) ? (int) $d['fin_valor_centavos'] : null)) . '"></label>'
        . '<label>Dia de vencimento<input name="fin_dia_vencimento" type="number" min="1" max="31" value="' . $v('fin_dia_vencimento') . '"></label>'
        . '<label>Forma de pagamento<select name="fin_forma">' . opcoes(FORMAS_PAGAMENTO, $d['fin_forma'] ?? null) . '</select></label>'
        . '<label>Situação<select name="fin_situacao">' . opcoes(SITUACOES_FIN, $d['fin_situacao'] ?? null) . '</select></label>'
        . '</div><label>Observações financeiras<textarea name="fin_observacoes" rows="2">' . $v('fin_observacoes') . '</textarea></label>';
}

function etiqueta_situacao(?string $s): string
{
    return $s ? '<span class="etiqueta ' . (CLASSE_SITUACAO[$s] ?? '') . '">' . e(SITUACOES_FIN[$s] ?? $s) . '</span>' : '<span class="suave pequeno">—</span>';
}

// ---------- Matrículas feitas pela administração ----------

const ROTULO_ORIGEM = ['COMPRA' => 'Compra', 'LICENCA_ESCOLA' => 'Pela escola', 'BOLSA' => 'Bolsa', 'MANUAL' => 'Matrícula pela administração'];

/** Matricula no curso (ou reativa a matrícula). Aceita o aluno pelo id ou pelo e-mail. */
function acao_matricular_manual(): void
{
    $admin = exigir_admin();
    $volta = post('voltar') === 'curso' ? ['admin_curso', ['id' => post('curso_id')]] : ['editar_aluno', ['id' => post('usuario_id')]];
    $f = fn(string $m) => voltar('erro', $m, $volta[0], $volta[1]);
    $al = post('usuario_id') !== ''
        ? um('SELECT id, nome, email FROM usuarios WHERE id = ?', [post('usuario_id')])
        : um('SELECT id, nome, email FROM usuarios WHERE email = ?', [strtolower(post('email'))]);
    if (!$al) $f('Aluno não encontrado. Ele precisa ter uma conta (o aluno cria em "Criar conta", ou você cadastra como aluno particular).');
    $curso = um('SELECT id, titulo, slug, status FROM cursos WHERE id = ?', [post('curso_id')]);
    if (!$curso) $f('Escolha o curso.');
    if ($curso['status'] !== 'PUBLICADO') $f('Publique o curso antes de matricular alunos (em Administração → Editar conteúdo → Status).');
    $origem = post('origem') === 'BOLSA' ? 'BOLSA' : 'MANUAL';
    $fim = null;
    if (post('fim') !== '') {
        $d = DateTime::createFromFormat('Y-m-d', post('fim'));
        if (!$d || $d < new DateTime('today')) $f('A data final precisa ser hoje ou depois.');
        $fim = $d->format('Y-m-d') . ' 23:59:59';
    }
    $atual = acesso_ativo($al['id'], $curso['id']);
    if ($atual && $atual['origem'] !== 'LICENCA_ESCOLA' && $atual['fim'] === $fim) $f("{$al['nome']} já está matriculado(a) neste curso.");
    $existe = um('SELECT id FROM acessos WHERE usuario_id = ? AND curso_id = ?', [$al['id'], $curso['id']]);
    if ($existe) {
        q("UPDATE acessos SET origem = ?, status = 'ATIVO', org_id = NULL, licenca_id = NULL, pedido_id = NULL, inicio = ?, fim = ? WHERE id = ?",
            [$origem, agora(), $fim, $existe['id']]);
    } else {
        q("INSERT INTO acessos (id, usuario_id, curso_id, origem, status, inicio, fim) VALUES (?,?,?,?, 'ATIVO', ?, ?)",
            [novo_id(), $al['id'], $curso['id'], $origem, agora(), $fim]);
    }
    auditar($admin['id'], null, 'matricula.manual', 'Usuario', $al['id'], ['curso' => $curso['id'], 'origem' => $origem]);
    enviar_email($al['email'], 'Você foi matriculado(a): ' . $curso['titulo'],
        'Olá, ' . explode(' ', $al['nome'])[0] . "!\n\nSua matrícula no curso {$curso['titulo']} está ativa" . ($fim ? ' até ' . data_br($fim) : '') . '. Bons estudos!',
        'Começar o curso', url_absoluta('curso', ['slug' => $curso['slug']]));
    voltar('ok', "{$al['nome']} matriculado(a) em {$curso['titulo']}.", $volta[0], $volta[1]);
}

function acao_cancelar_matricula(): void
{
    $admin = exigir_admin();
    $a = um('SELECT * FROM acessos WHERE id = ?', [post('acesso_id')]);
    if (!$a) ir('admin_alunos');
    q("UPDATE acessos SET status = 'REVOGADO' WHERE id = ?", [$a['id']]);
    auditar($admin['id'], $a['org_id'], 'matricula.cancelada', 'Usuario', $a['usuario_id'], ['curso' => $a['curso_id']]);
    $msg = 'Matrícula cancelada. O histórico do aluno foi mantido.';
    if ($a['origem'] === 'LICENCA_ESCOLA') $msg .= ' Atenção: a vaga veio da escola; para não voltar, tire o aluno da escola.';
    voltar('ok', $msg, 'editar_aluno', ['id' => $a['usuario_id']]);
}

/** Coloca o aluno numa escola (e turma) sem código; recebe o curso se houver vaga na licença. */
function acao_adicionar_escola(): void
{
    $admin = exigir_admin();
    $uid = post('usuario_id');
    $org = post('org_id');
    $f = fn(string $m) => voltar('erro', $m, 'editar_aluno', ['id' => $uid]);
    if (!valor('SELECT 1 FROM usuarios WHERE id = ?', [$uid]) || !valor('SELECT 1 FROM organizacoes WHERE id = ?', [$org])) $f('Escolha a escola.');
    $turma = post('turma_id') ?: null;
    if ($turma && !valor('SELECT 1 FROM turmas WHERE id = ? AND org_id = ?', [$turma, $org])) $f('A turma escolhida não é desta escola.');
    q("INSERT IGNORE INTO membros (id, usuario_id, org_id, papel, criado_em) VALUES (?,?,?, 'ALUNO', ?)", [novo_id(), $uid, $org, agora()]);
    if ($turma) q('INSERT IGNORE INTO turma_alunos (turma_id, usuario_id, entrou_em) VALUES (?,?,?)', [$turma, $uid, agora()]);
    aplicar_licencas($org);
    auditar($admin['id'], $org, 'membro.adicionado', 'Usuario', $uid);
    voltar('ok', 'Aluno colocado na escola. Se a escola tem licença com vaga, os cursos dela já foram liberados.', 'editar_aluno', ['id' => $uid]);
}

function acao_remover_escola_aluno(): void
{
    $admin = exigir_admin();
    $uid = post('usuario_id');
    $org = post('org_id');
    remover_da_escola($uid, $org);
    auditar($admin['id'], $org, 'membro.removido', 'Usuario', $uid);
    voltar('ok', 'Aluno retirado da escola. Os cursos que vinham dela foram encerrados; histórico e certificados mantidos.', 'editar_aluno', ['id' => $uid]);
}
