<?php
declare(strict_types=1);

/**
 * E-mails pela função mail() do PHP, que a hospedagem compartilhada já oferece.
 * Remetente: 'email_remetente' no config.php, ou nao-responda@ o domínio do site.
 */
function remetente_email(): string
{
    $cfg = trim((string) (config()['email_remetente'] ?? ''));
    if ($cfg && filter_var($cfg, FILTER_VALIDATE_EMAIL)) return $cfg;
    $host = preg_replace('/^(www|cursos)\./', '', strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]));
    return 'nao-responda@' . $host;
}

/**
 * Envia um e-mail simples com um botão opcional. Nunca interrompe a página: se o envio
 * falhar, fica registrado em emails_log (visível em Administração).
 */
function enviar_email(string $para, string $assunto, string $texto, ?string $rotuloBotao = null, ?string $link = null): bool
{
    $para = trim(str_replace(["\r", "\n"], '', $para));
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) return false;
    $site = nome_site();
    $de = remetente_email();
    $fronteira = 'b' . bin2hex(random_bytes(8));

    $txt = $texto . ($link ? "\n\n" . ($rotuloBotao ? "$rotuloBotao: " : '') . $link : '') . "\n\n— $site\n" . url_base();
    $paragrafos = '';
    foreach (preg_split('/\R\R+/', trim($texto)) as $p) $paragrafos .= '<p style="margin:0 0 14px">' . nl2br(e($p)) . '</p>';
    $botao = $link
        ? '<p style="margin:22px 0"><a href="' . e($link) . '" style="background:#0070b8;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block">' . e($rotuloBotao ?: 'Abrir') . '</a></p>'
          . '<p style="font-size:12px;color:#5b6578;word-break:break-all">Se o botão não funcionar, copie este endereço: ' . e($link) . '</p>'
        : '';
    $html = '<!doctype html><html><body style="margin:0;background:#f4f8fc;font-family:Arial,sans-serif;color:#1b2333">'
        . '<div style="max-width:560px;margin:0 auto;padding:24px 16px">'
        . '<p><img src="' . e(url_base() . 'assets/logo.png') . '" alt="' . e($site) . '" width="150" style="height:auto"></p>'
        . '<div style="background:#fff;border:1px solid #dfe3ec;border-radius:12px;padding:22px;font-size:15px;line-height:1.5">'
        . $paragrafos . $botao . '</div>'
        . '<p style="font-size:12px;color:#5b6578;margin-top:16px">' . e($site) . ' · <a href="' . e(url_base()) . '" style="color:#0070b8">' . e(url_base()) . '</a><br>Este é um e-mail automático; não é preciso responder.</p>'
        . '</div></body></html>';

    $corpo = "--$fronteira\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($txt))
        . "--$fronteira\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$fronteira--\r\n";
    $cabecalhos = implode("\r\n", [
        'From: ' . mb_encode_mimeheader($site, 'UTF-8', 'B') . " <$de>",
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"$fronteira\"",
        'X-Mailer: plataforma-cursos',
    ]);
    $assuntoCod = mb_encode_mimeheader($assunto, 'UTF-8', 'B', "\r\n");
    $ok = false;
    try {
        $ok = @mail($para, $assuntoCod, $corpo, $cabecalhos, '-f' . $de);
    } catch (Throwable $t) {
        $ok = false;
    }
    try {
        q('INSERT INTO emails_log (para, assunto, enviado, criado_em) VALUES (?,?,?,?)', [$para, mb_substr($assunto, 0, 200), $ok ? 1 : 0, agora()]);
    } catch (Throwable $t) {
        // O registro é só diagnóstico; nunca impede a ação principal.
    }
    return $ok;
}

/** E-mails de todos os administradores da plataforma. */
function emails_admins(): array
{
    return array_column(todos('SELECT email FROM usuarios WHERE admin = 1'), 'email');
}
