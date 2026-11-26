<?php
$u = exigir_usuario();
$a = um('SELECT ea.*, e.usuario_id FROM entrega_arquivos ea JOIN entregas e ON e.id = ea.entrega_id WHERE ea.id = ?', [get('id')]);
if (!$a || !pode_ver_entrega($u, $a['entrega_id'], $a['usuario_id'])) nao_encontrado();
$caminho = pasta_arquivos() . '/' . basename($a['arquivo']);
if (!is_file($caminho)) nao_encontrado();

while (ob_get_level()) ob_end_clean();
$naTela = eh_imagem($a) || $a['tipo'] === 'application/pdf';
header('Content-Type: ' . ($naTela ? $a['tipo'] : 'application/octet-stream'));
header('Content-Length: ' . filesize($caminho));
$nomeAscii = preg_replace('/[^A-Za-z0-9._-]+/', '_', sem_acentos($a['nome']));
header('Content-Disposition: ' . ($naTela ? 'inline' : 'attachment') . '; filename="' . $nomeAscii . '"; filename*=UTF-8\'\'' . rawurlencode($a['nome']));
header('Cache-Control: private, max-age=86400');
if (eh_imagem($a)) header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
readfile($caminho);
exit;
