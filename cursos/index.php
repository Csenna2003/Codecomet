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

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

config();
iniciar_sessao();

const ROTAS = [
    'inicio', 'entrar', 'cadastro', 'painel', 'curso', 'aula', 'pedido', 'certificado', 'redefinir',
    'professor', 'entrega', 'escola', 'admin', 'admin_curso', 'admin_pedidos',
];

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        verificar_csrf();
        executar_acao(post('acao'));
        ir();
    }
    $rota = get('r') ?: 'inicio';
    if (!in_array($rota, ROTAS, true)) nao_encontrado();
    require __DIR__ . "/app/paginas/$rota.php";
} catch (PDOException $e) {
    // Tabelas ainda não criadas: leva ao instalador.
    if ($e->getCode() === '42S02') {
        header('Location: instalar.php');
        exit;
    }
    throw $e;
}
