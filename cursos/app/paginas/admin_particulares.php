<?php
exigir_admin();
$mes = preg_match('/^\d{4}-\d{2}$/', get('mes')) ? get('mes') : date('Y-m');
$ini = "$mes-01 00:00:00";
$fim = date('Y-m-d H:i:s', strtotime("$ini +1 month"));
$vinculos = todos(
    'SELECT p.*, al.nome AS aluno, al.email AS aluno_email, pr.nome AS professor FROM particulares p
     JOIN usuarios al ON al.id = p.aluno_id JOIN usuarios pr ON pr.id = p.professor_id ORDER BY p.ativo DESC, al.nome'
);
$aulas = todos(
    'SELECT ap.*, al.nome AS aluno, pr.nome AS professor, pf.valor_hora_centavos, pf.chave_pix FROM aulas_particulares ap
     JOIN usuarios al ON al.id = ap.aluno_id JOIN usuarios pr ON pr.id = ap.professor_id
     LEFT JOIN professores pf ON pf.usuario_id = ap.professor_id
     WHERE ap.chegada >= ? AND ap.chegada < ? ORDER BY ap.chegada DESC',
    [$ini, $fim]
);
$porProf = [];
foreach ($aulas as $a) {
    $p = &$porProf[$a['professor']];
    $p['total'] = ($p['total'] ?? 0) + 1;
    $p['valor_hora'] = $a['valor_hora_centavos'];
    $p['pix'] = $a['chave_pix'];
    $p[$a['status']] = ($p[$a['status']] ?? 0) + 1;
    if ($a['status'] === 'CONFIRMADA') $p['min'] = ($p['min'] ?? 0) + (int) round((strtotime($a['saida']) - strtotime($a['chegada'])) / 60);
    unset($p);
}
$horas = fn(int $min) => intdiv($min, 60) . 'h' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT);
cabecalho('Aulas particulares — administração');
?>
<p class="pequeno"><a href="<?= e(url('admin')) ?>">← Administração</a> · <a href="<?= e(url('particulares')) ?>">Registrar aulas</a></p>
<h1>Aulas particulares</h1>

<form method="get" action="index.php" class="campos cartao">
  <input type="hidden" name="r" value="admin_particulares">
  <label>Mês<input type="month" name="mes" value="<?= e($mes) ?>"></label>
  <div><button class="btn sec">Ver mês</button></div>
</form>

<h2>Resumo de <?= e(date('m/Y', strtotime($ini))) ?> por professor</h2>
<?php if (!$porProf): ?><p class="suave">Nenhuma aula neste mês.</p><?php else: ?>
<div class="cartao tabela-rolagem"><table>
  <thead><tr><th>Professor</th><th>Aulas</th><th>Confirmadas</th><th>Aguardando</th><th>Não confirmadas</th><th>Horas confirmadas</th><th>Valor da hora</th><th>A pagar (horas confirmadas)</th></tr></thead>
  <tbody><?php foreach ($porProf as $nome => $p): ?>
    <tr><td><?= e($nome) ?></td><td><?= (int) $p['total'] ?></td><td><?= (int) ($p['CONFIRMADA'] ?? 0) ?></td><td><?= (int) ($p['AGUARDANDO'] ?? 0) + (int) ($p['EM_ANDAMENTO'] ?? 0) ?></td>
      <td><?= (int) ($p['CONTESTADA'] ?? 0) ?></td><td><?= e($horas((int) ($p['min'] ?? 0))) ?></td>
      <td><?= $p['valor_hora'] !== null ? e(reais((int) $p['valor_hora'])) : '<span class="suave pequeno">definir no perfil</span>' ?></td>
      <td><?= $p['valor_hora'] !== null ? '<strong>' . e(reais((int) round(($p['min'] ?? 0) / 60 * $p['valor_hora']))) . '</strong>' . ($p['pix'] ? '<br><span class="suave pequeno">Pix: ' . e($p['pix']) . '</span>' : '') : '—' ?></td></tr>
  <?php endforeach ?></tbody>
</table></div>
<?php endif ?>

<p class="pequeno suave">O valor a pagar considera só as aulas confirmadas pelo responsável, pela duração real (da chegada à saída).</p>

<h2>Aulas do mês</h2>
<?php if ($aulas): ?>
<div class="cartao tabela-rolagem"><table>
  <thead><tr><th>Data</th><th>Aluno</th><th>Professor</th><th>Chegada</th><th>Saída</th><th>Confirmação</th><th></th></tr></thead>
  <tbody><?php foreach ($aulas as $a): ?>
    <tr><td><?= e(data_br($a['chegada'])) ?></td><td><a href="<?= e(url('aluno', ['id' => $a['aluno_id']])) ?>"><?= e($a['aluno']) ?></a></td><td><?= e($a['professor']) ?></td>
      <td class="hora"><?= e(hora($a['chegada'])) ?><?= $a['chegada_manual'] ? '*' : '' ?></td><td class="hora"><?= e(hora($a['saida'])) ?></td>
      <td><span class="etiqueta <?= CLASSE_PARTICULAR[$a['status']] ?>"><?= e(ROTULO_PARTICULAR[$a['status']]) ?></span></td>
      <td><a href="<?= e(url('particular_aula', ['id' => $a['id']])) ?>">Ver</a></td></tr>
  <?php endforeach ?></tbody>
</table></div>
<p class="pequeno suave">* horário de chegada informado manualmente pelo professor.</p>
<?php else: ?><p class="suave">Nenhuma aula neste mês.</p><?php endif ?>

<h2>Alunos particulares</h2>
<?php if ($vinculos): ?>
<div class="cartao tabela-rolagem"><table>
  <thead><tr><th>Aluno</th><th>Professor</th><th>Responsável</th><th>Situação</th><th></th></tr></thead>
  <tbody><?php foreach ($vinculos as $v): ?>
    <tr><td><a href="<?= e(url('aluno', ['id' => $v['aluno_id']])) ?>"><?= e($v['aluno']) ?></a><br><span class="suave pequeno"><?= e($v['aluno_email']) ?></span></td>
      <td><?= e($v['professor']) ?></td><td><?= e($v['responsavel_nome']) ?><br><span class="suave pequeno"><?= e($v['responsavel_telefone']) ?></span></td>
      <td><?= $v['ativo'] ? '<span class="etiqueta ok">Ativo</span>' : '<span class="etiqueta">Inativo</span>' ?></td>
      <td><?= botao_acao('alternar_vinculo', $v['ativo'] ? 'Desativar' : 'Reativar', ['vinculo_id' => $v['id']], 'btn mini sec') ?></td></tr>
  <?php endforeach ?></tbody>
</table></div>
<?php endif ?>

<h2>Cadastrar aluno particular</h2>
<?= abrir_form('criar_vinculo') ?>
  <p class="suave pequeno">O professor precisa ter conta na plataforma. Se o aluno ainda não tiver conta, ela é criada com o nome informado.</p>
  <div class="campos">
    <label>E-mail do aluno (ou do responsável)<input name="aluno_email" type="email" required></label>
    <label>Nome do aluno (se ainda não tem conta)<input name="aluno_nome"></label>
  </div>
  <div class="campos">
    <label>E-mail do professor<input name="professor_email" type="email" required></label>
    <label>Nome do responsável<input name="responsavel_nome" required></label>
    <label>WhatsApp do responsável (com DDD)<input name="responsavel_telefone" type="tel" placeholder="(12) 99999-8888" required></label>
  </div>
  <div><button class="btn sec">Cadastrar</button></div>
</form>
<?php rodape();
