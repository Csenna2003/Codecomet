<?php
exigir_admin();
$a = aula_com_curso(get('id'));
if (!$a) nao_encontrado();
$curso = um('SELECT id, titulo FROM cursos WHERE id = ?', [$a['curso_id']]);
$modulos = todos('SELECT id, ordem, titulo FROM modulos WHERE curso_id = ? ORDER BY ordem', [$a['curso_id']]);
$ex = um('SELECT * FROM exercicios WHERE aula_id = ?', [$a['id']]);
$temEntregas = $ex && valor('SELECT 1 FROM entregas WHERE exercicio_id = ?', [$ex['id']]);
$notas = (string) valor('SELECT texto FROM aula_notas WHERE aula_id = ?', [$a['id']]);
$quiz = json_decode((string) $a['quiz'], true) ?: [];
cabecalho('Editar aula');
?>
<p class="pequeno"><a href="<?= e(url('admin_curso', ['id' => $curso['id']])) ?>">← <?= e($curso['titulo']) ?></a> · <a href="<?= e(url('aula', ['id' => $a['id']])) ?>">Ver aula</a></p>
<h1>Editar aula</h1>

<?= abrir_form('salvar_aula', 'pilha cartao', ['aula_id' => $a['id']]) ?>
  <div class="campos">
    <label style="grid-column: span 2">Título<input name="titulo" value="<?= e($a['titulo']) ?>" required></label>
    <label>Tipo<select name="tipo" <?= $temEntregas ? 'disabled' : '' ?>><?php foreach (ROTULO_TIPO_AULA as $k => $v): ?><option value="<?= $k ?>" <?= $a['tipo'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach ?></select>
      <?php if ($temEntregas): ?><input type="hidden" name="tipo" value="EXERCICIO"><?php endif ?></label>
    <label>Duração (min)<input name="duracao_min" type="number" min="0" value="<?= (int) $a['duracao_min'] ?>"></label>
  </div>
  <div class="campos">
    <label>Módulo<select name="modulo_id"><?php foreach ($modulos as $m): ?><option value="<?= e($m['id']) ?>" <?= $m['id'] === $a['modulo_id'] ? 'selected' : '' ?>>Módulo <?= (int) $m['ordem'] ?>: <?= e($m['titulo']) ?></option><?php endforeach ?></select></label>
    <label class="marcar"><input type="checkbox" name="opcional" <?= $a['obrigatoria'] ? '' : 'checked' ?>> <span>Aula opcional (não bloqueia as próximas nem o certificado)</span></label>
  </div>

  <label>Texto da aula<textarea name="conteudo" rows="22" class="editor"><?= e($a['conteudo']) ?></textarea></label>
  <details class="pequeno"><summary>Como formatar o texto</summary>
    <ul>
      <li><code>## Título</code> no começo da linha: subtítulo.</li>
      <li><code>- item</code>: lista; <code>1. passo</code>: lista numerada.</li>
      <li><code>[[Dica]] texto</code>: quadro de destaque. Rótulos com “Desafio” ou “Pergunta” ficam amarelos; com “Atenção”, “Regra” ou “Segurança”, vermelhos.</li>
      <li><code>**texto**</code>: negrito; <code>`código`</code>: código no meio da frase.</li>
      <li>Bloco de código: uma linha só com <code>```</code>, o código, e outra linha só com <code>```</code>.</li>
      <li>Deixe uma linha em branco entre parágrafos, itens e quadros.</li>
    </ul>
  </details>

  <div class="campos">
    <label>Link do vídeo (YouTube ou Vimeo)<input name="video_url" type="url" value="<?= e($a['video_url'] ?? '') ?>"></label>
    <label>Simulador: link do Tinkercad ou Wokwi, ou um simulador da plataforma<input name="simulador_url" list="sims-internos" inputmode="url" value="<?= e($a['simulador_url'] ?? '') ?>" placeholder="https://www.tinkercad.com/things/... ou simuladores/..."></label>
    <datalist id="sims-internos"><?php foreach (simuladores_internos() as $arq => $tit): ?><option value="<?= e($arq) ?>"><?= e($tit) ?></option><?php endforeach ?></datalist>
  </div>

  <fieldset class="pilha"><legend>Quiz (só para aula do tipo Quiz)</legend>
    <label>Perguntas — separe com uma linha em branco; marque a certa com * e as outras com -
      <textarea name="quiz" rows="6"><?= e(quiz_para_texto($quiz)) ?></textarea></label>
  </fieldset>

  <fieldset class="pilha"><legend>Exercício com entrega (só para aula do tipo Exercício)</legend>
    <?php if ($temEntregas): ?><p class="aviso alerta">Esta aula já tem entregas de alunos. Mudanças nos critérios valem para as próximas correções.</p><?php endif ?>
    <label>Enunciado<textarea name="enunciado" rows="5"><?= e($ex['enunciado'] ?? '') ?></textarea></label>
    <label>Critérios de avaliação (um por linha)<textarea name="rubrica" rows="5"><?= e(implode("\n", $ex ? (json_decode($ex['rubrica'], true) ?: []) : [])) ?></textarea></label>
    <div class="campos">
      <label>O aluno entrega<select name="tipo_entrega">
        <?php foreach (ROTULO_TIPO_ENTREGA as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($ex['tipo_entrega'] ?? 'LINK_SIMULADOR') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach ?></select></label>
      <label>Limite de tentativas (opcional)<input name="max_tentativas" type="number" min="1" value="<?= e($ex['max_tentativas'] ?? '') ?>"></label>
    </div>
  </fieldset>

  <label>Notas do professor (só a equipe vê; aceita a mesma formatação)<textarea name="notas" rows="8"><?= e($notas) ?></textarea></label>

  <div class="acoes">
    <button class="btn" name="depois" value="editar">Salvar</button>
    <button class="btn sec" name="depois" value="ver">Salvar e ver a aula</button>
  </div>
</form>
<?php rodape();
