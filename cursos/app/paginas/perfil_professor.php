<?php
$u = exigir_usuario();
$id = get('id') ?: $u['id'];
if ($id !== $u['id'] && !eh_admin($u)) nao_encontrado();
$p = um('SELECT p.*, us.nome, us.email FROM professores p JOIN usuarios us ON us.id = p.usuario_id WHERE p.usuario_id = ?', [$id]);
if (!$p) nao_encontrado();
$admin = eh_admin($u);
$comp = json_decode((string) $p['competencias'], true) ?: [];
$disp = json_decode((string) $p['disponibilidade'], true) ?: [];
$v = fn($k) => e($p[$k] ?? '');
cabecalho('Perfil do professor');
?>
<p class="pequeno"><a href="<?= e(url($admin ? 'professores' : 'painel')) ?>">← Voltar</a></p>
<h1><?= $id === $u['id'] ? 'Meu perfil de professor' : 'Professor: ' . e($p['nome']) ?></h1>
<p class="suave pequeno"><?= e($p['email']) ?><?= $id === $u['id'] ? ' · nome e e-mail se alteram em <a href="' . e(url('meus_dados')) . '">Meus dados</a>' : '' ?></p>

<?= abrir_form('salvar_professor', 'pilha cartao', ['usuario_id' => $id]) ?>
  <h3>Contato e região</h3>
  <div class="campos">
    <label>Telefone / WhatsApp<input name="telefone" type="tel" value="<?= $v('telefone') ?>"></label>
    <label>Modalidade<select name="modalidade"><?= opcoes(MODALIDADES, $p['modalidade']) ?></select></label>
    <label>Bairros / regiões que atende<input name="regioes" value="<?= $v('regioes') ?>" placeholder="Ex.: Centro, Jardim Aquarius"></label>
  </div>
  <?= campos_endereco($p) ?>

  <h3>Formação e competências</h3>
  <label>Formação<input name="formacao" value="<?= $v('formacao') ?>" placeholder="Ex.: Engenharia de Computação (cursando), técnico em eletrônica"></label>
  <fieldset><legend>Competências</legend>
    <div class="grade-marcas">
    <?php foreach (COMPETENCIAS as $c): ?>
      <label class="marcar"><input type="checkbox" name="competencias[]" value="<?= e($c) ?>" <?= in_array($c, $comp, true) ? 'checked' : '' ?>> <span><?= e($c) ?></span></label>
    <?php endforeach ?>
    </div>
  </fieldset>
  <label>Outras competências<input name="outras_competencias" value="<?= $v('outras_competencias') ?>"></label>
  <label>Apresentação (aparece para a administração)<textarea name="bio" rows="3"><?= $v('bio') ?></textarea></label>

  <h3>Disponibilidade</h3>
  <div class="tabela-rolagem"><table class="disponibilidade">
    <thead><tr><th></th><?php foreach (PERIODOS as $per): ?><th><?= e($per) ?></th><?php endforeach ?></tr></thead>
    <tbody><?php foreach (DIAS as $dk => $dia): ?>
      <tr><th><?= e($dia) ?></th>
        <?php foreach (PERIODOS as $pk => $_): ?><td><input type="checkbox" aria-label="<?= e("$dia, $pk") ?>" name="disp[<?= $dk ?>][<?= $pk ?>]" <?= in_array("$dk-$pk", $disp, true) ? 'checked' : '' ?>></td><?php endforeach ?>
      </tr>
    <?php endforeach ?></tbody>
  </table></div>
  <label>Observações sobre horários<input name="disponibilidade_obs" value="<?= $v('disponibilidade_obs') ?>" placeholder="Ex.: terças só a partir das 15h"></label>

  <h3>Dados para pagamento</h3>
  <div class="campos">
    <label>CPF<input name="cpf" inputmode="numeric" value="<?= e(formatar_doc($p['cpf'] ?? '')) ?>"></label>
    <label>Chave Pix<input name="chave_pix" value="<?= $v('chave_pix') ?>"></label>
    <?php if ($admin): ?>
      <label>Valor da hora-aula (R$)<input name="valor_hora" inputmode="decimal" placeholder="0,00" value="<?= e(valor_br($p['valor_hora_centavos'] !== null ? (int) $p['valor_hora_centavos'] : null)) ?>"></label>
    <?php else: ?>
      <p>Valor da hora-aula: <strong><?= $p['valor_hora_centavos'] !== null ? e(reais((int) $p['valor_hora_centavos'])) : 'a definir pela administração' ?></strong></p>
    <?php endif ?>
  </div>
  <?php if ($admin): ?><label class="marcar"><input type="checkbox" name="ativo" <?= $p['ativo'] ? 'checked' : '' ?>> <span>Professor ativo</span></label><?php endif ?>
  <div><button class="btn">Salvar perfil</button></div>
</form>
<?php rodape();
