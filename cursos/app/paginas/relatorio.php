<?php
$u = exigir_usuario();
$escopo = escopo_relatorio($u);
if (!$escopo) ir('painel');
$orgId = isset($escopo[get('org')]) ? get('org') : array_key_first($escopo);
$org = $escopo[$orgId];

// Professor vê só as turmas dele; coordenação e administrador veem todas.
$turmas = $org['todas']
    ? todos('SELECT id, nome, periodo FROM turmas WHERE org_id = ? ORDER BY nome', [$orgId])
    : todos('SELECT id, nome, periodo FROM turmas WHERE org_id = ? AND professor_id = ? ORDER BY nome', [$orgId, $u['id']]);
$turmaIds = array_column($turmas, 'id');
$turmaId = in_array(get('turma'), $turmaIds, true) ? get('turma') : (!$org['todas'] && count($turmas) === 1 ? $turmas[0]['id'] : '');
$turmaNome = $turmaId ? $turmas[array_search($turmaId, $turmaIds, true)]['nome'] : null;

if ($turmaId) {
    $alunos = todos('SELECT u.id, u.nome, u.email FROM turma_alunos ta JOIN usuarios u ON u.id = ta.usuario_id WHERE ta.turma_id = ? ORDER BY u.nome', [$turmaId]);
} elseif ($org['todas']) {
    $alunos = todos("SELECT u.id, u.nome, u.email FROM membros m JOIN usuarios u ON u.id = m.usuario_id WHERE m.org_id = ? AND m.papel = 'ALUNO' ORDER BY u.nome", [$orgId]);
} else {
    $alunos = $turmaIds ? todos('SELECT DISTINCT u.id, u.nome, u.email FROM turma_alunos ta JOIN usuarios u ON u.id = ta.usuario_id WHERE ta.turma_id IN (' . marcas($turmaIds) . ') ORDER BY u.nome', $turmaIds) : [];
}
$turmaDe = [];
if ($alunos) {
    $ids = array_column($alunos, 'id');
    foreach (todos('SELECT ta.usuario_id, t.nome FROM turma_alunos ta JOIN turmas t ON t.id = ta.turma_id WHERE t.org_id = ? AND ta.usuario_id IN (' . marcas($ids) . ')', array_merge([$orgId], $ids)) as $r) $turmaDe[$r['usuario_id']][] = $r['nome'];
}

// Cursos da escola: os contratados e os que os alunos fazem pela escola.
$cursos = todos(
    'SELECT DISTINCT c.id, c.titulo FROM cursos c
     WHERE c.id IN (SELECT curso_id FROM licencas WHERE org_id = ?) OR c.id IN (SELECT curso_id FROM acessos WHERE org_id = ?)
     ORDER BY c.titulo', [$orgId, $orgId]);
$cursoIds = array_column($cursos, 'id');
$cursoId = in_array(get('curso'), $cursoIds, true) ? get('curso') : ($cursoIds[0] ?? '');
$cursoTitulo = $cursoId ? $cursos[array_search($cursoId, $cursoIds, true)]['titulo'] : '';
$d = $cursoId ? dados_relatorio($cursoId, $alunos) : null;

if ($d && get('csv') === '1') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio-' . slugify($org['nome'] . ' ' . ($turmaNome ?? '') . ' ' . $cursoTitulo) . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Aluno', 'Login', 'Turma', 'Progresso (%)', 'Aulas concluídas', 'Onde está', 'Projetos aprovados', 'Aguardando correção', 'Nota média (0-10)', 'Última atividade', 'Situação', 'Certificado'], ';');
    foreach ($d['linhas'] as $l) {
        $base = [$l['nome'], login_de($l['email']), implode(', ', $turmaDe[$l['id']] ?? [])];
        fputcsv($out, $l['acesso']
            ? array_merge($base, [$l['pct'], $l['feitas'] . ' de ' . $d['resumo']['aulas'], $l['modulo'], $l['aprovadas'] . ' de ' . $d['resumo']['exercicios'], $l['aguardando'], nota_br($l['nota']), $l['ultima'] ? data_br($l['ultima']) : '', $l['situacao'][0], $l['certificado'] ?? ''])
            : array_merge($base, ['', '', '', '', '', '', '', 'Sem acesso ao curso', '']), ';');
    }
    exit;
}

$params = ['org' => $orgId, 'turma' => $turmaId, 'curso' => $cursoId];
cabecalho('Relatório das turmas');
?>
<p class="pequeno nao-imprimir"><a href="<?= e(url($org['todas'] ? 'escola' : 'professor', $org['todas'] ? ['org' => $orgId] : [])) ?>">← <?= $org['todas'] ? e($org['nome']) : 'Correções' ?></a></p>
<div class="linha">
  <h1>Relatório de progresso</h1>
  <?php if ($d && $alunos): ?><span class="acoes nao-imprimir">
    <button type="button" class="btn sec mini" data-imprimir>Imprimir ou salvar PDF</button>
    <a class="btn sec mini" href="<?= e(url('relatorio', $params + ['csv' => 1])) ?>">Baixar planilha</a>
  </span><?php endif ?>
</div>
<p class="suave"><strong><?= e($org['nome']) ?></strong> · <?= $turmaNome ? 'Turma ' . e($turmaNome) : ($org['todas'] ? 'Todos os alunos' : 'Minhas turmas') ?><?= $cursoTitulo ? ' · ' . e($cursoTitulo) : '' ?> · gerado em <?= e(data_br(agora())) ?></p>

<form method="get" action="index.php" class="cartao campos nao-imprimir">
  <input type="hidden" name="r" value="relatorio">
  <?php if (count($escopo) > 1): ?>
    <label>Escola<select name="org" onchange="this.form.turma.value='';this.form.curso.value='';this.form.submit()"><?php foreach ($escopo as $id => $o): ?><option value="<?= e($id) ?>" <?= $id === $orgId ? 'selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach ?></select></label>
  <?php else: ?><input type="hidden" name="org" value="<?= e($orgId) ?>"><?php endif ?>
  <label>Turma<select name="turma"><?php if ($org['todas'] || count($turmas) !== 1): ?><option value=""><?= $org['todas'] ? 'Todos os alunos' : 'Todas as minhas turmas' ?></option><?php endif ?>
    <?php foreach ($turmas as $t): ?><option value="<?= e($t['id']) ?>" <?= $t['id'] === $turmaId ? 'selected' : '' ?>><?= e($t['nome'] . ($t['periodo'] ? ' (' . $t['periodo'] . ')' : '')) ?></option><?php endforeach ?></select></label>
  <label>Curso<select name="curso"><?php foreach ($cursos as $c): ?><option value="<?= e($c['id']) ?>" <?= $c['id'] === $cursoId ? 'selected' : '' ?>><?= e($c['titulo']) ?></option><?php endforeach ?></select></label>
  <div><button class="btn sec">Ver relatório</button></div>
</form>

<?php if (!$cursos): ?>
  <p class="aviso alerta">Esta escola ainda não tem cursos. Quando os alunos estiverem matriculados, o relatório aparece aqui.</p>
<?php elseif (!$alunos): ?>
  <p class="aviso alerta">Nenhum aluno <?= $turmaNome ? 'nesta turma' : 'nesta escola' ?> ainda.</p>
<?php else: $r = $d['resumo']; ?>
  <div class="numeros-relatorio">
    <div class="cartao"><strong><?= $r['alunos'] ?></strong><span>alunos no curso</span></div>
    <div class="cartao"><strong><?= $r['progresso'] ?>%</strong><span>progresso médio</span></div>
    <div class="cartao"><strong><?= $r['certificados'] ?></strong><span>com certificado</span></div>
    <div class="cartao"><strong><?= nota_br($r['nota']) ?></strong><span>nota média nos projetos (0 a 10)</span></div>
    <div class="cartao <?= $r['atencao'] ? 'atencao' : '' ?>"><strong><?= $r['atencao'] ?></strong><span>precisam de atenção (parados ou sem começar)</span></div>
    <div class="cartao"><strong><?= $r['aguardando'] ?></strong><span>projetos aguardando correção</span></div>
  </div>
  <?php if ($r['sem_acesso']): ?><p class="pequeno suave"><?= $r['sem_acesso'] ?> aluno(s) da lista ainda não têm acesso a este curso.</p><?php endif ?>

  <h2>Alunos</h2>
  <div class="cartao tabela-rolagem"><table class="tabela-relatorio">
    <thead><tr><th>Aluno</th><?php if (!$turmaNome): ?><th>Turma</th><?php endif ?><th>Progresso</th><th>Onde está</th><th>Projetos aprovados</th><th>Nota média</th><th>Última atividade</th><th>Situação</th></tr></thead>
    <tbody>
    <?php foreach ($d['linhas'] as $l): ?>
      <tr>
        <td><a href="<?= e(url('aluno', ['id' => $l['id']])) ?>"><?= e($l['nome']) ?></a></td>
        <?php if (!$turmaNome): ?><td><?= e(implode(', ', $turmaDe[$l['id']] ?? []) ?: '—') ?></td><?php endif ?>
        <?php if (!$l['acesso']): ?>
          <td colspan="6" class="suave pequeno">Sem acesso a este curso</td>
        <?php else: ?>
          <td style="min-width:120px"><?= barra($l['pct']) ?><span class="pequeno"><?= $l['pct'] ?>% · <?= $l['feitas'] ?> de <?= $r['aulas'] ?> aulas</span></td>
          <td><?= e($l['modulo']) ?></td>
          <td><?= $l['aprovadas'] ?> de <?= $r['exercicios'] ?><?= $l['aguardando'] ? '<br><span class="pequeno suave">' . $l['aguardando'] . ' aguardando</span>' : '' ?></td>
          <td><?= nota_br($l['nota']) ?></td>
          <td><?= $l['ultima'] ? e(data_br($l['ultima'])) : '—' ?></td>
          <td><span class="etiqueta <?= e($l['situacao'][1]) ?>"><?= e($l['situacao'][0]) ?></span></td>
        <?php endif ?>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>

  <div class="relatorio-blocos">
    <section>
      <h2>Andamento por módulo</h2>
      <div class="cartao modulos-relatorio">
        <?php foreach ($d['modulos'] as $m): ?><div>
          <p class="pequeno" style="margin:.6rem 0 .2rem"><strong>Módulo <?= (int) $m['ordem'] ?>:</strong> <?= e($m['titulo']) ?> <span class="suave">— <?= $m['pct'] ?>% das aulas concluídas</span></p>
          <?= barra($m['pct']) ?>
        </div><?php endforeach ?>
      </div>
    </section>
    <section>
      <h2>Projetos do curso</h2>
      <div class="cartao tabela-rolagem"><table>
        <thead><tr><th>Módulo</th><th>Projeto</th><th>Entregaram</th><th>Aprovados</th><th>Nota média</th></tr></thead>
        <tbody><?php foreach ($d['exercicios'] as $x): ?>
          <tr><td><?= $x['modulo'] ?></td><td><?= e($x['titulo']) ?></td><td><?= $x['entregaram'] ?> de <?= $r['alunos'] ?><?= $x['aguardando'] ? '<br><span class="pequeno suave">' . $x['aguardando'] . ' aguardando correção</span>' : '' ?></td><td><?= $x['aprovados'] ?></td><td><?= nota_br($x['nota']) ?></td></tr>
        <?php endforeach ?></tbody>
      </table></div>
    </section>
  </div>
  <p class="pequeno suave">“Parado” = sem concluir aulas nem enviar projetos há <?= DIAS_PARADO ?> dias ou mais. O progresso conta só as aulas obrigatórias; a nota média é a da última correção de cada projeto, convertida para 0 a 10.</p>
<?php endif ?>
<?php rodape();
