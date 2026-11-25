<?php
$u = exigir_usuario();
$o = um('SELECT * FROM organizacoes WHERE id = ?', [get('id')]);
if (!$o || (!eh_admin($u) && !isset(orgs_geridas($u)[$o['id']]))) nao_encontrado();
$d = um('SELECT * FROM dados_escola WHERE org_id = ?', [$o['id']]) ?? [];
$v = fn($k) => e($d[$k] ?? '');
$admin = eh_admin($u);
cabecalho('Cadastro da escola');
?>
<p class="pequeno"><a href="<?= e(url($admin ? 'admin' : 'escola', $admin ? [] : ['org' => $o['id']])) ?>">← Voltar</a> · <a href="<?= e(url('escola', ['org' => $o['id']])) ?>">Painel da escola</a></p>
<h1>Cadastro: <?= e($o['nome']) ?></h1>

<?= abrir_form('salvar_escola', 'pilha cartao', ['org_id' => $o['id']]) ?>
  <h3>Dados da escola</h3>
  <div class="campos">
    <label>Nome<input name="nome" value="<?= e($o['nome']) ?>" required></label>
    <label>Razão social<input name="razao_social" value="<?= $v('razao_social') ?>"></label>
    <label>CNPJ<input name="documento" inputmode="numeric" value="<?= e(formatar_doc($o['documento'] ?? '')) ?>"></label>
    <label>Telefone<input name="telefone" type="tel" value="<?= $v('telefone') ?>"></label>
    <label>E-mail<input name="email" type="email" value="<?= $v('email') ?>"></label>
  </div>
  <?= campos_endereco($d) ?>
  <fieldset class="pilha"><legend>Contato pedagógico</legend>
    <div class="campos">
      <label>Nome<input name="contato_nome" value="<?= $v('contato_nome') ?>"></label>
      <label>E-mail<input name="contato_email" type="email" value="<?= $v('contato_email') ?>"></label>
      <label>Telefone<input name="contato_telefone" type="tel" value="<?= $v('contato_telefone') ?>"></label>
    </div>
  </fieldset>
  <?php if ($admin): ?>
  <fieldset class="pilha"><legend>Financeiro e contrato</legend>
    <div class="campos">
      <label>Contato financeiro<input name="fin_nome" value="<?= $v('fin_nome') ?>"></label>
      <label>E-mail financeiro<input name="fin_email" type="email" value="<?= $v('fin_email') ?>"></label>
      <label>Telefone financeiro<input name="fin_telefone" type="tel" value="<?= $v('fin_telefone') ?>"></label>
    </div>
    <div class="campos">
      <label>Início do contrato<input name="fin_inicio" type="date" value="<?= $v('fin_inicio') ?>"></label>
      <label>Fim do contrato<input name="fin_fim" type="date" value="<?= $v('fin_fim') ?>"></label>
    </div>
    <?= campos_financeiro($d, 'Valor mensal do contrato') ?>
    <label class="marcar"><input type="checkbox" name="ativa" <?= $o['ativa'] ? 'checked' : '' ?>> <span>Escola ativa (desmarcar bloqueia o acesso da equipe e dos alunos pela escola)</span></label>
  </fieldset>
  <?php endif ?>
  <div><button class="btn">Salvar cadastro</button></div>
</form>
<?php rodape();
