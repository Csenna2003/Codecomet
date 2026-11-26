<?php
$u = exigir_usuario();
$aula = um('SELECT a.*, m.curso_id, m.titulo AS modulo_titulo FROM aulas a JOIN modulos m ON m.id = a.modulo_id WHERE a.id = ?', [get('id')]);
if (!$aula) nao_encontrado();
$curso = carregar_curso('id', $aula['curso_id']);
$modo = modo_acesso($u, $curso['id']);
if (!$modo) ir('curso', ['slug' => $curso['slug']]);
$aluno = $modo === 'aluno';
$est = $aluno ? estado_curso($u['id'], $curso) : null;
$meu = $est['por_aula'][$aula['id']] ?? null;
if ($aluno && empty($meu['liberada'])) ir('curso', ['slug' => $curso['slug']]);

$aulas = aulas_do_curso($curso);
$i = array_search($aula['id'], array_column($aulas, 'id'), true);
$ex = $aulas[$i]['exercicio'];
$anterior = $aulas[$i - 1] ?? null;
$seguinte = $aulas[$i + 1] ?? null;
$seguinteAberta = $seguinte && (!$aluno || !empty($est['por_aula'][$seguinte['id']]['liberada']));
$video = $aula['video_url'] ? embed_video($aula['video_url']) : null;
$sim = $aula['simulador_url'] ? embed_simulador($aula['simulador_url']) : null;
$perguntas = json_decode((string) $aula['quiz'], true) ?: [];
// Notas do professor: só para a equipe (admin, professores e coordenação), nunca para quem é só aluno.
$notas = eh_equipe($u) ? (string) valor('SELECT texto FROM aula_notas WHERE aula_id = ?', [$aula['id']]) : '';

$entregas = [];
if ($aluno && $ex) {
    $entregas = todos('SELECT * FROM entregas WHERE exercicio_id = ? AND usuario_id = ? ORDER BY tentativa DESC', [$ex['id'], $u['id']]);
    foreach ($entregas as &$en) $en['revisao'] = um('SELECT * FROM revisoes WHERE entrega_id = ? ORDER BY criado_em DESC LIMIT 1', [$en['id']]);
    unset($en);
}
$ultima = $entregas[0] ?? null;
$esgotou = $ex && $ex['max_tentativas'] && count($entregas) >= (int) $ex['max_tentativas'];
$podeEnviar = $aluno && $ex && (!$ultima || ($ultima['status'] === 'PRECISA_AJUSTE' && !$esgotou));
$rotuloLink = ['LINK_SIMULADOR' => 'Link do seu projeto no Tinkercad ou no Wokwi', 'LINK_VIDEO' => 'Link do projeto ou de um vídeo mostrando funcionando (Scratch, Wokwi, Tinkercad, YouTube, Google Drive…)', 'CODIGO' => 'Link do projeto (opcional)', 'ARQUIVO' => 'Link do projeto ou de um vídeo (opcional)'];
$arqsEntregas = arquivos_das_entregas(array_column($entregas, 'id'));
$tipoEnt = $ex['tipo_entrega'] ?? '';
$rotuloArquivos = ['ARQUIVO' => 'Fotos ou arquivos do projeto', 'LINK_VIDEO' => 'Fotos do projeto (se não tiver link)'][$tipoEnt] ?? 'Fotos ou arquivos (opcional)';

cabecalho($aula['titulo']);
?>
<p class="pequeno"><a href="<?= e(url('curso', ['slug' => $curso['slug']])) ?>">← <?= e($curso['titulo']) ?></a> · <?= e($aula['modulo_titulo']) ?></p>
<div class="linha">
  <h1><?= e($aula['titulo']) ?></h1>
  <span><span class="etiqueta"><?= e(ROTULO_TIPO_AULA[$aula['tipo']]) ?></span> <?php if (!empty($meu['concluida'])): ?><span class="etiqueta ok">Concluída</span><?php endif ?></span>
</div>
<?php if (!$aluno): ?><p class="aviso alerta">Visualização da equipe: o progresso não é registrado.<?php if (eh_admin($u)): ?> <a href="<?= e(url('editar_aula', ['id' => $aula['id']])) ?>">Editar esta aula</a><?php endif ?></p><?php endif ?>

<?php if ($video): ?>
  <iframe class="quadro video" src="<?= e($video) ?>" title="<?= e($aula['titulo']) ?>" allow="accelerometer; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
<?php endif ?>

<?php if ($aula['conteudo'] !== ''): ?><div class="cartao conteudo-aula"><?= render_conteudo($aula['conteudo']) ?></div><?php endif ?>

<?php if ($notas): ?>
  <details class="cartao notas-professor">
    <summary>Notas do professor (só a equipe vê)</summary>
    <div class="conteudo-aula"><?= render_conteudo($notas) ?></div>
  </details>
<?php endif ?>

<?php if ($sim): ?>
  <section>
    <h2>Simulador (<?= e($sim['plataforma']) ?>)</h2>
    <iframe class="quadro simulador" src="<?= e($sim['embed']) ?>" title="Simulador da aula" loading="lazy"
      sandbox="allow-scripts allow-same-origin allow-popups allow-forms allow-downloads"></iframe>
    <p class="pequeno suave">Para guardar o seu trabalho, abra o projeto na sua conta e salve uma cópia:
      <a href="<?= e($sim['original']) ?>" target="_blank" rel="noopener noreferrer">abrir o simulador em nova aba ↗</a></p>
  </section>
<?php endif ?>

<?php if ($aula['tipo'] === 'QUIZ'): ?>
  <?= abrir_form('responder_quiz', 'pilha', ['aula_id' => $aula['id']]) ?>
  <?php foreach ($perguntas as $n => $p): ?>
    <fieldset>
      <legend><?= $n + 1 ?>. <?= e($p['pergunta']) ?></legend>
      <?php foreach ($p['opcoes'] as $k => $o): ?>
        <label class="marcar"><input type="radio" name="q<?= $n ?>" value="<?= $k ?>" required> <span><?= e($o) ?></span></label>
      <?php endforeach ?>
    </fieldset>
  <?php endforeach ?>
  <?php if ($aluno): ?><div><button class="btn"><?= !empty($meu['concluida']) ? 'Responder de novo' : 'Enviar respostas' ?></button></div><?php endif ?>
  </form>
<?php endif ?>

<?php if ($ex): ?>
  <section>
    <h2>Seu desafio</h2>
    <div class="cartao">
      <div class="conteudo-aula"><?= render_conteudo($ex['enunciado']) ?></div>
      <h3>Como você será avaliado</h3>
      <ul><?php foreach ($ex['rubrica'] as $r): ?><li><?= e($r) ?></li><?php endforeach ?></ul>
      <?php if ($ex['max_tentativas']): ?><p class="pequeno suave">Tentativas: <?= count($entregas) ?> de <?= (int) $ex['max_tentativas'] ?></p><?php endif ?>
    </div>

    <?php foreach ($entregas as $en):
        $cls = $en['status'] === 'APROVADA' ? 'ok' : ($en['status'] === 'PRECISA_AJUSTE' ? 'erro' : 'alerta');
        $rv = $en['revisao'];
        $atendidos = $rv ? (json_decode($rv['criterios'], true) ?: []) : []; ?>
      <div class="cartao">
        <div class="linha"><strong>Tentativa <?= (int) $en['tentativa'] ?> · enviada em <?= e(data_br($en['enviado_em'])) ?></strong><span class="etiqueta <?= $cls ?>"><?= e(ROTULO_ENTREGA[$en['status']]) ?></span></div>
        <?php if ($en['link']): ?><p class="pequeno"><a href="<?= e($en['link']) ?>" target="_blank" rel="noopener noreferrer"><?= e($en['link']) ?></a></p><?php endif ?>
        <?= mostrar_arquivos($arqsEntregas[$en['id']] ?? []) ?>
        <?php if ($rv): ?>
          <p>Nota: <strong><?= (int) $rv['nota'] ?></strong> de <?= (int) $ex['nota_maxima'] ?></p>
          <?php if ($rv['comentario'] !== ''): ?><p class="texto-aula">💬 <?= e($rv['comentario']) ?></p><?php endif ?>
          <ul class="pequeno"><?php foreach ($ex['rubrica'] as $r): ?><li><?= in_array($r, $atendidos, true) ? '✅' : '⬜' ?> <?= e($r) ?></li><?php endforeach ?></ul>
        <?php endif ?>
      </div>
    <?php endforeach ?>

    <?php if ($podeEnviar): ?>
      <?= abrir_form('enviar_entrega', 'pilha cartao', ['aula_id' => $aula['id']], true) ?>
        <h3><?= $ultima ? 'Reenviar com os ajustes' : 'Enviar meu projeto' ?></h3>
        <label><?= e($rotuloLink[$tipoEnt]) ?><input name="link" type="url" placeholder="https://" <?= $tipoEnt === 'LINK_SIMULADOR' ? 'required' : '' ?>></label>
        <label><?= e($rotuloArquivos) ?>
          <input type="file" name="arquivos[]" multiple data-reduzir-fotos data-max="<?= ARQ_MAX_QTD ?>" data-limite="<?= limite_arquivo() ?>"
            accept="image/*,.pdf,.ino,.py,.txt,.c,.cpp,.h,.sb3,.zip" <?= $tipoEnt === 'ARQUIVO' ? 'required' : '' ?>>
          <span class="pequeno suave">Até <?= ARQ_MAX_QTD ?> arquivos. No celular dá para tirar a foto na hora. As fotos são reduzidas automaticamente; outros arquivos até <?= e(tamanho_legivel(limite_arquivo())) ?> cada.</span>
          <span class="pequeno" data-fotos-status aria-live="polite"></span></label>
        <label><?= $ex['tipo_entrega'] === 'CODIGO' ? 'Código do programa' : 'Código do programa (opcional, mas recomendado)' ?>
          <textarea class="codigo" name="codigo" rows="10" <?= $ex['tipo_entrega'] === 'CODIGO' ? 'required' : '' ?>></textarea></label>
        <label>Recado para o professor (opcional)<textarea name="observacao" rows="2"></textarea></label>
        <div><button class="btn">Enviar para correção</button></div>
      </form>
    <?php endif ?>
    <?php if ($aluno && $ultima && $ultima['status'] === 'PRECISA_AJUSTE' && $esgotou): ?><p class="aviso erro">Você usou todas as tentativas. Fale com seu professor.</p><?php endif ?>
  </section>
<?php endif ?>

<div class="linha" style="margin-top:1.5rem">
  <?php if ($anterior): ?><a class="btn sec" href="<?= e(url('aula', ['id' => $anterior['id']])) ?>">← Aula anterior</a><?php else: ?><span></span><?php endif ?>
  <?php if ($aluno && empty($meu['concluida']) && !in_array($aula['tipo'], ['QUIZ', 'EXERCICIO'], true)): ?>
    <?= botao_acao('marcar_concluida', 'Marcar como concluída', ['aula_id' => $aula['id']], 'btn') ?>
  <?php endif ?>
  <?php if ($seguinteAberta): ?>
    <a class="btn" href="<?= e(url('aula', ['id' => $seguinte['id']])) ?>">Próxima aula →</a>
  <?php elseif ($seguinte): ?>
    <span class="suave pequeno">Conclua esta aula para liberar a próxima 🔒</span>
  <?php else: ?>
    <a class="btn sec" href="<?= e(url('curso', ['slug' => $curso['slug']])) ?>">Voltar ao curso</a>
  <?php endif ?>
</div>
<?php rodape();
