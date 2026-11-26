<?php
declare(strict_types=1);

function partes_https(string $url): ?array
{
    $p = parse_url($url);
    if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host'])) return null;
    $p['host'] = preg_replace('/^www\./', '', strtolower($p['host']));
    $p['path'] = $p['path'] ?? '/';
    return $p;
}

function eh_https(string $url): bool
{
    return partes_https($url) !== null;
}

function plataforma_de(string $url): ?string
{
    $p = partes_https($url);
    if (!$p) return null;
    if ($p['host'] === 'tinkercad.com') return 'TINKERCAD';
    if ($p['host'] === 'wokwi.com') return 'WOKWI';
    return null;
}

/** Link de compartilhamento do Tinkercad/Wokwi -> endereço usado no iframe da aula. */
/** Simuladores próprios da plataforma (pasta simuladores/): ['simuladores/nome.html' => 'Título']. */
function simuladores_internos(): array
{
    $r = [];
    foreach (glob(RAIZ . '/simuladores/*.html') ?: [] as $arq) {
        $html = (string) file_get_contents($arq, false, null, 0, 2000);
        $r['simuladores/' . basename($arq)] = preg_match('#<title>(.*?)</title>#s', $html, $m) ? html_entity_decode(trim($m[1])) : basename($arq, '.html');
    }
    return $r;
}

function embed_simulador(string $url): ?array
{
    $url = trim($url);
    if (preg_match('#^simuladores/[a-z0-9-]+\.html$#', $url)) {
        if (!is_file(RAIZ . '/' . $url)) return null;
        return ['plataforma' => 'Adoro Robótica', 'embed' => $url, 'original' => $url, 'interno' => true,
                'titulo' => simuladores_internos()[$url] ?? 'Simulador'];
    }
    $p = partes_https($url);
    if (!$p) return null;
    if ($p['host'] === 'tinkercad.com' && preg_match('#/(?:things|embed)/([A-Za-z0-9]+)#', $p['path'], $m)) {
        return ['plataforma' => 'Tinkercad', 'embed' => "https://www.tinkercad.com/embed/{$m[1]}?editbtn=1", 'original' => $url];
    }
    if ($p['host'] === 'wokwi.com' && str_starts_with($p['path'], '/projects/')) {
        $caminho = preg_replace('#[^A-Za-z0-9/_-]#', '', $p['path']);
        return ['plataforma' => 'Wokwi', 'embed' => "https://wokwi.com$caminho", 'original' => $url];
    }
    return null;
}

/** YouTube/Vimeo -> player embutido. */
function embed_video(string $url): ?string
{
    $p = partes_https($url);
    if (!$p) return null;
    $id = null;
    if ($p['host'] === 'youtu.be') $id = trim($p['path'], '/');
    if ($p['host'] === 'youtube.com' || $p['host'] === 'm.youtube.com') {
        parse_str($p['query'] ?? '', $qs);
        $id = $qs['v'] ?? (preg_match('#/(?:embed|shorts)/([^/]+)#', $p['path'], $m) ? $m[1] : null);
    }
    if ($id !== null) {
        return preg_match('/^[A-Za-z0-9_-]{6,20}$/', (string) $id) ? "https://www.youtube-nocookie.com/embed/$id" : null;
    }
    if ($p['host'] === 'vimeo.com' && preg_match('#/(\d+)#', $p['path'], $m)) return "https://player.vimeo.com/video/{$m[1]}";
    return null;
}
