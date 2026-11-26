<?php
declare(strict_types=1);

require __DIR__ . '/app/nucleo.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/curso.php';
require __DIR__ . '/app/escola.php';
require __DIR__ . '/app/pix.php';
require __DIR__ . '/app/senha.php';
require __DIR__ . '/app/simulador.php';
require __DIR__ . '/app/layout.php';
require __DIR__ . '/app/acoes.php';
require __DIR__ . '/app/acoes_gestao.php';
require __DIR__ . '/app/particular.php';
require __DIR__ . '/app/email.php';
require __DIR__ . '/app/cadastros.php';
require __DIR__ . '/app/conteudo.php';
require __DIR__ . '/app/acessos.php';
require __DIR__ . '/app/edicao.php';
require __DIR__ . '/app/arquivos.php';
require __DIR__ . '/app/relatorio.php';
require __DIR__ . '/app/esquema.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Guarda a página na memória até o fim: assim um redirecionamento ainda funciona
// mesmo que um erro aconteça depois de o cabeçalho começar a ser montado.
ob_start();

config();
iniciar_sessao();

/**
 * Após enviar uma versão nova dos arquivos, cria as tabelas que faltarem.
 * Feito uma vez por sessão; os comandos só criam o que não existe.
 */
const VERSAO_ESQUEMA = 7;
if (($_SESSION['esquema'] ?? 0) !== VERSAO_ESQUEMA) {
    if (!valor("SHOW TABLES LIKE 'usuarios'")) {
        header('Location: instalar.php');
        exit;
    }
    foreach (esquema_sql() as $sql) db()->exec($sql);
    $_SESSION['esquema'] = VERSAO_ESQUEMA;
}

const ROTAS = [
    'inicio', 'entrar', 'cadastro', 'painel', 'curso', 'aula', 'pedido', 'certificado', 'redefinir',
    'professor', 'entrega', 'escola', 'admin', 'admin_curso', 'admin_pedidos',
    'aluno', 'particulares', 'particular_aula', 'confirmar', 'admin_particulares', 'esqueci',
    'acessos_criados', 'editar_aula', 'meus_dados', 'editar_aluno', 'editar_escola', 'admin_alunos', 'professores', 'perfil_professor',
    'arquivo', 'relatorio',
];

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        // Envio maior que o limite da hospedagem: o PHP descarta tudo, inclusive o formulário.
        if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            flash('erro', 'Os arquivos passaram do limite de envio (' . tamanho_legivel(limite_arquivo()) . '). Envie menos fotos ou arquivos menores.');
            $volta = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            header('Location: ' . (str_starts_with($volta, url_base()) ? $volta : url('painel')));
            exit;
        }
        verificar_csrf();
        executar_acao(post('acao'));
        ir();
    }
    $rota = get('r') ?: 'inicio';
    if (!in_array($rota, ROTAS, true)) nao_encontrado();
    require __DIR__ . "/app/paginas/$rota.php";
} catch (PDOException $e) {
    if ($e->getCode() === '42S02') {
        // Sem a tabela de usuários a plataforma nunca foi instalada: vai para o instalador.
        if (!valor("SHOW TABLES LIKE 'usuarios'")) {
            header('Location: instalar.php');
            exit;
        }
        // Atualização trouxe tabelas novas: cria e repete a requisição.
        foreach (esquema_sql() as $sql) db()->exec($sql);
        $_SESSION['esquema'] = VERSAO_ESQUEMA;
        ob_end_clean();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            header('Location: ' . ($_SERVER['REQUEST_URI'] ?? 'index.php'));
            exit;
        }
        voltar('erro', 'A plataforma acabou de ser atualizada. Tente de novo.', get('r') ?: 'inicio');
    }
    throw $e;
}
