<?php
if (usuario_atual()) ir('painel');
$f = $_SESSION['form_cadastro'] ?? [];
$codigo = get('codigo') ?: ($f['codigo'] ?? '');
cabecalho('Criar conta', true);
?>
<h1>Criar conta</h1>
<?= abrir_form('cadastrar') ?>
  <label>Nome completo<input name="nome" autocomplete="name" value="<?= e($f['nome'] ?? '') ?>" required></label>
  <label>E-mail<input name="email" type="email" autocomplete="email" value="<?= e($f['email'] ?? '') ?>" required></label>
  <label>Senha (mínimo de 8 caracteres)<input name="senha" type="password" minlength="8" autocomplete="new-password" required></label>
  <label>Data de nascimento<input name="nascimento" type="date" value="<?= e($f['nascimento'] ?? '') ?>" required></label>
  <label>Código da escola (se você recebeu um)<input name="codigo" value="<?= e($codigo) ?>" autocapitalize="characters"></label>
  <fieldset class="pilha">
    <legend>Se o aluno tem menos de 18 anos</legend>
    <label>Nome do responsável<input name="responsavel_nome"></label>
    <label>E-mail do responsável<input name="responsavel_email" type="email"></label>
    <label class="marcar"><input type="checkbox" name="consentimento"> <span>Sou o responsável e autorizo o cadastro e o uso dos dados do aluno para fins educacionais.</span></label>
  </fieldset>
  <button class="btn">Criar conta</button>
</form>
<p>Já tem conta? <a href="<?= e(url('entrar')) ?>">Entrar</a></p>
<?php rodape();
