<?php
if (usuario_atual()) ir('painel');
cabecalho('Entrar', true);
?>
<h1>Entrar</h1>
<?= abrir_form('entrar') ?>
  <label>E-mail ou usuário<input name="email" autocomplete="username" autocapitalize="none" value="<?= e(get('login')) ?>" required></label>
  <label>Senha<input name="senha" type="password" autocomplete="current-password" required></label>
  <button class="btn">Entrar</button>
</form>
<p><a href="<?= e(url('esqueci')) ?>">Esqueci minha senha</a></p>
<p>Ainda não tem conta? <a href="<?= e(url('cadastro')) ?>">Criar conta</a></p>
<?php rodape();
