<?php
// Fotos e arquivos enviados junto com as entregas.
// Ficam na pasta arquivos/, bloqueada para acesso direto; só saem pela rota "arquivo", que confere a permissão.

const ARQ_MAX_QTD = 4;
const ARQ_MAX_BYTES = 10 * 1024 * 1024;
const ARQ_LADO_MAX = 1600;
const ARQ_IMAGENS = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
const ARQ_OUTROS = [
    'pdf' => 'application/pdf', 'zip' => 'application/zip', 'sb3' => 'application/octet-stream',
    'ino' => 'text/plain', 'py' => 'text/plain', 'txt' => 'text/plain', 'c' => 'text/plain', 'cpp' => 'text/plain', 'h' => 'text/plain',
];

function pasta_arquivos(): string
{
    $p = dirname(__DIR__) . '/arquivos';
    if (!is_dir($p)) @mkdir($p, 0755, true);
    if (!is_file("$p/.htaccess")) @copy(__DIR__ . '/.htaccess', "$p/.htaccess");
    if (!is_file("$p/index.html")) @file_put_contents("$p/index.html", '');
    return $p;
}

function bytes_ini(string $v): int
{
    $v = trim($v);
    $n = (int) $v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024;
        case 'm': $n *= 1024;
        case 'k': $n *= 1024;
    }
    return $n;
}

/** Maior arquivo que a hospedagem e a plataforma aceitam, em bytes. */
function limite_arquivo(): int
{
    $l = [ARQ_MAX_BYTES];
    foreach (['upload_max_filesize', 'post_max_size'] as $k) if (($b = bytes_ini((string) ini_get($k))) > 0) $l[] = $b;
    return min($l);
}

function tamanho_legivel(int $b): string
{
    if ($b >= 1048576) return str_replace('.', ',', (string) round($b / 1048576, 1)) . ' MB';
    return max(1, (int) round($b / 1024)) . ' KB';
}

/**
 * Confere os arquivos do campo e devolve a lista pronta para guardar.
 * Em caso de problema devolve a mensagem de erro (string).
 */
function receber_arquivos(string $campo)
{
    $f = $_FILES[$campo] ?? null;
    if (!$f || !is_array($f['name'])) return [];
    $itens = [];
    foreach ($f['name'] as $i => $nome) {
        $erro = (int) $f['error'][$i];
        if ($erro === UPLOAD_ERR_NO_FILE) continue;
        $nome = mb_substr(trim(str_replace(["\0", '/', '\\'], '', (string) $nome)), 0, 150) ?: 'arquivo';
        if (in_array($erro, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return "O arquivo \"$nome\" é maior que " . tamanho_legivel(limite_arquivo()) . '.';
        if ($erro !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'][$i])) return "Não foi possível receber o arquivo \"$nome\". Tente de novo.";
        $tam = (int) $f['size'][$i];
        if ($tam > ARQ_MAX_BYTES) return "O arquivo \"$nome\" é maior que " . tamanho_legivel(ARQ_MAX_BYTES) . '.';
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        $tmp = $f['tmp_name'][$i];
        if (isset(ARQ_IMAGENS[$ext])) {
            $info = @getimagesize($tmp);
            if (!$info || !in_array($info['mime'] ?? '', ARQ_IMAGENS, true)) return "\"$nome\" não é uma imagem válida.";
            $mime = $info['mime'];
        } elseif (isset(ARQ_OUTROS[$ext])) {
            $ini = (string) file_get_contents($tmp, false, null, 0, 4);
            if ($ext === 'pdf' && $ini !== '%PDF') return "\"$nome\" não é um PDF válido.";
            if (in_array($ext, ['zip', 'sb3'], true) && substr($ini, 0, 2) !== 'PK') return "\"$nome\" não é um arquivo válido.";
            $mime = ARQ_OUTROS[$ext];
        } else {
            return "Tipo de arquivo não aceito: \"$nome\". Envie fotos (JPG, PNG), PDF, código (.ino, .py, .txt), projeto do Scratch (.sb3) ou .zip.";
        }
        $itens[] = ['tmp' => $tmp, 'nome' => $nome, 'ext' => $ext, 'mime' => $mime, 'tamanho' => $tam];
    }
    if (count($itens) > ARQ_MAX_QTD) return 'Envie no máximo ' . ARQ_MAX_QTD . ' arquivos por entrega.';
    return $itens;
}

/**
 * Reduz a foto para no máximo 1600 px e regrava em JPEG, o que também apaga
 * dados escondidos da câmera (como a localização GPS). Devolve false se não der.
 */
function reduzir_imagem(string $origem, string $destino, string $mime): bool
{
    if (!function_exists('imagecreatetruecolor') || $mime === 'image/gif') return false;
    $info = @getimagesize($origem);
    if (!$info || $info[0] * $info[1] > 40000000) return false;
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($origem),
        'image/png' => @imagecreatefrompng($origem),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($origem) : false,
        default => false,
    };
    if (!$img) return false;
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($origem)['Orientation'] ?? 1);
        $giro = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($giro && ($r = imagerotate($img, $giro, 0))) { imagedestroy($img); $img = $r; }
    }
    $w = imagesx($img); $h = imagesy($img);
    $k = min(1, ARQ_LADO_MAX / max($w, $h));
    $nw = max(1, (int) round($w * $k)); $nh = max(1, (int) round($h * $k));
    $novo = imagecreatetruecolor($nw, $nh);
    imagefill($novo, 0, 0, imagecolorallocate($novo, 255, 255, 255));
    imagecopyresampled($novo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = imagejpeg($novo, $destino, 82);
    imagedestroy($img); imagedestroy($novo);
    return $ok;
}

/** Guarda os arquivos já conferidos. Anota em $gravados o que foi gravado, para desfazer se algo falhar. */
function guardar_arquivos(string $entregaId, array $itens, array &$gravados): void
{
    $pasta = pasta_arquivos();
    foreach ($itens as $it) {
        $id = novo_id();
        $nome = $it['nome'];
        $mime = $it['mime'];
        if (isset(ARQ_IMAGENS[$it['ext']]) && reduzir_imagem($it['tmp'], "$pasta/$id.jpg", $mime)) {
            $arquivo = "$id.jpg";
            $mime = 'image/jpeg';
            if (!preg_match('/\.jpe?g$/i', $nome)) $nome = preg_replace('/\.[^.]*$/', '', $nome) . '.jpg';
        } else {
            $arquivo = "$id.{$it['ext']}";
            if (!move_uploaded_file($it['tmp'], "$pasta/$arquivo")) throw new RuntimeException('Falha ao gravar o arquivo enviado.');
        }
        $gravados[] = "$pasta/$arquivo";
        q('INSERT INTO entrega_arquivos (id, entrega_id, nome, arquivo, tipo, tamanho, criado_em) VALUES (?,?,?,?,?,?,?)',
            [$id, $entregaId, $nome, $arquivo, $mime, (int) filesize("$pasta/$arquivo"), agora()]);
    }
}

/** [entrega_id => [arquivos...]] */
function arquivos_das_entregas(array $ids): array
{
    if (!$ids) return [];
    $r = [];
    foreach (todos('SELECT * FROM entrega_arquivos WHERE entrega_id IN (' . marcas($ids) . ') ORDER BY criado_em, nome', array_values($ids)) as $a) $r[$a['entrega_id']][] = $a;
    return $r;
}

function eh_imagem(array $a): bool
{
    return str_starts_with($a['tipo'], 'image/');
}

/** Miniaturas das fotos e links dos outros arquivos. */
function mostrar_arquivos(array $arqs, bool $grande = false): string
{
    if (!$arqs) return '';
    $h = '<div class="arquivos-entrega' . ($grande ? ' grande' : '') . '">';
    foreach ($arqs as $a) {
        $u = e(url('arquivo', ['id' => $a['id']]));
        $h .= eh_imagem($a)
            ? '<a href="' . $u . '" target="_blank" rel="noopener"><img src="' . $u . '" alt="' . e($a['nome']) . '" loading="lazy"></a>'
            : '<a class="arquivo" href="' . $u . '">📎 ' . e($a['nome']) . ' <span class="suave pequeno">(' . e(tamanho_legivel((int) $a['tamanho'])) . ')</span></a>';
    }
    return $h . '</div>';
}

/** Pode ver o arquivo: o próprio aluno, ou quem corrige as entregas dele. */
function pode_ver_entrega(array $u, string $entregaId, string $donoId): bool
{
    if ($donoId === $u['id']) return true;
    $f = filtro_entregas($u);
    return $f && valor("SELECT 1 FROM entregas e WHERE e.id = ? AND {$f[0]}", array_merge([$entregaId], $f[1]));
}
