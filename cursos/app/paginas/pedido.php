<?php
$u = exigir_usuario();
$p = um('SELECT p.*, c.titulo, c.slug FROM pedidos p JOIN cursos c ON c.id = p.curso_id WHERE p.id = ?', [get('id')]);
if (!$p || $p['usuario_id'] !== $u['id']) nao_encontrado();
if ($p['status'] === 'PAGO') ir('curso', ['slug' => $p['slug']]);
$pix = config_pix();
$codigo = $pix ? pix_copia_e_cola($pix, (int) $p['valor_centavos'], $p['id']) : null;
cabecalho('Pagamento por Pix', true);
?>
<h1>Pagamento por Pix</h1>
<div class="cartao">
  <p><strong><?= e($p['titulo']) ?></strong></p>
  <p>Valor: <strong><?= e(brl((int) $p['valor_centavos'])) ?></strong></p>
  <?php if ($p['status'] === 'CANCELADO'): ?>
    <p class="aviso erro">Este pedido foi cancelado. Volte ao curso para gerar um novo.</p>
  <?php elseif (!$codigo): ?>
    <p class="aviso erro">O pagamento está temporariamente indisponível. Fale com a nossa equipe.</p>
  <?php elseif ($p['avisou_pago_em']): ?>
    <p class="aviso ok">Recebemos o seu aviso! Assim que o Pix for conferido, o curso aparece em “Meu painel”. Isso costuma levar até 1 dia útil.</p>
  <?php else: ?>
    <ol>
      <li>Abra o aplicativo do seu banco e escolha <strong>Pix → Ler QR Code</strong> ou <strong>Pix copia e cola</strong>.</li>
      <li>Confira o valor e o nome <strong><?= e($pix['nome']) ?></strong> antes de confirmar.</li>
      <li>Depois de pagar, clique em <strong>Já paguei</strong>.</li>
    </ol>
    <div class="qr" data-qr="<?= e($codigo) ?>" role="img" aria-label="QR Code do Pix"></div>
    <label>Pix copia e cola<textarea class="codigo" id="pix-codigo" readonly rows="6"><?= e($codigo) ?></textarea></label>
    <div class="linha" style="margin-top:.75rem">
      <button type="button" class="btn sec" data-copiar="pix-codigo">Copiar código Pix</button>
      <?= botao_acao('avisar_pagamento', 'Já paguei', ['pedido_id' => $p['id']], 'btn') ?>
    </div>
  <?php endif ?>
  <p class="suave pequeno" style="margin-top:1rem">Pedido <?= e($p['id']) ?></p>
</div>
<a href="<?= e(url('curso', ['slug' => $p['slug']])) ?>">Voltar ao curso</a>
<script src="assets/qrcode.js" defer></script>
<?php rodape();
