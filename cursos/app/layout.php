<?php
declare(strict_types=1);

function cabecalho(string $titulo, bool $estreito = false): void
{
    $u = usuario_atual();
    $site = nome_site();
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo === '' ? $site : "$titulo — $site") ?></title>
<link rel="stylesheet" href="assets/estilo.css?v=10">
<link rel="icon" href="assets/icone-32.png" sizes="32x32">
<link rel="icon" href="assets/icone-192.png" sizes="192x192">
<link rel="apple-touch-icon" href="assets/icone-180.png">
<meta name="theme-color" content="#0070b8">
<link rel="manifest" href="manifest.webmanifest">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Adoro Robótica">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
</head>
<body>
<header class="topo">
  <div class="dentro">
    <a href="<?= e(url()) ?>" class="marca"><img src="assets/logo.png" alt="<?= e($site) ?>" width="256" height="107"></a>
    <nav>
      <a href="<?= e(url()) ?>">Cursos</a>
      <?php if ($u): ?>
        <a href="<?= e(url('painel')) ?>">Meu painel</a>
        <?php if (eh_equipe($u)): ?><a href="<?= e(url('professor')) ?>">Correções</a><?php endif ?>
        <?php if (da_aulas_particulares($u)): ?><a href="<?= e(url('particulares')) ?>">Aulas particulares</a><?php endif ?>
        <?php if (eh_admin($u) || orgs_geridas($u)): ?><a href="<?= e(url('escola')) ?>">Escola</a><?php endif ?>
        <?php if (eh_admin($u)): ?><a href="<?= e(url('admin')) ?>">Administração</a><?php endif ?>
        <form method="post" action="index.php">
          <?= csrf_campo() ?><input type="hidden" name="acao" value="sair">
          <button class="link">Sair (<?= e(explode(' ', $u['nome'])[0]) ?>)</button>
        </form>
      <?php else: ?>
        <a href="<?= e(url('entrar')) ?>">Entrar</a>
        <a href="<?= e(url('cadastro')) ?>" class="btn mini">Criar conta</a>
      <?php endif ?>
    </nav>
  </div>
</header>
<main<?= $estreito ? ' class="estreito"' : '' ?>>
<?= mostrar_flash() ?>
<?php
}

function rodape(): void
{
    ?>
</main>
<script src="assets/app.js?v=6" defer></script>
</body>
</html>
<?php
}

/** Formulário POST de um botão só. */
function botao_acao(string $acao, string $rotulo, array $campos = [], string $classe = 'btn mini', string $confirmar = ''): string
{
    $h = '<form method="post" action="index.php"' . ($confirmar ? ' data-confirmar="' . e($confirmar) . '"' : '') . '>' . csrf_campo()
        . '<input type="hidden" name="acao" value="' . e($acao) . '">';
    foreach ($campos as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return $h . '<button class="' . e($classe) . '">' . e($rotulo) . '</button></form>';
}

function abrir_form(string $acao, string $classe = 'pilha cartao', array $campos = [], bool $comArquivos = false): string
{
    $h = '<form method="post" action="index.php" class="' . e($classe) . '"' . ($comArquivos ? ' enctype="multipart/form-data"' : '') . '>' . csrf_campo()
        . '<input type="hidden" name="acao" value="' . e($acao) . '">';
    foreach ($campos as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    return $h;
}

function barra(int $pct): string
{
    return '<div class="barra" role="progressbar" aria-valuenow="' . $pct . '" aria-valuemin="0" aria-valuemax="100"><span style="width:' . $pct . '%"></span></div>';
}
