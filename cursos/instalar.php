<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80000 || !extension_loaded('pdo_mysql')) {
    header('Content-Type: text/html; charset=utf-8');
    exit('<p style="font-family:sans-serif;max-width:40rem;margin:2rem auto">A plataforma precisa do PHP 8.0 ou mais novo, com a extensão pdo_mysql. '
        . 'Esta hospedagem está com o PHP ' . htmlspecialchars(PHP_VERSION) . '. No cPanel, abra <strong>MultiPHP Manager</strong> '
        . '(ou <strong>Selecionar versão do PHP</strong>), escolha a versão 8.1 ou mais nova para o seu domínio e recarregue esta página.</p>');
}

require __DIR__ . '/app/nucleo.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/esquema.php';
require __DIR__ . '/app/exemplo.php';

header('X-Frame-Options: DENY');
iniciar_sessao();

$arquivoConfig = __DIR__ . '/config.php';
$erro = '';
$conteudoManual = '';

function tela(string $titulo, string $corpo): void
{
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Instalação</title><link rel="stylesheet" href="assets/estilo.css?v=2"><link rel="icon" href="assets/icone-32.png"></head><body><main class="estreito">'
        . '<p><img src="assets/logo.png" alt="" width="180" height="75"></p>'
        . '<h1>' . e($titulo) . '</h1>' . $corpo . '</main></body></html>';
    exit;
}

// ---------- Passo 1: dados do banco e do Pix ----------
if (!is_file($arquivoConfig)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verificar_csrf();
        $cfg = [
            'nome_site' => post('nome_site') ?: 'Cursos',
            // O banco da hospedagem compartilhada fica sempre no mesmo servidor.
            'db' => ['host' => 'localhost', 'nome' => post('db_nome'), 'usuario' => post('db_usuario'), 'senha' => (string) ($_POST['db_senha'] ?? '')],
            'pix' => ['chave' => post('pix_chave'), 'nome' => post('pix_nome'), 'cidade' => post('pix_cidade')],
        ];
        try {
            conectar_banco('localhost', $cfg['db']['nome'], $cfg['db']['usuario'], $cfg['db']['senha']);
            $php = "<?php\n// Gerado pelo instalar.php. Contém a senha do banco: não compartilhe.\nreturn " . var_export($cfg, true) . ";\n";
            if (@file_put_contents($arquivoConfig, $php) === false) {
                $conteudoManual = $php;
            } else {
                header('Location: instalar.php');
                exit;
            }
        } catch (PDOException $ex) {
            $erro = 'Não consegui conectar ao banco com esses dados. Confira o nome do banco, o usuário, a senha e se o usuário foi adicionado ao banco com todos os privilégios.';
        }
    }
    if ($conteudoManual) {
        tela('Quase lá', '<p class="aviso alerta">A hospedagem não deixou gravar o arquivo de configuração. Crie um arquivo chamado <strong>config.php</strong> nesta pasta (pelo Gerenciador de Arquivos do cPanel) com o conteúdo abaixo e depois recarregue esta página.</p>'
            . '<textarea class="codigo" rows="16" readonly>' . e($conteudoManual) . '</textarea>');
    }
    tela('Instalação — passo 1 de 2', ($erro ? '<p class="aviso erro">' . e($erro) . '</p>' : '')
        . '<p class="suave">Antes, crie no cPanel um banco MySQL e um usuário com todos os privilégios nele (seção “Bancos de dados MySQL”).</p>'
        . '<form method="post" class="pilha cartao">' . csrf_campo()
        . '<label>Nome da plataforma<input name="nome_site" value="' . e(post('nome_site') ?: 'Adoro Robótica') . '" required></label>'
        . '<fieldset class="pilha"><legend>Banco de dados (do cPanel)</legend>'
        . '<label>Nome do banco<input name="db_nome" value="' . e(post('db_nome')) . '" placeholder="conta_cursos" required></label>'
        . '<label>Usuário do banco<input name="db_usuario" value="' . e(post('db_usuario')) . '" placeholder="conta_cursos" required></label>'
        . '<label>Senha do usuário do banco<input name="db_senha" type="password" required></label></fieldset>'
        . '<fieldset class="pilha"><legend>Pix para receber as vendas (pode preencher depois)</legend>'
        . '<label>Chave Pix<input name="pix_chave" value="' . e(post('pix_chave')) . '" placeholder="CPF/CNPJ só números, +55DDDNÚMERO, e-mail ou chave aleatória"></label>'
        . '<label>Nome do titular da conta<input name="pix_nome" value="' . e(post('pix_nome')) . '"></label>'
        . '<label>Cidade do titular<input name="pix_cidade" value="' . e(post('pix_cidade')) . '"></label></fieldset>'
        . '<button class="btn">Continuar</button></form>');
}

// ---------- Passo 2: tabelas e conta de administrador ----------
$cfg = config();
try {
    foreach (esquema_sql() as $sql) db()->exec($sql);
} catch (PDOException $ex) {
    tela('Erro no banco de dados', '<p class="aviso erro">Não consegui criar as tabelas. Confira se o usuário do banco tem todos os privilégios. Detalhe: ' . e($ex->getMessage()) . '</p>');
}

if (valor('SELECT 1 FROM usuarios WHERE admin = 1 LIMIT 1')) {
    tela('Plataforma instalada', '<p class="aviso ok">Está tudo pronto.</p><p><a class="btn" href="index.php">Abrir a plataforma</a></p>'
        . '<p class="suave pequeno">Por segurança, este instalador não faz mais nada depois que o administrador existe. Você pode apagar o arquivo instalar.php.</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $nome = post('nome');
    $email = strtolower(post('email'));
    $senha = (string) ($_POST['senha'] ?? '');
    // Só quem conhece a senha do banco cria o primeiro administrador.
    if (!hash_equals((string) $cfg['db']['senha'], (string) ($_POST['db_senha'] ?? ''))) $erro = 'A senha do banco de dados não confere.';
    elseif (mb_strlen($nome) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Informe nome e e-mail válidos.';
    elseif (strlen($senha) < 8) $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    else {
        $id = novo_id();
        q('INSERT INTO usuarios (id, nome, email, senha_hash, admin, criado_em) VALUES (?,?,?,?,1,?)', [$id, $nome, $email, password_hash($senha, PASSWORD_DEFAULT), agora()]);
        if (($_POST['exemplo'] ?? '') === 'on') criar_curso_exemplo();
        entrar_como(um('SELECT * FROM usuarios WHERE id = ?', [$id]));
        flash('ok', 'Plataforma instalada! Você é o administrador.');
        header('Location: index.php?r=admin');
        exit;
    }
}

tela('Instalação — passo 2 de 2', ($erro ? '<p class="aviso erro">' . e($erro) . '</p>' : '<p class="aviso ok">Banco de dados conectado e tabelas criadas.</p>')
    . '<p>Crie a sua conta de administrador.</p>'
    . '<form method="post" class="pilha cartao">' . csrf_campo()
    . '<label>Seu nome<input name="nome" value="' . e(post('nome')) . '" required></label>'
    . '<label>Seu e-mail<input name="email" type="email" value="' . e(post('email')) . '" required></label>'
    . '<label>Senha (mínimo de 8 caracteres)<input name="senha" type="password" minlength="8" required></label>'
    . '<label>Senha do banco de dados (a mesma do passo 1, para confirmar que é você)<input name="db_senha" type="password" required></label>'
    . '<label class="marcar"><input type="checkbox" name="exemplo" checked> <span>Criar um curso de exemplo (pode apagar ou arquivar depois)</span></label>'
    . '<button class="btn">Concluir instalação</button></form>');
