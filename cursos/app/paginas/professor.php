<?php
$u = exigir_usuario();
$f = filtro_entregas($u);
if (!$f) ir('painel');
$base = "SELECT e.*, u.nome AS aluno, o.nome AS org_nome, a.titulo AS aula, c.titulo AS curso FROM entregas e
         JOIN usuarios u ON u.id = e.usuario_id LEFT JOIN organizacoes o ON o.id = e.org_id
         JOIN exercicios x ON x.id = e.exercicio_id JOIN aulas a ON a.id = x.aula_id
         JOIN modulos m ON m.id = a.modulo_id JOIN cursos c ON c.id = m.curso_id WHERE {$f[0]}";
$fila = todos("$base AND e.status = 'ENVIADA' ORDER BY e.enviado_em", $f[1]);
$recentes = todos("$base AND e.status <> 'ENVIADA' ORDER BY e.revisado_em DESC LIMIT 20", $f[1]);

$tabela = function (array $itens, string $acao) { ?>
  <div class="cartao tabela-rolagem"><table>
    <thead><tr><th>Aluno</th><th>Escola</th><th>Exercício</th><th>Enviada em</th><th>Situação</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($itens as $e): ?>
      <tr>
        <td><?= e($e['aluno']) ?></td>
        <td><?= e($e['org_nome'] ?? 'Venda direta') ?></td>
        <td><?= e($e['aula']) ?><br><span class="suave pequeno"><?= e($e['curso']) ?> · tentativa <?= (int) $e['tentativa'] ?></span></td>
        <td><?= e(data_br($e['enviado_em'])) ?></td>
        <td><?= e(ROTULO_ENTREGA[$e['status']]) ?><?= $e['nota'] !== null ? ' · nota ' . (int) $e['nota'] : '' ?></td>
        <td><a href="<?= e(url('entrega', ['id' => $e['id']])) ?>"><?= e($acao) ?></a></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
<?php };
cabecalho('Correções');
?>
<h1>Correções</h1>
<h2>Aguardando correção (<?= count($fila) ?>)</h2>
<?php if ($fila) $tabela($fila, 'Corrigir'); else echo '<p class="suave">Nenhuma entrega na fila. 🎉</p>'; ?>
<h2>Corrigidas recentemente</h2>
<?php if ($recentes) $tabela($recentes, 'Ver'); else echo '<p class="suave">Nada por aqui ainda.</p>'; ?>
<?php rodape();
