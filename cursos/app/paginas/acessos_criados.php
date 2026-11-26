<?php
$u = exigir_usuario();
$d = $_SESSION['acessos_criados'] ?? null;
if (!$d || (!eh_admin($u) && !orgs_geridas($u))) ir('painel');
$pessoas = $d['pessoas'];

if (get('csv') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="acessos-' . date('Y-m-d-Hi') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // acentos certos no Excel
    fputcsv($out, ['Nome', 'Login', 'Senha provisória', 'Link de acesso', 'Observação'], ';');
    foreach ($pessoas as $p) fputcsv($out, [$p['nome'], $p['login'], $p['senha'] ?? '', url_acesso($p['login']), $p['obs']], ';');
    exit;
}
$tel = telefone_whatsapp((string) ($d['telefone'] ?? ''));
$todos = implode("\n\n", array_map(fn($p) => $p['senha'] ? texto_acesso($p['nome'], $p['login'], $p['senha']) : "{$p['nome']}: login {$p['login']} ({$p['obs']})", $pessoas));
cabecalho('Acessos criados');
?>
<div class="nao-imprimir">
  <h1>Acessos criados</h1>
  <p class="suave"><?= e($d['contexto']) ?> · <?= count($pessoas) ?> pessoa(s)</p>
  <p class="aviso alerta">Anote ou envie agora: por segurança, as senhas só aparecem nesta tela. Se perder, gere uma nova senha provisória no cadastro da pessoa.</p>
  <div class="acoes" style="margin-bottom:1rem">
    <button type="button" class="btn" data-imprimir>Imprimir cartões de acesso</button>
    <a class="btn sec" href="<?= e(url('acessos_criados', ['csv' => '1'])) ?>">Baixar planilha</a>
    <button type="button" class="btn sec" data-copiar="todos-acessos">Copiar tudo</button>
  </div>
  <textarea id="todos-acessos" class="codigo" rows="4" readonly><?= e($todos) ?></textarea>

  <div class="cartao tabela-rolagem" style="margin-top:1rem"><table>
    <thead><tr><th>Nome</th><th>Login</th><th>Senha provisória</th><th></th></tr></thead>
    <tbody><?php foreach ($pessoas as $p): ?>
      <tr><td><?= e($p['nome']) ?><?= $p['obs'] ? '<br><span class="suave pequeno">' . e($p['obs']) . '</span>' : '' ?></td>
        <td><code><?= e($p['login']) ?></code></td>
        <td><?= $p['senha'] ? '<code>' . e($p['senha']) . '</code>' : '—' ?></td>
        <td><?php if ($p['senha']): ?><a class="btn mini sec" target="_blank" rel="noopener noreferrer"
          href="https://wa.me/<?= e(count($pessoas) === 1 ? $tel : '') ?>?text=<?= e(rawurlencode(texto_acesso($p['nome'], $p['login'], $p['senha']))) ?>">WhatsApp</a><?php endif ?></td></tr>
    <?php endforeach ?></tbody>
  </table></div>
  <p><a href="<?= e(url(eh_admin($u) ? 'admin_alunos' : 'escola')) ?>">← Voltar</a></p>
</div>

<div class="cartoes-acesso">
<?php foreach ($pessoas as $p) if ($p['senha']): ?>
  <div class="cartao-acesso">
    <img src="assets/logo.png" alt="" width="120" height="50">
    <p class="nome"><?= e($p['nome']) ?></p>
    <p>Acesse: <strong><?= e(url_absoluta('entrar')) ?></strong></p>
    <p>Login: <code><?= e($p['login']) ?></code></p>
    <p>Senha: <code><?= e($p['senha']) ?></code></p>
    <p class="pequeno">No primeiro acesso, troque a senha em Meu painel → Meus dados.</p>
  </div>
<?php endif ?>
</div>
<?php rodape();
