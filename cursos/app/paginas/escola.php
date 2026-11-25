<?php
$u = exigir_usuario();
$orgs = eh_admin($u)
    ? array_column(todos('SELECT id, nome FROM organizacoes ORDER BY nome'), 'nome', 'id')
    : orgs_geridas($u);
if (!$orgs) ir(eh_admin($u) ? 'admin' : 'painel');
// A escola pedida na URL só vale se estiver entre as que o usuário administra.
$orgId = isset($orgs[get('org')]) ? get('org') : array_key_first($orgs);
$orgNome = $orgs[$orgId];

$membros = todos('SELECT m.*, u.nome, u.email FROM membros m JOIN usuarios u ON u.id = m.usuario_id WHERE m.org_id = ? ORDER BY u.nome', [$orgId]);
$alunos = array_values(array_filter($membros, fn($m) => $m['papel'] === 'ALUNO'));
$equipe = array_values(array_filter($membros, fn($m) => $m['papel'] !== 'ALUNO'));
$turmas = todos(
    'SELECT t.*, u.nome AS professor, (SELECT COUNT(*) FROM turma_alunos ta WHERE ta.turma_id = t.id) AS n_alunos
     FROM turmas t LEFT JOIN usuarios u ON u.id = t.professor_id WHERE t.org_id = ? ORDER BY t.nome',
    [$orgId]
);
$convites = todos('SELECT c.*, t.nome AS turma FROM convites c LEFT JOIN turmas t ON t.id = c.turma_id WHERE c.org_id = ? AND c.ativo = 1 ORDER BY c.criado_em DESC', [$orgId]);
$licencas = todos(
    "SELECT l.*, c.titulo, (SELECT COUNT(*) FROM acessos a WHERE a.licenca_id = l.id AND a.status = 'ATIVO') AS usadas
     FROM licencas l JOIN cursos c ON c.id = l.curso_id WHERE l.org_id = ?",
    [$orgId]
);

// Progresso por aluno em cada curso licenciado.
$turmaDe = [];
foreach (todos('SELECT ta.usuario_id, t.nome FROM turma_alunos ta JOIN turmas t ON t.id = ta.turma_id WHERE t.org_id = ?', [$orgId]) as $r) {
    $turmaDe[$r['usuario_id']][] = $r['nome'];
}
$info = [];
foreach ($licencas as $l) {
    $obrig = array_column(todos('SELECT a.id FROM aulas a JOIN modulos m ON m.id = a.modulo_id WHERE m.curso_id = ? AND a.obrigatoria = 1', [$l['curso_id']]), 'id');
    $comVaga = array_flip(array_column(todos("SELECT usuario_id FROM acessos WHERE licenca_id = ? AND status = 'ATIVO'", [$l['id']]), 'usuario_id'));
    $feitas = [];
    if ($obrig) {
        foreach (todos('SELECT usuario_id, COUNT(*) n FROM progresso WHERE aula_id IN (' . marcas($obrig) . ') GROUP BY usuario_id', $obrig) as $r) $feitas[$r['usuario_id']] = (int) $r['n'];
    }
    $certs = array_flip(array_column(todos('SELECT usuario_id FROM certificados WHERE curso_id = ?', [$l['curso_id']]), 'usuario_id'));
    $info[$l['id']] = ['total' => count($obrig), 'vaga' => $comVaga, 'feitas' => $feitas, 'certs' => $certs];
}
$campoOrg = ['org_id' => $orgId];
cabecalho('Escola');
?>
<div class="linha"><h1><?= e($orgNome) ?></h1><a class="btn sec mini" href="<?= e(url('editar_escola', ['id' => $orgId])) ?>">Dados da escola</a></div>
<?php if (count($orgs) > 1): ?>
  <p class="pequeno">Trocar de escola:
  <?php foreach ($orgs as $id => $nome) if ($id !== $orgId): ?><a href="<?= e(url('escola', ['org' => $id])) ?>" style="margin-right:.75rem"><?= e($nome) ?></a><?php endif ?>
  </p>
<?php endif ?>

<h2>Licenças</h2>
<?php if (!$licencas): ?><p class="suave">Esta escola ainda não tem cursos contratados.</p><?php endif ?>
<div class="grade">
<?php foreach ($licencas as $l): ?>
  <div class="cartao">
    <h3><?= e($l['titulo']) ?></h3>
    <p><?= (int) $l['usadas'] ?> de <?= (int) $l['vagas_total'] ?> vagas em uso</p>
    <?= barra((int) min(100, round($l['usadas'] / max(1, $l['vagas_total']) * 100))) ?>
    <p class="pequeno suave"><?= $l['validade'] ? 'Válida até ' . e(data_br($l['validade'])) : 'Sem data de validade' ?></p>
  </div>
<?php endforeach ?>
</div>

<h2>Alunos e progresso (<?= count($alunos) ?>)</h2>
<?php if (!$alunos): ?>
  <p class="suave">Nenhum aluno ainda. Crie um código de convite abaixo e distribua para a turma.</p>
<?php else: ?>
  <div class="cartao tabela-rolagem"><table>
    <thead><tr><th>Aluno</th><th>Turma</th><?php foreach ($licencas as $l): ?><th><?= e($l['titulo']) ?></th><?php endforeach ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($alunos as $m): ?>
      <tr>
        <td><a href="<?= e(url('aluno', ['id' => $m['usuario_id']])) ?>"><?= e($m['nome']) ?></a><br><span class="suave pequeno"><?= e($m['email']) ?></span></td>
        <td><?= e(implode(', ', $turmaDe[$m['usuario_id']] ?? []) ?: '—') ?></td>
        <?php foreach ($licencas as $l): $x = $info[$l['id']]; $uid = $m['usuario_id']; ?>
          <td><?php if (isset($x['certs'][$uid])): ?><span class="etiqueta ok">Certificado</span>
              <?php elseif (isset($x['vaga'][$uid])): ?><?= $x['total'] ? (int) round(($x['feitas'][$uid] ?? 0) / $x['total'] * 100) : 0 ?>%
              <?php else: ?><span class="etiqueta alerta">Sem vaga</span><?php endif ?></td>
        <?php endforeach ?>
        <td><div class="acoes">
          <?= botao_acao('gerar_link_senha', 'Nova senha', $campoOrg + ['usuario_id' => $m['usuario_id']], 'btn mini sec') ?>
          <?= botao_acao('remover_membro', 'Remover', $campoOrg + ['usuario_id' => $m['usuario_id']], 'btn mini perigo', 'Remover ' . $m['nome'] . ' da escola? O histórico e os certificados serão mantidos.') ?>
        </div></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
<?php endif ?>

<h2>Turmas</h2>
<?php foreach ($turmas as $t): ?>
  <p><strong><?= e($t['nome']) ?></strong><?= $t['periodo'] ? ' (' . e($t['periodo']) . ')' : '' ?> · <?= (int) $t['n_alunos'] ?> alunos · professor: <?= e($t['professor'] ?? 'não definido') ?></p>
<?php endforeach ?>
<?= abrir_form('criar_turma', 'cartao campos', $campoOrg) ?>
  <label>Nome da turma<input name="nome" required placeholder="7º ano B"></label>
  <label>Período<input name="periodo" placeholder="2027"></label>
  <label>Professor<select name="professor_id"><option value="">Definir depois</option>
    <?php foreach ($equipe as $m): ?><option value="<?= e($m['usuario_id']) ?>"><?= e($m['nome']) ?></option><?php endforeach ?></select></label>
  <div><button class="btn sec">Criar turma</button></div>
</form>

<h2>Códigos de convite</h2>
<p class="suave pequeno">O aluno digita o código ao criar a conta (ou em “Meu painel”) e entra direto na turma, já com acesso aos cursos da escola.</p>
<?php if ($convites): ?>
  <div class="cartao tabela-rolagem"><table>
    <thead><tr><th>Código</th><th>Para</th><th>Turma</th><th>Usos</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($convites as $c): ?>
      <tr><td><strong><?= e($c['codigo']) ?></strong></td><td><?= e(ROTULO_PAPEL[$c['papel']]) ?></td><td><?= e($c['turma'] ?? '—') ?></td>
        <td><?= (int) $c['usos'] ?><?= $c['max_usos'] ? ' de ' . (int) $c['max_usos'] : '' ?></td>
        <td><?= botao_acao('desativar_convite', 'Desativar', $campoOrg + ['convite_id' => $c['id']], 'btn mini perigo') ?></td></tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
<?php endif ?>
<?= abrir_form('criar_convite', 'cartao campos', $campoOrg) ?>
  <label>Convite para<select name="papel"><option value="ALUNO">Alunos</option><option value="PROFESSOR">Professor</option></select></label>
  <label>Turma (para alunos)<select name="turma_id"><option value="">Sem turma</option>
    <?php foreach ($turmas as $t): ?><option value="<?= e($t['id']) ?>"><?= e($t['nome']) ?></option><?php endforeach ?></select></label>
  <label>Limite de usos (opcional)<input name="max_usos" type="number" min="1"></label>
  <div><button class="btn sec">Gerar código</button></div>
</form>

<h2>Equipe</h2>
<?php foreach ($equipe as $m): ?>
  <div class="linha" style="margin-bottom:.5rem">
    <span><?= e($m['nome']) ?> — <?= e(ROTULO_PAPEL[$m['papel']]) ?> <span class="suave pequeno">(<?= e($m['email']) ?>)</span></span>
    <?php if ($m['usuario_id'] !== $u['id']): ?>
      <span class="acoes">
        <?php if ($m['papel'] === 'PROFESSOR'): ?><?= botao_acao('gerar_link_senha', 'Nova senha', $campoOrg + ['usuario_id' => $m['usuario_id']], 'btn mini sec') ?><?php endif ?>
        <?= botao_acao('remover_membro', 'Remover', $campoOrg + ['usuario_id' => $m['usuario_id']], 'btn mini perigo', 'Remover ' . $m['nome'] . ' da escola?') ?>
      </span>
    <?php endif ?>
  </div>
<?php endforeach ?>
<?php rodape();
