<?php
$u = exigir_usuario();
$alunoId = get('id');
if ($alunoId === '' && get('email') !== '') {
    $alunoId = (string) valor('SELECT id FROM usuarios WHERE email = ?', [strtolower(get('email'))]);
    if ($alunoId === '') voltar('erro', 'Nenhuma conta com este e-mail.', eh_admin($u) ? 'admin' : 'painel');
}
if ($alunoId === '') $alunoId = $u['id'];
$al = um('SELECT * FROM usuarios WHERE id = ?', [$alunoId]);
if (!$al || !pode_ver_aluno($u, $alunoId)) nao_encontrado();
$proprio = $alunoId === $u['id'];

$escolas = todos(
    "SELECT o.nome, m.papel, GROUP_CONCAT(t.nome SEPARATOR ', ') AS turmas FROM membros m JOIN organizacoes o ON o.id = m.org_id
     LEFT JOIN turma_alunos ta ON ta.usuario_id = m.usuario_id LEFT JOIN turmas t ON t.id = ta.turma_id AND t.org_id = o.id
     WHERE m.usuario_id = ? GROUP BY o.id, o.nome, m.papel",
    [$alunoId]
);
// Cursos com acesso ou com algum progresso (mesmo que o acesso tenha terminado).
$cursosIds = array_unique(array_merge(
    array_column(todos('SELECT curso_id FROM acessos WHERE usuario_id = ?', [$alunoId]), 'curso_id'),
    array_column(todos('SELECT DISTINCT m.curso_id FROM progresso p JOIN aulas a ON a.id = p.aula_id JOIN modulos m ON m.id = a.modulo_id WHERE p.usuario_id = ?', [$alunoId]), 'curso_id')
));
$progresso = [];
foreach (todos('SELECT aula_id, concluido_em FROM progresso WHERE usuario_id = ?', [$alunoId]) as $p) $progresso[$p['aula_id']] = $p['concluido_em'];
$entregasPorEx = [];
foreach (todos('SELECT * FROM entregas WHERE usuario_id = ? ORDER BY tentativa', [$alunoId]) as $en) $entregasPorEx[$en['exercicio_id']][] = $en;

$particulares = todos(
    'SELECT ap.*, pr.nome AS professor FROM aulas_particulares ap JOIN usuarios pr ON pr.id = ap.professor_id
     WHERE ap.aluno_id = ? ORDER BY ap.chegada DESC',
    [$alunoId]
);
$confirmadas = array_filter($particulares, fn($a) => $a['status'] === 'CONFIRMADA');
$minutos = array_sum(array_map(fn($a) => (int) round((strtotime($a['saida']) - strtotime($a['chegada'])) / 60), $confirmadas));

cabecalho('Acompanhamento — ' . $al['nome']);
?>
<div class="linha"><h1><?= $proprio ? 'Meu acompanhamento' : 'Acompanhamento: ' . e($al['nome']) ?></h1>
<?php if (eh_admin($u)): ?><a class="btn sec mini" href="<?= e(url('editar_aluno', ['id' => $alunoId])) ?>">Editar cadastro</a><?php elseif ($proprio): ?><a class="btn sec mini" href="<?= e(url('meus_dados')) ?>">Meus dados</a><?php endif ?></div>
<div class="cartao">
  <dl class="dados">
    <dt>Aluno</dt><dd><?= e($al['nome']) ?> <span class="suave pequeno">(<?= e($al['email']) ?>)</span></dd>
    <?php if ($al['responsavel_nome']): ?><dt>Responsável</dt><dd><?= e($al['responsavel_nome']) ?><?= $al['responsavel_email'] ? ' · ' . e($al['responsavel_email']) : '' ?></dd><?php endif ?>
    <?php foreach ($escolas as $esc): ?><dt>Escola</dt><dd><?= e($esc['nome']) ?><?= $esc['turmas'] ? ' · ' . e($esc['turmas']) : '' ?></dd><?php endforeach ?>
    <dt>Cadastro</dt><dd><?= e(data_br($al['criado_em'])) ?></dd>
  </dl>
</div>

<h2>Cursos online</h2>
<?php if (!$cursosIds): ?><p class="suave">Nenhum curso online.</p><?php endif ?>
<?php foreach ($cursosIds as $cid):
    $curso = carregar_curso('id', $cid);
    if (!$curso) continue;
    $est = estado_curso($alunoId, $curso);
    $acesso = um('SELECT a.*, o.nome AS org FROM acessos a LEFT JOIN organizacoes o ON o.id = a.org_id WHERE a.usuario_id = ? AND a.curso_id = ?', [$alunoId, $cid]);
    $cert = um('SELECT codigo, emitido_em FROM certificados WHERE usuario_id = ? AND curso_id = ?', [$alunoId, $cid]);
    $datas = [];
    foreach (aulas_do_curso($curso) as $a) if (isset($progresso[$a['id']])) $datas[] = $progresso[$a['id']];
    $ultima = $datas ? max($datas) : null; ?>
  <section class="cartao">
    <div class="linha"><h3><?= e($curso['titulo']) ?></h3>
      <?php if ($cert): ?><a class="etiqueta ok" href="<?= e(url('certificado', ['codigo' => $cert['codigo']])) ?>">Certificado em <?= e(data_br($cert['emitido_em'])) ?></a><?php endif ?></div>
    <p class="pequeno suave">
      <?= $acesso ? ($acesso['org'] ? 'Pela escola ' . e($acesso['org']) : 'Compra individual') . ($acesso['status'] === 'REVOGADO' ? ' · acesso encerrado' : '') . ' · início ' . e(data_br($acesso['inicio'])) : 'Sem acesso ativo' ?>
      <?= $ultima ? ' · última atividade ' . e(data_br($ultima)) : ' · ainda não começou' ?>
    </p>
    <?= barra($est['percentual']) ?><p class="pequeno"><?= $est['percentual'] ?>% concluído</p>
    <details><summary>Ver aula por aula</summary>
      <div class="tabela-rolagem"><table>
        <thead><tr><th>Aula</th><th>Tipo</th><th>Concluída em</th><th>Exercício</th></tr></thead>
        <tbody>
        <?php foreach ($curso['modulos'] as $m) foreach ($m['aulas'] as $a):
            $ens = $a['exercicio'] ? ($entregasPorEx[$a['exercicio']['id']] ?? []) : [];
            $ult = $ens ? end($ens) : null; ?>
          <tr>
            <td><?= e($a['titulo']) ?><?= $a['obrigatoria'] ? '' : ' <span class="suave pequeno">(opcional)</span>' ?></td>
            <td><?= e(ROTULO_TIPO_AULA[$a['tipo']]) ?></td>
            <td><?= isset($progresso[$a['id']]) ? '✅ ' . e(data_br($progresso[$a['id']])) : '<span class="suave">—</span>' ?></td>
            <td><?php if ($a['exercicio']): ?>
              <?= $ult ? e(ROTULO_ENTREGA[$ult['status']]) . ($ult['nota'] !== null ? ' · nota ' . (int) $ult['nota'] . '/' . (int) $a['exercicio']['nota_maxima'] : '') . ' · ' . count($ens) . ' tentativa(s)' : '<span class="suave">não enviado</span>' ?>
            <?php endif ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table></div>
    </details>
  </section>
<?php endforeach ?>

<h2>Aulas particulares</h2>
<?php if (!$particulares): ?><p class="suave">Nenhuma aula particular registrada.</p><?php else: ?>
  <p><?= count($confirmadas) ?> aula(s) confirmada(s) pelo responsável · <?= intdiv($minutos, 60) ?>h<?= str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT) ?> de aula confirmada</p>
  <div class="cartao tabela-rolagem"><table>
    <thead><tr><th>Data</th><th>Professor</th><th>Chegada</th><th>Saída</th><th>Conteúdo</th><th>Confirmação</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($particulares as $a): ?>
      <tr><td><?= e(data_br($a['chegada'])) ?></td><td><?= e($a['professor']) ?></td>
        <td class="hora"><?= e(hora($a['chegada'])) ?><?= $a['chegada_manual'] ? '*' : '' ?></td><td class="hora"><?= e(hora($a['saida'])) ?></td>
        <td><?= e(mb_strimwidth((string) $a['conteudo'], 0, 90, '…')) ?></td>
        <td><span class="etiqueta <?= CLASSE_PARTICULAR[$a['status']] ?>"><?= e(ROTULO_PARTICULAR[$a['status']]) ?></span></td>
        <td><a href="<?= e(url('particular_aula', ['id' => $a['id']])) ?>">Ver</a></td></tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
  <p class="pequeno suave">* horário de chegada informado manualmente pelo professor.</p>
<?php endif ?>
<?php rodape();
