<?php
declare(strict_types=1);

/**
 * Formatação simples do texto das aulas (escapa todo o HTML do autor):
 *   ## Título            -> subtítulo
 *   [[Rótulo]] texto     -> quadro de destaque
 *   - item / 1. item     -> listas
 *   ``` ... ```          -> bloco de código
 *   **negrito**, *linha em itálico*
 * Texto sem marcação continua aparecendo como parágrafos.
 */
function render_conteudo(string $texto): string
{
    $texto = str_replace("\r", '', $texto);
    $h = '';
    // Blocos de código primeiro, para não interpretar o que está dentro deles.
    $partes = preg_split('/^```[^\n]*\n(.*?)\n```$/ms', $texto, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($partes as $i => $parte) {
        if ($i % 2 === 1) {
            $h .= '<pre class="codigo-aula"><code>' . e($parte) . '</code></pre>';
            continue;
        }
        $lista = null;
        foreach (preg_split('/\n\s*\n/', trim($parte)) as $bloco) {
            $bloco = trim($bloco);
            if ($bloco === '') continue;
            $tipo = preg_match('/^- /', $bloco) ? 'ul' : (preg_match('/^\d+\. /', $bloco) ? 'ol' : null);
            if ($lista && $tipo !== $lista) { $h .= "</$lista>"; $lista = null; }
            if ($tipo) {
                if (!$lista) { $h .= "<$tipo>"; $lista = $tipo; }
                $num = $tipo === 'ol' ? (int) $bloco : 0;
                $item = preg_replace('/^(- |\d+\. )/', '', $bloco);
                $h .= '<li' . ($num ? ' value="' . $num . '"' : '') . '>' . inline_conteudo($item) . '</li>';
            } elseif (str_starts_with($bloco, '## ')) {
                $h .= '<h3 class="sub-aula">' . inline_conteudo(substr($bloco, 3)) . '</h3>';
            } elseif (preg_match('/^\[\[([^\]]{1,80})\]\]\s*(.*)$/s', $bloco, $m)) {
                $classe = preg_match('/atenç|segurança|regra/i', $m[1]) ? ' alerta' : (preg_match('/desafio|pergunta/i', $m[1]) ? ' desafio' : '');
                $h .= '<div class="destaque-aula' . $classe . '"><strong>' . e($m[1]) . '</strong> ' . inline_conteudo($m[2]) . '</div>';
            } elseif (preg_match('/^\*([^*].*)\*$/s', $bloco, $m)) {
                $h .= '<p class="suave"><em>' . inline_conteudo($m[1]) . '</em></p>';
            } else {
                $h .= '<p>' . nl2br(inline_conteudo($bloco), false) . '</p>';
            }
        }
        if ($lista) $h .= "</$lista>";
    }
    return $h;
}

function inline_conteudo(string $s): string
{
    $s = e($s);
    $s = preg_replace('/`([^`\n]{1,200})`/', '<code>$1</code>', $s);
    return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
}

// ---------- Cursos prontos (app/conteudo/*.json) ----------

function pacotes_de_cursos(): array
{
    $r = [];
    foreach (glob(RAIZ . '/app/conteudo/*.json') ?: [] as $arq) {
        $c = json_decode((string) file_get_contents($arq), true);
        if (!is_array($c) || empty($c['slug'])) continue;
        $aulas = 0;
        foreach ($c['modulos'] as $m) $aulas += count($m['aulas']);
        $r[basename($arq)] = ['titulo' => $c['titulo'], 'descricao' => $c['descricao'], 'slug' => $c['slug'],
            'modulos' => count($c['modulos']), 'aulas' => $aulas, 'carga' => (int) ($c['carga_horaria'] ?? 0)];
    }
    ksort($r);
    return $r;
}

/** Cria o curso (em rascunho) com módulos, aulas, exercícios e notas do professor. */
function importar_pacote(string $arquivo): string
{
    $c = json_decode((string) file_get_contents(RAIZ . '/app/conteudo/' . $arquivo), true);
    if (!is_array($c)) throw new RuntimeException('Pacote inválido.');
    return transacao(function () use ($c) {
        $cursoId = novo_id();
        q("INSERT INTO cursos (id, slug, titulo, descricao, nivel, carga_horaria, preco_centavos, status, criado_em)
           VALUES (?,?,?,?,?,?,0,'RASCUNHO',?)",
            [$cursoId, $c['slug'], $c['titulo'], $c['descricao'], $c['nivel'] ?? 'INICIANTE', (int) ($c['carga_horaria'] ?? 0), agora()]);
        foreach ($c['modulos'] as $mi => $m) {
            $modId = novo_id();
            q('INSERT INTO modulos (id, curso_id, ordem, titulo) VALUES (?,?,?,?)', [$modId, $cursoId, $mi + 1, $m['titulo']]);
            foreach ($m['aulas'] as $ai => $a) {
                $aulaId = novo_id();
                q('INSERT INTO aulas (id, modulo_id, ordem, titulo, tipo, conteudo, simulador_url, obrigatoria, duracao_min) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$aulaId, $modId, $ai + 1, mb_substr($a['titulo'], 0, 160), $a['tipo'], $a['conteudo'], $a['simulador_url'] ?? null,
                        ($a['obrigatoria'] ?? true) ? 1 : 0, (int) ($a['duracao_min'] ?? 0)]);
                if (!empty($a['notas'])) q('INSERT INTO aula_notas (aula_id, texto) VALUES (?,?)', [$aulaId, $a['notas']]);
                if (!empty($a['exercicio'])) {
                    $x = $a['exercicio'];
                    q('INSERT INTO exercicios (id, aula_id, enunciado, rubrica, tipo_entrega) VALUES (?,?,?,?,?)',
                        [novo_id(), $aulaId, $x['enunciado'], json_encode($x['rubrica'], JSON_UNESCAPED_UNICODE), $x['tipo_entrega'] ?? 'LINK_VIDEO']);
                }
            }
        }
        return $cursoId;
    });
}

function acao_importar_curso(): void
{
    $admin = exigir_admin();
    $arq = post('arquivo');
    $pacotes = pacotes_de_cursos();
    if (!isset($pacotes[$arq])) voltar('erro', 'Curso não encontrado.', 'admin');
    if (valor('SELECT 1 FROM cursos WHERE slug = ?', [$pacotes[$arq]['slug']])) voltar('erro', 'Este curso já foi importado.', 'admin');
    $id = importar_pacote($arq);
    auditar($admin['id'], null, 'curso.importado', 'Curso', $id, ['pacote' => $arq]);
    voltar('ok', 'Curso importado como rascunho. Revise, defina o preço e publique quando quiser.', 'admin_curso', ['id' => $id]);
}
