<?php
if (usuario_atual()) ir('painel');
cabecalho('Esqueci minha senha', true);
?>
<h1>Esqueci minha senha</h1>
<p class="suave">Informe o e-mail da sua conta. Enviaremos um link para você criar uma nova senha.</p>
<?= abrir_form('esqueci_senha') ?>
  <label>E-mail<input name="email" type="email" autocomplete="email" required></label>
  <button class="btn">Enviar link</button>
</form>
<p class="pequeno suave">Não recebeu? Confira a caixa de spam ou peça ao seu professor um link de nova senha.</p>
<p><a href="<?= e(url('entrar')) ?>">Voltar para a entrada</a></p>
<?php rodape();
