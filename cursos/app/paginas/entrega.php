<?php
$u = exigir_usuario();
$f = filtro_entregas($u);
if (!$f) ir('painel');
$e = um(
    "SELECT e.*, u.nome AS aluno, o.nome AS org_nome, a.titulo AS aula, x.enunciado, x.rubrica, x.nota_maxima
     FROM entregas e JOIN usuarios u ON u.id = e.usuario_id LEFT JOIN organizacoes o ON o.id = e.org_id
     JOIN exercicios x ON x.id = e.exercicio_id JOIN aulas a ON a.id = x.aula_id
     WHERE e.id = ? AND {$f[0]}",
    array_merge([get('id')], $f[1])
);
if (!$e) nao_encontrado();
$rubrica = json_decode($e['rubrica'], true) ?: [];
$sim = $e['link'] ? embed_simulador($e['link']) : null;
$revisoes = todos('SELECT r.*, u.nome AS revisor FROM revisoes r JOIN usuarios u ON u.id = r.revisor_id WHERE r.entrega_id = ? ORDER BY r.criado_em DESC', [$e['id']]);
cabecalho('Corrigir entrega');
?>
<p class="pequeno"><a href="<?= e(url('professor')) ?>">← Correções</a></p>
<h1><?= e($e['aula']) ?></h1>
<p class="suave"><a href="<?= e(url('aluno', ['id' => $e['usuario_id']])) ?>"><?= e($e['aluno']) ?></a> · <?= e($e['org_nome'] ?? 'Venda direta') ?> · tentativa <?= (int) $e['tentativa'] ?> · enviada em <?= e(data_br($e['enviado_em'])) ?> · <?= e(ROTULO_ENTREGA[$e['status']]) ?></p>

<details class="cartao"><summary>Enunciado do exercício</summary><p class="texto-aula"><?= e($e['enunciado']) ?></p></details>

<h2>Entrega do aluno</h2>
<?php if ($e['link']): ?><p><a href="<?= e($e['link']) ?>" target="_blank" rel="noopener noreferrer"><?= e($e['link']) ?> ↗</a></p><?php endif ?>
<?php if ($sim): ?><iframe class="quadro simulador" src="<?= e($sim['embed']) ?>" title="Projeto do aluno" loading="lazy" sandbox="allow-scripts allow-same-origin allow-popups allow-forms"></iframe><?php endif ?>
<?= mostrar_arquivos(arquivos_das_entregas([$e['id']])[$e['id']] ?? [], true) ?>
<?php if ($e['codigo']): ?><pre><?= e($e['codigo']) ?></pre><?php endif ?>
<?php if ($e['observacao']): ?><p class="cartao texto-aula">Recado do aluno: <?= e($e['observacao']) ?></p><?php endif ?>

<?php if ($e['status'] === 'ENVIADA'): ?>
  <?= abrir_form('revisar_entrega', 'pilha cartao', ['entrega_id' => $e['id']]) ?>
    <h3>Correção</h3>
    <fieldset><legend>Critérios atendidos</legend>
      <?php foreach ($rubrica as $i => $r): ?><label class="marcar"><input type="checkbox" name="c<?= $i ?>"> <span><?= e($r) ?></span></label><?php endforeach ?>
    </fieldset>
    <div class="campos">
      <label>Nota (0 a <?= (int) $e['nota_maxima'] ?>)<input name="nota" type="number" min="0" max="<?= (int) $e['nota_maxima'] ?>" step="1" required></label>
      <label>Decisão<select name="decisao" required><option value="" selected disabled>Escolha…</option><option value="APROVADA">Aprovar</option><option value="PRECISA_AJUSTE">Pedir ajuste</option></select></label>
    </div>
    <label>Comentário para o aluno<textarea name="comentario" rows="4"></textarea></label>
    <div><button class="btn">Registrar correção</button></div>
  </form>
<?php else: foreach ($revisoes as $r): $at = json_decode($r['criterios'], true) ?: []; ?>
  <div class="cartao">
    <strong><?= e(ROTULO_ENTREGA[$r['decisao']]) ?> · nota <?= (int) $r['nota'] ?></strong>
    <p class="suave pequeno">por <?= e($r['revisor']) ?> em <?= e(data_br($r['criado_em'])) ?></p>
    <?php if ($r['comentario'] !== ''): ?><p class="texto-aula"><?= e($r['comentario']) ?></p><?php endif ?>
    <ul class="pequeno"><?php foreach ($rubrica as $c): ?><li><?= in_array($c, $at, true) ? '✅' : '⬜' ?> <?= e($c) ?></li><?php endforeach ?></ul>
  </div>
<?php endforeach; endif ?>
<?php rodape();
