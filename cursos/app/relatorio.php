<?php
// Relatório de progresso das turmas, para a coordenação da escola, os professores e o administrador.

const DIAS_PARADO = 14;

/**
 * Escolas que o usuário pode ver no relatório: [org_id => ['nome' => ..., 'todas' => bool]].
 * "todas" = vê todas as turmas e alunos da escola; professor vê só as turmas dele.
 */
function escopo_relatorio(array $u): array
{
    $r = [];
    if (eh_admin($u)) {
        foreach (todos('SELECT id, nome FROM organizacoes ORDER BY nome') as $o) $r[$o['id']] = ['nome' => $o['nome'], 'todas' => true];
        return $r;
    }
    foreach (orgs_professor($u) as $id => $nome) $r[$id] = ['nome' => $nome, 'todas' => false];
    foreach (orgs_geridas($u) as $id => $nome) $r[$id] = ['nome' => $nome, 'todas' => true];
    return $r;
}

function pode_ver_relatorio(array $u): bool
{
    return (bool) escopo_relatorio($u);
}

/** Números do curso para a lista de alunos. */
function dados_relatorio(string $cursoId, array $alunos): array
{
    $ids = array_column($alunos, 'id');
    $modulos = todos('SELECT id, ordem, titulo FROM modulos WHERE curso_id = ? ORDER BY ordem', [$cursoId]);
    $aulas = todos('SELECT a.id, a.modulo_id, a.obrigatoria FROM aulas a JOIN modulos m ON m.id = a.modulo_id WHERE m.curso_id = ? ORDER BY m.ordem, a.ordem', [$cursoId]);
    $exercicios = todos(
        'SELECT x.id, x.nota_maxima, a.titulo, m.ordem AS modulo FROM exercicios x JOIN aulas a ON a.id = x.aula_id JOIN modulos m ON m.id = a.modulo_id
         WHERE m.curso_id = ? ORDER BY m.ordem, a.ordem', [$cursoId]);
    $obrig = array_values(array_filter($aulas, fn($a) => (int) $a['obrigatoria'] === 1));
    $obrigPorModulo = [];
    foreach ($obrig as $a) $obrigPorModulo[$a['modulo_id']][] = $a['id'];
    $moduloDaAula = array_column($aulas, 'modulo_id', 'id');

    $acesso = $prog = $ultima = $entregas = $certs = [];
    if ($ids) {
        $in = marcas($ids);
        foreach (todos("SELECT usuario_id FROM acessos WHERE curso_id = ? AND status = 'ATIVO' AND (fim IS NULL OR fim > ?) AND usuario_id IN ($in)", array_merge([$cursoId, agora()], $ids)) as $r) $acesso[$r['usuario_id']] = true;
        if ($aulas) {
            $aulaIds = array_column($aulas, 'id');
            foreach (todos('SELECT usuario_id, aula_id, concluido_em FROM progresso WHERE aula_id IN (' . marcas($aulaIds) . ") AND usuario_id IN ($in)", array_merge($aulaIds, $ids)) as $r) {
                $prog[$r['usuario_id']][$r['aula_id']] = true;
                $ultima[$r['usuario_id']] = max($ultima[$r['usuario_id']] ?? '', $r['concluido_em']);
            }
        }
        if ($exercicios) {
            $exIds = array_column($exercicios, 'id');
            foreach (todos('SELECT usuario_id, exercicio_id, status, nota, enviado_em FROM entregas WHERE exercicio_id IN (' . marcas($exIds) . ") AND usuario_id IN ($in) ORDER BY tentativa", array_merge($exIds, $ids)) as $r) {
                $entregas[$r['usuario_id']][$r['exercicio_id']] = $r; // fica a última tentativa
                $ultima[$r['usuario_id']] = max($ultima[$r['usuario_id']] ?? '', $r['enviado_em']);
            }
        }
        foreach (todos("SELECT usuario_id, codigo FROM certificados WHERE curso_id = ? AND usuario_id IN ($in)", array_merge([$cursoId], $ids)) as $r) $certs[$r['usuario_id']] = $r['codigo'];
    }

    $notaMax = array_column($exercicios, 'nota_maxima', 'id');
    $limiteParado = date('Y-m-d H:i:s', time() - DIAS_PARADO * 86400);
    $linhas = [];
    foreach ($alunos as $al) {
        $uid = $al['id'];
        if (empty($acesso[$uid]) && !isset($certs[$uid])) { $linhas[] = $al + ['acesso' => false]; continue; }
        $feitas = 0;
        $modAtual = null;
        foreach ($modulos as $m) {
            $lista = $obrigPorModulo[$m['id']] ?? [];
            $ok = count(array_filter($lista, fn($id) => isset($prog[$uid][$id])));
            $feitas += $ok;
            if ($modAtual === null && $ok < count($lista)) $modAtual = $m;
        }
        $ents = $entregas[$uid] ?? [];
        $aprovadas = count(array_filter($ents, fn($e) => $e['status'] === 'APROVADA'));
        $aguardando = count(array_filter($ents, fn($e) => $e['status'] === 'ENVIADA'));
        $notas = [];
        foreach ($ents as $exId => $e) if ($e['nota'] !== null && $e['status'] !== 'ENVIADA') $notas[] = $e['nota'] / max(1, (int) $notaMax[$exId]) * 10;
        $ult = $ultima[$uid] ?? null;
        $pct = count($obrig) ? (int) round($feitas / count($obrig) * 100) : 0;
        if (isset($certs[$uid])) $sit = ['Certificado', 'ok'];
        elseif (!$ult) $sit = ['Não começou', 'alerta'];
        elseif ($ult < $limiteParado) $sit = ['Parado há ' . (int) floor((time() - strtotime($ult)) / 86400) . ' dias', 'erro'];
        else $sit = ['Em dia', ''];
        $linhas[] = $al + [
            'acesso' => true, 'feitas' => $feitas, 'pct' => isset($certs[$uid]) ? 100 : $pct,
            'modulo' => $modAtual ? 'Módulo ' . (int) $modAtual['ordem'] : 'Todos concluídos',
            'aprovadas' => $aprovadas, 'aguardando' => $aguardando, 'nota' => $notas ? array_sum($notas) / count($notas) : null,
            'ultima' => $ult, 'situacao' => $sit, 'certificado' => $certs[$uid] ?? null,
        ];
    }
    $ativos = array_values(array_filter($linhas, fn($l) => $l['acesso']));

    // Andamento médio de cada módulo.
    $porModulo = [];
    foreach ($modulos as $m) {
        $lista = $obrigPorModulo[$m['id']] ?? [];
        if (!$lista || !$ativos) { $porModulo[] = $m + ['pct' => 0, 'aulas' => count($lista)]; continue; }
        $soma = 0;
        foreach ($ativos as $l) $soma += count(array_filter($lista, fn($id) => isset($prog[$l['id']][$id])));
        $porModulo[] = $m + ['pct' => (int) round($soma / (count($lista) * count($ativos)) * 100), 'aulas' => count($lista)];
    }

    // Situação de cada exercício na turma.
    $porExercicio = [];
    foreach ($exercicios as $x) {
        $env = $apr = $agu = 0; $ns = [];
        foreach ($ativos as $l) {
            $e = $entregas[$l['id']][$x['id']] ?? null;
            if (!$e) continue;
            $env++;
            if ($e['status'] === 'APROVADA') $apr++;
            if ($e['status'] === 'ENVIADA') $agu++;
            if ($e['nota'] !== null && $e['status'] !== 'ENVIADA') $ns[] = $e['nota'] / max(1, (int) $x['nota_maxima']) * 10;
        }
        $porExercicio[] = ['titulo' => $x['titulo'], 'modulo' => (int) $x['modulo'], 'entregaram' => $env, 'aprovados' => $apr, 'aguardando' => $agu, 'nota' => $ns ? array_sum($ns) / count($ns) : null];
    }

    $notasTodas = array_filter(array_column($ativos, 'nota'), fn($n) => $n !== null);
    $resumo = [
        'alunos' => count($ativos),
        'sem_acesso' => count($linhas) - count($ativos),
        'progresso' => $ativos ? (int) round(array_sum(array_column($ativos, 'pct')) / count($ativos)) : 0,
        'certificados' => count(array_filter($ativos, fn($l) => $l['certificado'])),
        'atencao' => count(array_filter($ativos, fn($l) => in_array($l['situacao'][1], ['erro', 'alerta'], true))),
        'aguardando' => array_sum(array_column($ativos, 'aguardando')),
        'nota' => $notasTodas ? array_sum($notasTodas) / count($notasTodas) : null,
        'aulas' => count($obrig),
        'exercicios' => count($exercicios),
    ];
    return ['linhas' => $linhas, 'modulos' => $porModulo, 'exercicios' => $porExercicio, 'resumo' => $resumo];
}

function nota_br(?float $n): string
{
    return $n === null ? '—' : str_replace('.', ',', (string) round($n, 1));
}
