<?php
exigir_admin();
$al = um('SELECT * FROM usuarios WHERE id = ?', [get('id')]);
if (!$al) nao_encontrado();
$d = um('SELECT * FROM dados_aluno WHERE usuario_id = ?', [$al['id']]) ?? [];
$v = fn($k) => e($d[$k] ?? '');
cabecalho('Cadastro do aluno');
?>
<p class="pequeno"><a href="<?= e(url('admin_alunos')) ?>">← Alunos</a> · <a href="<?= e(url('aluno', ['id' => $al['id']])) ?>">Ver acompanhamento</a></p>
<h1>Cadastro: <?= e($al['nome']) ?></h1>
<p class="suave pequeno">Cadastrado em <?= e(data_br($al['criado_em'])) ?><?= $d ? ' · atualizado em ' . e(data_br($d['atualizado_em'])) : '' ?></p>

<?= abrir_form('salvar_aluno', 'pilha cartao', ['usuario_id' => $al['id']]) ?>
  <h3>Dados do aluno</h3>
  <div class="campos">
    <label>Nome completo<input name="nome" value="<?= e($al['nome']) ?>" required></label>
    <label>E-mail (login)<input name="email" type="email" value="<?= e($al['email']) ?>" required></label>
    <label>Telefone / WhatsApp<input name="telefone" type="tel" value="<?= $v('telefone') ?>"></label>
    <label>Data de nascimento<input name="nascimento" type="date" value="<?= e($al['nascimento'] ?? '') ?>"></label>
    <label>CPF do aluno<input name="cpf" inputmode="numeric" value="<?= e(formatar_doc($d['cpf'] ?? '')) ?>"></label>
  </div>
  <?= campos_endereco($d) ?>

  <fieldset class="pilha"><legend>Responsável</legend>
    <div class="campos">
      <label>Nome<input name="responsavel_nome" value="<?= e($al['responsavel_nome'] ?? '') ?>"></label>
      <label>E-mail<input name="responsavel_email" type="email" value="<?= e($al['responsavel_email'] ?? '') ?>"></label>
      <label>Telefone / WhatsApp<input name="responsavel_telefone" type="tel" value="<?= $v('responsavel_telefone') ?>"></label>
      <label>CPF<input name="responsavel_cpf" inputmode="numeric" value="<?= e(formatar_doc($d['responsavel_cpf'] ?? '')) ?>"></label>
    </div>
  </fieldset>

  <fieldset class="pilha"><legend>Financeiro</legend>
    <div class="campos">
      <label>Responsável financeiro<input name="fin_nome" value="<?= $v('fin_nome') ?>" placeholder="Quem paga"></label>
      <label>CPF do responsável financeiro<input name="fin_cpf" inputmode="numeric" value="<?= e(formatar_doc($d['fin_cpf'] ?? '')) ?>"></label>
      <label>E-mail para cobrança<input name="fin_email" type="email" value="<?= $v('fin_email') ?>"></label>
      <label>Telefone para cobrança<input name="fin_telefone" type="tel" value="<?= $v('fin_telefone') ?>"></label>
    </div>
    <label>Endereço de cobrança (se for diferente do endereço acima)<input name="fin_endereco" value="<?= $v('fin_endereco') ?>"></label>
    <?= campos_financeiro($d, 'Mensalidade') ?>
  </fieldset>
  <div><button class="btn">Salvar cadastro</button></div>
</form>
<p class="pequeno suave">Os dados financeiros e os CPFs só aparecem para a administração da plataforma.</p>
<?php rodape();
