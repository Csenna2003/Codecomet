<?php
$token = get('token');
$r = buscar_redefinicao($token);
cabecalho('Criar nova senha', true);
?>
<h1>Criar nova senha</h1>
<?php if ($r): ?>
  <?= abrir_form('redefinir_senha', 'pilha cartao', ['token' => $token]) ?>
    <p>Olá, <?= e(explode(' ', $r['nome'])[0]) ?>! Escolha uma nova senha.</p>
    <label>Nova senha (mínimo de 8 caracteres)<input name="senha" type="password" minlength="8" autocomplete="new-password" required></label>
    <button class="btn">Salvar nova senha</button>
  </form>
<?php else: ?>
  <p class="aviso erro">Este link não vale mais. Peça outro ao seu professor ou à nossa equipe.</p>
<?php endif ?>
<a href="<?= e(url('entrar')) ?>">Ir para a tela de entrada</a>
<?php rodape();
