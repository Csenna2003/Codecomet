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
$escolasLista = todos('SELECT id, nome FROM organizacoes WHERE ativa = 1 ORDER BY nome');
$turmasLista = todos('SELECT t.id, t.nome, o.nome AS escola FROM turmas t JOIN organizacoes o ON o.id = t.org_id ORDER BY o.nome, t.nome');
$cursosLista = todos("SELECT id, titulo FROM cursos WHERE status = 'PUBLICADO' ORDER BY titulo");
cabecalho('Alunos');
?>
<p class="pequeno"><a href="<?= e(url('admin')) ?>">← Administração</a></p>
<h1>Alunos e usuários</h1>
<details class="cartao" open>
  <summary>Criar acessos (login e senha) — um ou vários de uma vez</summary>
  <?= abrir_form('criar_acessos', 'pilha') ?>
    <label>Nomes, um por linha. Para quem tem e-mail, use “Nome; e-mail”.
      <textarea name="pessoas" rows="6" required placeholder="Ana Beatriz Souza&#10;Pedro Henrique Lima; pedro@email.com"></textarea></label>
    <p class="pequeno suave">Quem não tiver e-mail recebe um usuário (ex.: ana.souza). Todos recebem uma senha provisória, que aparece na tela seguinte para você enviar ou imprimir.</p>
    <div class="campos">
      <label>Tipo de acesso<select name="papel"><option value="ALUNO">Aluno</option><option value="PROFESSOR">Professor</option><option value="COORDENADOR">Coordenação da escola</option></select></label>
      <label>Escola (opcional)<select name="org_id"><option value="">Nenhuma (aluno particular ou individual)</option><?php foreach ($escolasLista as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['nome']) ?></option><?php endforeach ?></select></label>
      <label>Turma (opcional)<select name="turma_id"><option value="">Sem turma</option><?php foreach ($turmasLista as $t): ?><option value="<?= e($t['id']) ?>"><?= e($t['escola'] . ' — ' . $t['nome']) ?></option><?php endforeach ?></select></label>
      <label>Matricular no curso (opcional)<select name="curso_id"><option value="">Nenhum</option><?php foreach ($cursosLista as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['titulo']) ?></option><?php endforeach ?></select></label>
      <label>Tipo de matrícula<select name="origem"><option value="MANUAL">Pagamento por fora</option><option value="BOLSA">Bolsa / cortesia</option></select></label>
    </div>
    <label>WhatsApp para enviar o acesso (opcional, quando for uma pessoa só)<input name="telefone" type="tel" placeholder="(12) 99999-8888"></label>
    <div><button class="btn">Criar acessos</button></div>
  </form>
</details>
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
      <td><?= e($a['nome']) ?><?= $a['eh_prof'] ? ' <span class="etiqueta">Professor</span>' : '' ?><br><span class="suave pequeno"><?= e(login_de($a['email'])) ?></span></td>
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
