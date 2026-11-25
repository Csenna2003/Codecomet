<?php
// Página pública do responsável: acesso só pelo link secreto enviado pelo professor.
$token = get('t');
$a = preg_match('/^[0-9a-f]{64}$/', $token)
    ? um('SELECT ap.id FROM aulas_particulares ap WHERE ap.token_hash = ?', [hash('sha256', $token)])
    : null;
$a = $a ? carregar_aula_particular($a['id']) : null;
cabecalho('Confirmar aula', true);
?>
<h1>Confirmação de aula particular</h1>
<?php if (!$a): ?>
  <p class="aviso erro">Este link não é válido ou foi substituído por um mais novo. Peça ao professor que envie de novo.</p>
<?php else: ?>
  <div class="cartao">
    <dl class="dados">
      <dt>Aluno</dt><dd><?= e($a['aluno_nome']) ?></dd>
      <dt>Professor</dt><dd><?= e($a['professor_nome']) ?></dd>
      <dt>Data</dt><dd><?= e(data_br($a['chegada'])) ?></dd>
      <dt>Chegada</dt><dd class="hora"><?= e(hora($a['chegada'])) ?><?= $a['chegada_manual'] ? ' <span class="suave pequeno">(informada pelo professor)</span>' : '' ?></dd>
      <dt>Saída</dt><dd class="hora"><?= e(hora($a['saida'])) ?></dd>
      <dt>Duração</dt><dd><?= e(duracao($a['chegada'], $a['saida'])) ?></dd>
    </dl>
    <h3>Conteúdo apresentado</h3>
    <p class="texto-aula"><?= e($a['conteudo']) ?></p>
    <?php if ($a['observacoes']): ?><h3>Observações do professor</h3><p class="texto-aula"><?= e($a['observacoes']) ?></p><?php endif ?>
  </div>
  <?php if ($a['status'] === 'AGUARDANDO'): ?>
    <?= abrir_form('responder_confirmacao', 'pilha cartao', ['t' => $token]) ?>
      <label>Seu nome<input name="nome" value="<?= e($a['responsavel_nome']) ?>" required></label>
      <label>Comentário (obrigatório se algo não conferir)<textarea name="comentario" rows="3"></textarea></label>
      <div class="linha">
        <button class="btn grande" name="decisao" value="CONFIRMADA">Confirmo que a aula aconteceu</button>
        <button class="btn perigo" name="decisao" value="CONTESTADA">Algo não confere</button>
      </div>
    </form>
  <?php else: ?>
    <p class="aviso <?= CLASSE_PARTICULAR[$a['status']] ?>"><?= $a['status'] === 'CONFIRMADA' ? 'Aula confirmada' : 'Aula marcada como não conferida' ?> por <?= e($a['resposta_nome']) ?> em <?= e(data_br($a['resposta_em'])) ?> às <?= e(hora($a['resposta_em'])) ?>.</p>
  <?php endif ?>
<?php endif ?>
<?php rodape();
