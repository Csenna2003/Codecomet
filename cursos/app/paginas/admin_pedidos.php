<?php
exigir_admin();
$sel = 'SELECT p.*, u.nome, u.email, c.titulo FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id JOIN cursos c ON c.id = p.curso_id';
// Quem já avisou que pagou aparece primeiro.
$pendentes = todos("$sel WHERE p.status = 'PENDENTE' AND p.valor_centavos > 0 ORDER BY p.avisou_pago_em IS NULL, p.avisou_pago_em, p.criado_em");
$pagos = todos("$sel WHERE p.status = 'PAGO' AND p.valor_centavos > 0 ORDER BY p.pago_em DESC LIMIT 20");
cabecalho('Pagamentos por Pix');
?>
<p class="pequeno"><a href="<?= e(url('admin')) ?>">← Administração</a></p>
<h1>Pagamentos por Pix</h1>
<p class="suave">Confira no extrato do banco se o Pix chegou (mesmo valor e mesmo identificador) e só então confirme. Confirmar libera o curso na hora.</p>

<h2>Aguardando conferência (<?= count($pendentes) ?>)</h2>
<?php if (!$pendentes): ?><p class="suave">Nenhum pedido pendente.</p><?php else: ?>
<div class="cartao tabela-rolagem"><table>
  <thead><tr><th>Aluno</th><th>Curso</th><th>Valor</th><th>Identificador no extrato</th><th>Situação</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($pendentes as $p): ?>
    <tr>
      <td><?= e($p['nome']) ?><br><span class="suave pequeno"><?= e($p['email']) ?></span></td>
      <td><?= e($p['titulo']) ?></td>
      <td><strong><?= e(brl((int) $p['valor_centavos'])) ?></strong></td>
      <td><code><?= e($p['id']) ?></code></td>
      <td><?= $p['avisou_pago_em'] ? '<span class="etiqueta alerta">Avisou que pagou em ' . e(data_br($p['avisou_pago_em'])) . '</span>' : '<span class="suave pequeno">Gerado em ' . e(data_br($p['criado_em'])) . ', sem aviso</span>' ?></td>
      <td><div class="acoes">
        <?= botao_acao('confirmar_pagamento', 'Confirmar recebimento', ['pedido_id' => $p['id']], 'btn mini', 'Confirmar que o Pix de ' . brl((int) $p['valor_centavos']) . ' chegou?') ?>
        <?= botao_acao('cancelar_pedido', 'Cancelar', ['pedido_id' => $p['id']], 'btn mini perigo', 'Cancelar este pedido?') ?>
      </div></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif ?>

<h2>Últimos pagamentos confirmados</h2>
<?php if (!$pagos): ?><p class="suave">Nenhum pagamento confirmado ainda.</p><?php else: ?>
<div class="cartao tabela-rolagem"><table>
  <thead><tr><th>Aluno</th><th>Curso</th><th>Valor</th><th>Pago em</th></tr></thead>
  <tbody><?php foreach ($pagos as $p): ?><tr><td><?= e($p['nome']) ?></td><td><?= e($p['titulo']) ?></td><td><?= e(brl((int) $p['valor_centavos'])) ?></td><td><?= e(data_br($p['pago_em'])) ?></td></tr><?php endforeach ?></tbody>
</table></div>
<?php endif ?>
<?php rodape();
