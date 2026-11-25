<?php
exigir_admin();
$busca = get('q');
$sit = isset(SITUACOES_FIN[get('situacao')]) ? get('situacao') : '';
$where = ['u.admin = 0'];
$p = [];
if ($busca !== '') {
    $where[] = '(u.nome LIKE ? OR u.email LIKE ?)';
    array_push($p, "%$busca%", "%$busca%");
}
if ($sit !== '') {
    $where[] = 'd.fin_situacao = ?';
    $p[] = $sit;
}
$alunos = todos(
    'SELECT u.id, u.nome, u.email, u.criado_em, d.telefone, d.cidade, d.fin_situacao, d.fin_valor_centavos,
            (SELECT GROUP_CONCAT(o.nome SEPARATOR ", ") FROM membros m JOIN organizacoes o ON o.id = m.org_id WHERE m.usuario_id = u.id) AS escolas,
            EXISTS(SELECT 1 FROM professores pr WHERE pr.usuario_id = u.id) AS eh_prof,
            (SELECT GROUP_CONCAT(c.titulo SEPARATOR ", ") FROM acessos ac JOIN cursos c ON c.id = ac.curso_id
               WHERE ac.usuario_id = u.id AND ac.status = \'ATIVO\' AND (ac.fim IS NULL OR ac.fim > ?)) AS cursos
     FROM usuarios u LEFT JOIN dados_aluno d ON d.usuario_id = u.id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY u.nome LIMIT 200',
    array_merge([agora()], $p)
);
cabecalho('Alunos');
?>
<p class="pequeno"><a href="<?= e(url('admin')) ?>">← Administração</a></p>
<h1>Alunos e usuários</h1>
<form method="get" action="index.php" class="cartao campos">
  <input type="hidden" name="r" value="admin_alunos">
  <label>Buscar por nome ou e-mail<input name="q" value="<?= e($busca) ?>"></label>
  <label>Situação financeira<select name="situacao"><?= opcoes(SITUACOES_FIN, $sit, 'Todas') ?></select></label>
  <div><button class="btn sec">Buscar</button></div>
</form>
<p class="suave pequeno"><?= count($alunos) ?> encontrado(s)<?= count($alunos) === 200 ? ' — mostrando os 200 primeiros; refine a busca' : '' ?>.</p>
<?php if ($alunos): ?>
<div class="cartao tabela-rolagem"><table>
  <thead><tr><th>Nome</th><th>Contato</th><th>Escola</th><th>Cursos</th><th>Mensalidade</th><th>Situação</th><th></th></tr></thead>
  <tbody><?php foreach ($alunos as $a): ?>
    <tr>
      <td><?= e($a['nome']) ?><?= $a['eh_prof'] ? ' <span class="etiqueta">Professor</span>' : '' ?><br><span class="suave pequeno"><?= e($a['email']) ?></span></td>
      <td><?= e($a['telefone'] ?? '') ?><br><span class="suave pequeno"><?= e($a['cidade'] ?? '') ?></span></td>
      <td><?= e($a['escolas'] ?? '—') ?></td>
      <td><?= $a['cursos'] ? e($a['cursos']) : '<span class="suave pequeno">sem matrícula</span>' ?></td>
      <td><?= $a['fin_valor_centavos'] !== null ? e(reais((int) $a['fin_valor_centavos'])) : '—' ?></td>
      <td><?= etiqueta_situacao($a['fin_situacao']) ?></td>
      <td><div class="acoes"><a class="btn mini sec" href="<?= e(url('editar_aluno', ['id' => $a['id']])) ?>">Editar</a><a class="btn mini sec" href="<?= e(url('aluno', ['id' => $a['id']])) ?>">Acompanhamento</a></div></td>
    </tr>
  <?php endforeach ?></tbody>
</table></div>
<?php endif ?>
<?php rodape();
