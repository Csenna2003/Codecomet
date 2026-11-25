<?php
exigir_admin();
$curso = carregar_curso('id', get('id'));
if (!$curso) nao_encontrado();
cabecalho('Editar curso');
?>
<p class="pequeno"><a href="<?= e(url('admin')) ?>">← Administração</a> · <a href="<?= e(url('curso', ['slug' => $curso['slug']])) ?>">Ver como aluno</a></p>
<h1><?= e($curso['titulo']) ?></h1>

<?= abrir_form('atualizar_curso', 'pilha cartao', ['curso_id' => $curso['id']]) ?>
  <label>Título<input name="titulo" value="<?= e($curso['titulo']) ?>" required></label>
  <label>Descrição<textarea name="descricao" rows="3" required><?= e($curso['descricao']) ?></textarea></label>
  <div class="campos">
    <label>Nível<select name="nivel"><?php foreach (ROTULO_NIVEL as $k => $v): ?><option value="<?= $k ?>" <?= $curso['nivel'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach ?></select></label>
    <label>Preço em R$ (0 = gratuito)<input name="preco" inputmode="decimal" value="<?= e(number_format($curso['preco_centavos'] / 100, 2, ',', '')) ?>" required></label>
    <label>Carga horária (h)<input name="carga_horaria" type="number" min="0" value="<?= (int) $curso['carga_horaria'] ?>" required></label>
    <label>Status<select name="status"><?php foreach (ROTULO_STATUS_CURSO as $k => $v): ?><option value="<?= $k ?>" <?= $curso['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach ?></select></label>
  </div>
  <div><button class="btn">Salvar curso</button></div>
</form>

<?php foreach ($curso['modulos'] as $m): ?>
  <section class="cartao">
    <h3>Módulo <?= (int) $m['ordem'] ?>: <?= e($m['titulo']) ?></h3>
    <ul class="aulas">
    <?php foreach ($m['aulas'] as $a): ?>
      <li>
        <span class="titulo"><?= (int) $a['ordem'] ?>. <a href="<?= e(url('aula', ['id' => $a['id']])) ?>"><?= e($a['titulo']) ?></a><?= $a['obrigatoria'] ? '' : ' <span class="suave pequeno">(opcional)</span>' ?></span>
        <span class="etiqueta"><?= e(ROTULO_TIPO_AULA[$a['tipo']]) ?></span>
        <?= botao_acao('excluir_aula', 'Excluir', ['aula_id' => $a['id']], 'btn mini perigo', 'Excluir esta aula?') ?>
      </li>
    <?php endforeach ?>
    </ul>
    <details style="margin-top:.75rem">
      <summary>Adicionar aula</summary>
      <?= abrir_form('criar_aula', 'pilha', ['modulo_id' => $m['id']]) ?>
        <div class="campos">
          <label>Título<input name="titulo" required></label>
          <label>Tipo<select name="tipo"><option value="VIDEO">Vídeo</option><option value="TEXTO">Leitura</option><option value="SIMULADOR">Simulador</option><option value="QUIZ">Quiz</option><option value="EXERCICIO">Exercício com entrega</option></select></label>
          <label>Duração (min)<input name="duracao_min" type="number" min="0"></label>
        </div>
        <label>Texto da aula<textarea name="conteudo" rows="4"></textarea></label>
        <label>Link do vídeo (YouTube ou Vimeo)<input name="video_url" type="url"></label>
        <label>Link do projeto no simulador (tinkercad.com/things/… ou wokwi.com/projects/…)<input name="simulador_url" type="url"></label>
        <fieldset class="pilha"><legend>Só para quiz</legend>
          <label>Perguntas — separe com uma linha em branco; marque a opção correta com * e as outras com -
            <textarea name="quiz" rows="5" placeholder="Qual pino liga o LED?&#10;* GPIO16&#10;- GPIO34"></textarea></label>
        </fieldset>
        <fieldset class="pilha"><legend>Só para exercício</legend>
          <label>Enunciado<textarea name="enunciado" rows="3"></textarea></label>
          <label>Critérios de avaliação (um por linha)<textarea name="rubrica" rows="3"></textarea></label>
          <div class="campos">
            <label>O aluno entrega<select name="tipo_entrega"><option value="LINK_SIMULADOR">Link do Tinkercad/Wokwi</option><option value="CODIGO">Código</option><option value="LINK_VIDEO">Link de vídeo (projeto físico)</option></select></label>
            <label>Limite de tentativas (opcional)<input name="max_tentativas" type="number" min="1"></label>
          </div>
        </fieldset>
        <label class="marcar"><input type="checkbox" name="opcional"> <span>Aula opcional (não bloqueia as próximas nem o certificado)</span></label>
        <div><button class="btn sec">Adicionar aula</button></div>
      </form>
    </details>
  </section>
<?php endforeach ?>

<?= abrir_form('criar_modulo', 'cartao campos', ['curso_id' => $curso['id']]) ?>
  <label>Novo módulo<input name="titulo" required></label>
  <div><button class="btn sec">Adicionar módulo</button></div>
</form>
<?php rodape();
