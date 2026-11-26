<?php
$u = exigir_usuario();
$acessos = todos(
    "SELECT a.*, o.nome AS org_nome FROM acessos a LEFT JOIN organizacoes o ON o.id = a.org_id
     WHERE a.usuario_id = ? AND a.status = 'ATIVO' AND (a.fim IS NULL OR a.fim > ?)
       AND NOT (a.origem = 'LICENCA_ESCOLA' AND o.ativa = 0) ORDER BY a.inicio",
    [$u['id'], agora()]
);
$certificados = todos('SELECT ce.*, c.titulo FROM certificados ce JOIN cursos c ON c.id = ce.curso_id WHERE ce.usuario_id = ? ORDER BY ce.emitido_em', [$u['id']]);
// Pedidos de ajuste que o aluno ainda não reenviou.
$ajustes = todos(
    "SELECT e.*, a.titulo, a.id AS aula_id FROM entregas e JOIN exercicios x ON x.id = e.exercicio_id JOIN aulas a ON a.id = x.aula_id
     WHERE e.usuario_id = ? AND e.status = 'PRECISA_AJUSTE'
       AND NOT EXISTS (SELECT 1 FROM entregas n WHERE n.exercicio_id = e.exercicio_id AND n.usuario_id = e.usuario_id AND n.tentativa > e.tentativa)",
    [$u['id']]
);
$pedidos = todos("SELECT p.*, c.titulo FROM pedidos p JOIN cursos c ON c.id = p.curso_id WHERE p.usuario_id = ? AND p.status = 'PENDENTE' AND p.valor_centavos > 0", [$u['id']]);
cabecalho('Meu painel');
?>
<div class="linha"><h1>Olá, <?= e(explode(' ', $u['nome'])[0]) ?>!</h1><span class="acoes"><a class="btn sec mini" href="<?= e(url('aluno')) ?>">Meu acompanhamento</a><a class="btn sec mini" href="<?= e(url('meus_dados')) ?>">Meus dados</a><?php if (eh_professor_cadastrado($u)): ?><a class="btn sec mini" href="<?= e(url('perfil_professor')) ?>">Meu perfil de professor</a><?php endif ?></span></div>

<?php if (valor('SELECT 1 FROM senhas_provisorias WHERE usuario_id = ?', [$u['id']])): ?>
  <p class="aviso alerta">Você está usando uma senha provisória. <a href="<?= e(url('meus_dados')) ?>">Troque agora por uma senha sua</a>.</p>
<?php endif ?>
<?php foreach ($ajustes as $e): ?>
  <p class="aviso alerta">Seu professor pediu um ajuste em <strong><?= e($e['titulo']) ?></strong>. <a href="<?= e(url('aula', ['id' => $e['aula_id']])) ?>">Ver o comentário e reenviar</a></p>
<?php endforeach ?>
<?php foreach ($pedidos as $p): ?>
  <p class="aviso alerta"><?= $p['avisou_pago_em'] ? 'Estamos conferindo o seu Pix de ' : 'Falta pagar o Pix de ' ?><strong><?= e($p['titulo']) ?></strong>. <a href="<?= e(url('pedido', ['id' => $p['id']])) ?>">Ver pagamento</a></p>
<?php endforeach ?>

<h2>Meus cursos</h2>
<?php if (!$acessos): ?>
  <p class="suave">Você ainda não está em nenhum curso. <a href="<?= e(url()) ?>">Veja os cursos</a> ou use o código da sua escola abaixo.</p>
<?php endif ?>
<div class="grade">
<?php foreach ($acessos as $a):
    $curso = carregar_curso('id', $a['curso_id']);
    $est = estado_curso($u['id'], $curso);
    $destino = $est['proxima'] ? url('aula', ['id' => $est['proxima']]) : url('curso', ['slug' => $curso['slug']]); ?>
  <article class="cartao">
    <h3><?= e($curso['titulo']) ?></h3>
    <p class="pequeno suave"><?= $a['org_nome'] ? 'Pela escola ' . e($a['org_nome']) : 'Compra individual' ?><?= $a['fim'] ? ' · acesso até ' . e(data_br($a['fim'])) : '' ?></p>
    <?= barra($est['percentual']) ?>
    <p class="pequeno"><?= $est['percentual'] ?>% concluído</p>
    <div class="fim"><a class="btn mini" href="<?= e($destino) ?>"><?= $est['percentual'] === 0 ? 'Começar' : ($est['proxima'] ? 'Continuar' : 'Rever curso') ?></a></div>
  </article>
<?php endforeach ?>
</div>

<h2>Certificados</h2>
<?php if (!$certificados): ?><p class="suave">Conclua todas as aulas e tenha os exercícios aprovados para receber o certificado.</p><?php endif ?>
<?php foreach ($certificados as $c): ?>
  <div class="cartao linha"><span><strong><?= e($c['titulo']) ?></strong> · emitido em <?= e(data_br($c['emitido_em'])) ?></span><a class="btn mini" href="<?= e(url('certificado', ['codigo' => $c['codigo']])) ?>">Abrir certificado</a></div>
<?php endforeach ?>

<h2>Minha escola</h2>
<?php foreach ($u['membros'] as $m): ?><p><?= e($m['org_nome']) ?> — <?= e(ROTULO_PAPEL[$m['papel']]) ?></p><?php endforeach ?>
<?= abrir_form('usar_codigo', 'cartao campos') ?>
  <label>Tenho um código da escola<input name="codigo" required autocapitalize="characters"></label>
  <div><button class="btn sec">Usar código</button></div>
</form>
<?php rodape();
