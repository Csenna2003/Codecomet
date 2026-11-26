<?php
exigir_admin();
$orgs = todos('SELECT o.*, (SELECT COUNT(*) FROM membros m WHERE m.org_id = o.id) AS n FROM organizacoes o ORDER BY o.nome');
$cursos = todos('SELECT c.*, (SELECT COUNT(*) FROM acessos a WHERE a.curso_id = c.id) AS matriculas FROM cursos c ORDER BY c.criado_em');
$usuarios = (int) valor('SELECT COUNT(*) FROM usuarios');
$pagos = um("SELECT COUNT(*) n, COALESCE(SUM(valor_centavos), 0) total FROM pedidos WHERE status = 'PAGO' AND valor_centavos > 0");
$pendentes = (int) valor("SELECT COUNT(*) FROM pedidos WHERE status = 'PENDENTE' AND valor_centavos > 0");
$trilhas = todos('SELECT * FROM trilhas ORDER BY criado_em');
cabecalho('Administração');
?>
<h1>Administração</h1>
<div class="grade">
  <div class="cartao"><h3><?= $usuarios ?></h3><span class="suave">usuários</span></div>
  <div class="cartao"><h3><?= count($orgs) ?></h3><span class="suave">escolas</span></div>
  <div class="cartao"><h3><?= e(reais((int) $pagos['total'])) ?></h3><span class="suave">em <?= (int) $pagos['n'] ?> pedidos pagos</span></div>
  <div class="cartao"><h3><?= $pendentes ?></h3><span class="suave">Pix aguardando conferência · </span><a href="<?= e(url('admin_pedidos')) ?>">Conferir pagamentos</a></div>
</div>

<div class="cartao linha">
  <form method="get" action="index.php" class="campos" style="flex:1">
    <input type="hidden" name="r" value="aluno">
    <label>Acompanhamento de um aluno (e-mail)<input name="email" type="email" required></label>
    <div><button class="btn sec">Ver acompanhamento</button></div>
  </form>
  <div class="acoes">
    <a class="btn" href="<?= e(url('admin_alunos')) ?>">Alunos</a>
    <a class="btn" href="<?= e(url('professores')) ?>">Professores</a>
    <a class="btn" href="<?= e(url('admin_particulares')) ?>">Aulas particulares</a>
  </div>
</div>

<?php
$pacotes = pacotes_de_cursos();
$cursoPorSlug = array_column(todos('SELECT id, slug FROM cursos'), 'id', 'slug');
if ($pacotes): ?>
<h2>Cursos prontos da Adoro Robótica</h2>
<p class="suave pequeno">Cursos montados a partir das suas apostilas, com aulas, projetos com correção e notas do professor. Entram como rascunho para você revisar, definir o preço e publicar.</p>
<div class="grade">
<?php foreach ($pacotes as $arq => $pk): $ja = isset($cursoPorSlug[$pk['slug']]); $corr = $ja ? situacao_correcoes($cursoPorSlug[$pk['slug']], $pk['slug']) : null; ?>
  <article class="cartao">
    <h3><?= e($pk['titulo']) ?></h3>
    <p class="suave pequeno"><?= e($pk['descricao']) ?></p>
    <p class="pequeno"><?= (int) $pk['modulos'] ?> módulos · <?= (int) $pk['aulas'] ?> aulas · <?= (int) $pk['carga'] ?>h</p>
    <?php if ($corr && $corr['pendentes']): ?>
      <p class="pequeno aviso alerta">Há <?= count($corr['pendentes']) ?> correção(ões) de texto para este curso (trechos que ficaram faltando na conversão das apostilas). Textos que você já editou não são alterados.</p>
    <?php endif ?>
    <div class="fim acoes"><?= $ja ? '<span class="etiqueta ok">Já importado</span>' : botao_acao('importar_curso', 'Importar curso', ['arquivo' => $arq], 'btn mini') ?>
      <?php if ($corr && $corr['pendentes']): ?><?= botao_acao('aplicar_correcoes', 'Atualizar textos', ['curso_id' => $cursoPorSlug[$pk['slug']]], 'btn mini', 'Corrigir ' . count($corr['pendentes']) . ' texto(s) deste curso? O progresso dos alunos, as entregas e o preço não mudam.') ?><?php endif ?></div>
  </article>
<?php endforeach ?>
</div>
<?php endif ?>

<h2>Cursos</h2>
<?php if ($cursos): ?>
<div class="cartao tabela-rolagem"><table>
  <thead><tr><th>Curso</th><th>Status</th><th>Preço</th><th>Matrículas</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($cursos as $c): ?>
    <tr><td><?= e($c['titulo']) ?></td><td><?= e(ROTULO_STATUS_CURSO[$c['status']]) ?></td><td><?= e(brl((int) $c['preco_centavos'])) ?></td><td><?= (int) $c['matriculas'] ?></td>
      <td><a href="<?= e(url('admin_curso', ['id' => $c['id']])) ?>">Editar conteúdo</a></td></tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php endif ?>
<?= abrir_form('criar_curso', 'cartao campos') ?>
  <label>Título do novo curso<input name="titulo" required></label>
  <label>Descrição curta<input name="descricao" required></label>
  <div><button class="btn sec">Criar curso</button></div>
</form>

<h2>Escolas</h2>
<?php foreach ($orgs as $o): $lics = todos('SELECT l.vagas_total, c.titulo FROM licencas l JOIN cursos c ON c.id = l.curso_id WHERE l.org_id = ?', [$o['id']]); ?>
  <div class="cartao">
    <div class="linha"><strong><?= e($o['nome']) ?><?= $o['ativa'] ? '' : ' <span class="etiqueta">Inativa</span>' ?></strong><span class="acoes"><a href="<?= e(url('editar_escola', ['id' => $o['id']])) ?>">Editar cadastro</a> · <a href="<?= e(url('escola', ['org' => $o['id']])) ?>">Abrir painel da escola</a> · <a href="<?= e(url('relatorio', ['org' => $o['id']])) ?>">Relatório</a></span></div>
    <p class="pequeno suave"><?= (int) $o['n'] ?> pessoas<?= $o['documento'] ? ' · ' . e($o['documento']) : '' ?></p>
    <?php foreach ($lics as $l): ?><p class="pequeno">Licença: <?= e($l['titulo']) ?> — <?= (int) $l['vagas_total'] ?> vagas</p><?php endforeach ?>
  </div>
<?php endforeach ?>
<?= abrir_form('criar_org', 'cartao pilha') ?>
  <div class="campos">
    <label>Nome da nova escola<input name="nome" required></label>
    <label>CNPJ (opcional)<input name="documento"></label>
  </div>
  <div class="campos">
    <label>Responsável da escola (para criar o acesso)<input name="resp_nome" placeholder="Nome da coordenação"></label>
    <label>E-mail do responsável (opcional)<input name="resp_email" type="email"></label>
  </div>
  <p class="pequeno suave">Com o nome do responsável, criamos o acesso da coordenação na hora (login e senha). Sem ele, a escola recebe um código de administrador para usar ao criar a conta.</p>
  <div><button class="btn sec">Criar escola</button></div>
</form>

<?php if ($orgs && $cursos): ?>
<h2>Liberar curso para uma escola</h2>
<?= abrir_form('criar_licenca', 'cartao campos') ?>
  <label>Escola<select name="org_id" required><?php foreach ($orgs as $o): ?><option value="<?= e($o['id']) ?>"><?= e($o['nome']) ?></option><?php endforeach ?></select></label>
  <label>Curso<select name="curso_id" required><?php foreach ($cursos as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['titulo']) ?></option><?php endforeach ?></select></label>
  <label>Vagas<input name="vagas" type="number" min="1" required></label>
  <label>Válida até (opcional)<input name="validade" type="date"></label>
  <div><button class="btn sec">Salvar licença</button></div>
</form>
<?php endif ?>

<h2>Trilhas de aprendizado</h2>
<?php foreach ($trilhas as $t): ?>
  <div class="linha" style="margin-bottom:.5rem"><span><strong><?= e($t['titulo']) ?></strong></span>
    <?= botao_acao('excluir_trilha', 'Excluir', ['trilha_id' => $t['id']], 'btn mini perigo', 'Excluir a trilha? Os cursos não são apagados.') ?></div>
<?php endforeach ?>
<?php if ($cursos): ?>
<?= abrir_form('criar_trilha') ?>
  <div class="campos"><label>Título da trilha<input name="titulo" required></label><label>Descrição<input name="descricao"></label></div>
  <fieldset><legend>Cursos da trilha (na ordem da lista)</legend>
    <?php foreach ($cursos as $c): ?><label class="marcar"><input type="checkbox" name="cursos[]" value="<?= e($c['id']) ?>"> <span><?= e($c['titulo']) ?></span></label><?php endforeach ?>
  </fieldset>
  <div><button class="btn sec">Criar trilha</button></div>
</form>
<?php endif ?>

<h2>E-mails enviados</h2>
<?php $emails = todos('SELECT * FROM emails_log ORDER BY id DESC LIMIT 15'); ?>
<p class="suave pequeno">A plataforma envia e-mails de boas-vindas, nova senha, correções, pagamentos e certificados. Remetente: <strong><?= e(remetente_email()) ?></strong>.</p>
<?= botao_acao('testar_email', 'Enviar um e-mail de teste para mim', [], 'btn sec') ?>
<?php if ($emails): ?>
<div class="cartao tabela-rolagem" style="margin-top:1rem"><table>
  <thead><tr><th>Quando</th><th>Para</th><th>Assunto</th><th>Situação</th></tr></thead>
  <tbody><?php foreach ($emails as $m): ?>
    <tr><td><?= e(date('d/m H:i', strtotime($m['criado_em']))) ?></td><td><?= e($m['para']) ?></td><td><?= e($m['assunto']) ?></td>
      <td><?= $m['enviado'] ? '<span class="etiqueta ok">Entregue ao servidor</span>' : '<span class="etiqueta erro">Recusado</span>' ?></td></tr>
  <?php endforeach ?></tbody>
</table></div>
<?php endif ?>

<h2>Nova senha para um usuário</h2>
<?= abrir_form('gerar_link_senha', 'cartao campos') ?>
  <label>E-mail da conta<input name="email" type="email" required></label>
  <div><button class="btn sec">Gerar link de nova senha</button></div>
</form>
<?php rodape();
