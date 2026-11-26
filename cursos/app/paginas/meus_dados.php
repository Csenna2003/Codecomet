<?php
$u = exigir_usuario();
$d = um('SELECT * FROM dados_aluno WHERE usuario_id = ?', [$u['id']]) ?? [];
cabecalho('Meus dados');
?>
<h1>Meus dados</h1>
<?php if (eh_professor_cadastrado($u)): ?><p><a class="btn sec mini" href="<?= e(url('perfil_professor')) ?>">Meu perfil de professor (competências e disponibilidade)</a></p><?php endif ?>
<?= abrir_form('salvar_meus_dados') ?>
  <div class="campos">
    <label>Nome completo<input name="nome" value="<?= e($u['nome']) ?>" required></label>
    <?php if (tem_email_real($u['email'])): ?>
      <label>E-mail<input name="email" type="email" value="<?= e($u['email']) ?>" required></label>
    <?php else: ?>
      <label><span>E-mail (opcional; hoje você entra com o usuário <strong><?= e(login_de($u['email'])) ?></strong>)</span><input name="email" type="email" placeholder="seu@email.com"></label>
    <?php endif ?>
    <label>Telefone / WhatsApp<input name="telefone" type="tel" value="<?= e($d['telefone'] ?? '') ?>"></label>
    <label>Data de nascimento<input name="nascimento" type="date" value="<?= e($u['nascimento'] ?? '') ?>"></label>
  </div>
  <?= campos_endereco($d) ?>
  <fieldset class="pilha"><legend>Responsável (para alunos menores de 18 anos)</legend>
    <div class="campos">
      <label>Nome<input name="responsavel_nome" value="<?= e($u['responsavel_nome'] ?? '') ?>"></label>
      <label>E-mail<input name="responsavel_email" type="email" value="<?= e($u['responsavel_email'] ?? '') ?>"></label>
      <label>Telefone / WhatsApp<input name="responsavel_telefone" type="tel" value="<?= e($d['responsavel_telefone'] ?? '') ?>"></label>
    </div>
  </fieldset>
  <div><button class="btn">Salvar meus dados</button></div>
</form>

<h2>Alterar senha</h2>
<?= abrir_form('alterar_senha', 'cartao campos') ?>
  <label>Senha atual<input name="atual" type="password" autocomplete="current-password" required></label>
  <label>Nova senha (mínimo de 8 caracteres)<input name="nova" type="password" minlength="8" autocomplete="new-password" required></label>
  <div><button class="btn sec">Alterar senha</button></div>
</form>
<?php rodape();
