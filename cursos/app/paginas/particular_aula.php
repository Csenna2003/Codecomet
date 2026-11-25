<?php
$u = exigir_usuario();
$a = carregar_aula_particular(get('id'));
if (!$a || !pode_ver_aluno($u, $a['aluno_id'])) nao_encontrado();
$podeGerir = $a['professor_id'] === $u['id'] || eh_admin($u);
$link = $podeGerir ? ($_SESSION['link_confirmacao'][$a['id']] ?? null) : null;
// Só mostra o link guardado neste aparelho se ele ainda for o válido (pode ter sido trocado em outro).
if ($link) {
    parse_str((string) parse_url($link, PHP_URL_QUERY), $qs);
    if (!$a['token_hash'] || !hash_equals($a['token_hash'], hash('sha256', (string) ($qs['t'] ?? '')))) $link = null;
}
cabecalho('Aula particular');
?>
<p class="pequeno"><a href="<?= e(url($podeGerir ? 'particulares' : 'aluno', $podeGerir ? [] : ['id' => $a['aluno_id']])) ?>">← Voltar</a></p>
<h1>Aula particular de <?= e($a['aluno_nome']) ?></h1>
<p><span class="etiqueta <?= CLASSE_PARTICULAR[$a['status']] ?>"><?= e(ROTULO_PARTICULAR[$a['status']]) ?></span></p>

<div class="cartao">
  <dl class="dados">
    <dt>Aluno</dt><dd><a href="<?= e(url('aluno', ['id' => $a['aluno_id']])) ?>"><?= e($a['aluno_nome']) ?></a></dd>
    <dt>Professor</dt><dd><?= e($a['professor_nome']) ?></dd>
    <dt>Data</dt><dd><?= e(data_br($a['chegada'])) ?></dd>
    <dt>Chegada do professor</dt><dd class="hora"><?= e(hora($a['chegada'])) ?><?= $a['chegada_manual'] ? ' <span class="suave pequeno">(informada manualmente)</span>' : ' <span class="suave pequeno">(registrada no momento da chegada)</span>' ?></dd>
    <dt>Saída</dt><dd class="hora"><?= e(hora($a['saida'])) ?></dd>
    <dt>Duração</dt><dd><?= e(duracao($a['chegada'], $a['saida'])) ?></dd>
    <dt>Responsável</dt><dd><?= e($a['responsavel_nome']) ?></dd>
  </dl>
  <?php if ($a['conteudo']): ?><h3>Conteúdo apresentado</h3><p class="texto-aula"><?= e($a['conteudo']) ?></p><?php endif ?>
  <?php if ($a['observacoes']): ?><h3>Observações do professor</h3><p class="texto-aula"><?= e($a['observacoes']) ?></p><?php endif ?>
  <?php if ($a['resposta_em']): ?>
    <h3>Resposta do responsável</h3>
    <p><?= $a['status'] === 'CONFIRMADA' ? '✅ Confirmada' : '⚠️ Não confirmada' ?> por <strong><?= e($a['resposta_nome']) ?></strong> em <?= e(data_br($a['resposta_em'])) ?> às <?= e(hora($a['resposta_em'])) ?>.</p>
    <?php if ($a['resposta_comentario']): ?><p class="texto-aula">💬 <?= e($a['resposta_comentario']) ?></p><?php endif ?>
  <?php endif ?>
</div>

<?php if ($podeGerir && $a['status'] === 'AGUARDANDO'): ?>
  <section class="cartao">
    <h3>Confirmação do responsável</h3>
    <?php if ($link):
        $msg = mensagem_whatsapp($a, $link); ?>
      <p>Envie este link para <strong><?= e($a['responsavel_nome']) ?></strong>. Ele abre sem senha, mostra o conteúdo da aula e pede a confirmação.</p>
      <textarea class="codigo" id="link-confirmacao" readonly rows="3"><?= e($link) ?></textarea>
      <div class="linha" style="margin-top:.75rem">
        <a class="btn grande" href="https://wa.me/<?= e(telefone_whatsapp($a['responsavel_telefone'])) ?>?text=<?= e(rawurlencode($msg)) ?>" target="_blank" rel="noopener noreferrer">Enviar pelo WhatsApp</a>
        <button type="button" class="btn sec" data-copiar="link-confirmacao">Copiar link</button>
      </div>
      <p class="pequeno suave">O link deve ser aberto pelo responsável, no aparelho dele.</p>
    <?php else: ?>
      <p>O link de confirmação fica disponível só no aparelho em que ele foi gerado. Para enviar daqui, gere um novo link (o anterior deixa de funcionar).</p>
      <?= botao_acao('novo_link_particular', 'Gerar novo link de confirmação', ['aula_id' => $a['id']], 'btn sec') ?>
    <?php endif ?>
  </section>
<?php endif ?>
<?php rodape();
