<?php
exigir_admin();
$al = um('SELECT * FROM usuarios WHERE id = ?', [get('id')]);
if (!$al) nao_encontrado();
$d = um('SELECT * FROM dados_aluno WHERE usuario_id = ?', [$al['id']]) ?? [];
$v = fn($k) => e($d[$k] ?? '');
$matriculas = todos(
    'SELECT a.*, c.titulo, o.nome AS org FROM acessos a JOIN cursos c ON c.id = a.curso_id LEFT JOIN organizacoes o ON o.id = a.org_id
     WHERE a.usuario_id = ? ORDER BY a.status, c.titulo',
    [$al['id']]
);
$cursos = todos("SELECT id, titulo, status FROM cursos WHERE status = 'PUBLICADO' ORDER BY titulo");
$escolasDele = todos(
    "SELECT m.org_id, o.nome, m.papel, (SELECT GROUP_CONCAT(t.nome SEPARATOR ', ') FROM turma_alunos ta JOIN turmas t ON t.id = ta.turma_id
       WHERE ta.usuario_id = m.usuario_id AND t.org_id = m.org_id) AS turmas
     FROM membros m JOIN organizacoes o ON o.id = m.org_id WHERE m.usuario_id = ? ORDER BY o.nome",
    [$al['id']]
);
$escolas = todos('SELECT id, nome FROM organizacoes WHERE ativa = 1 ORDER BY nome');
$turmas = todos('SELECT t.id, t.nome, o.nome AS escola FROM turmas t JOIN organizacoes o ON o.id = t.org_id ORDER BY o.nome, t.nome');
cabecalho('Cadastro do aluno');
?>
<p class="pequeno"><a href="<?= e(url('admin_alunos')) ?>">← Alunos</a> · <a href="<?= e(url('aluno', ['id' => $al['id']])) ?>">Ver acompanhamento</a></p>
<h1>Cadastro: <?= e($al['nome']) ?></h1>
<div class="linha cartao">
  <span>Login: <code><?= e(login_de($al['email'])) ?></code><?= valor('SELECT 1 FROM senhas_provisorias WHERE usuario_id = ?', [$al['id']]) ? ' <span class="etiqueta alerta">senha provisória</span>' : '' ?></span>
  <?= botao_acao('nova_senha_provisoria', 'Gerar nova senha provisória', ['usuario_id' => $al['id']], 'btn mini sec', 'Gerar uma nova senha? A senha atual deixa de funcionar.') ?>
</div>
<p class="suave pequeno">Cadastrado em <?= e(data_br($al['criado_em'])) ?><?= $d ? ' · atualizado em ' . e(data_br($d['atualizado_em'])) : '' ?></p>

<h2>Matrículas</h2>
<?php if (!$matriculas): ?><p class="suave">Nenhuma matrícula ainda.</p><?php else: ?>
<div class="cartao tabela-rolagem"><table>
  <thead><tr><th>Curso</th><th>Como entrou</th><th>Situação</th><th>Início</th><th>Até</th><th></th></tr></thead>
  <tbody><?php foreach ($matriculas as $m):
      $ativa = $m['status'] === 'ATIVO' && (!$m['fim'] || strtotime($m['fim']) > time()); ?>
    <tr><td><?= e($m['titulo']) ?></td>
      <td><?= e(ROTULO_ORIGEM[$m['origem']]) ?><?= $m['org'] ? ' (' . e($m['org']) . ')' : '' ?></td>
      <td><?= $ativa ? '<span class="etiqueta ok">Ativa</span>' : '<span class="etiqueta">Encerrada</span>' ?></td>
      <td><?= e(data_br($m['inicio'])) ?></td><td><?= $m['fim'] ? e(data_br($m['fim'])) : 'Sem prazo' ?></td>
      <td><?= $ativa ? botao_acao('cancelar_matricula', 'Cancelar', ['acesso_id' => $m['id']], 'btn mini perigo', 'Cancelar a matrícula em ' . $m['titulo'] . '? O histórico é mantido.') : '' ?></td></tr>
  <?php endforeach ?></tbody>
</table></div>
<?php endif ?>
<?php if ($cursos): ?>
<?= abrir_form('matricular_manual', 'cartao campos', ['usuario_id' => $al['id']]) ?>
  <label>Matricular no curso<select name="curso_id" required><option value="">Escolha…</option>
    <?php foreach ($cursos as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['titulo']) ?></option><?php endforeach ?></select></label>
  <label>Tipo<select name="origem"><option value="MANUAL">Matrícula (pagamento por fora)</option><option value="BOLSA">Bolsa / cortesia</option></select></label>
  <label>Acesso até (opcional)<input type="date" name="fim"></label>
  <div><button class="btn">Matricular</button></div>
</form>
<?php endif ?>

<h2>Escola e turma</h2>
<?php foreach ($escolasDele as $es): ?>
  <div class="linha cartao"><span><strong><?= e($es['nome']) ?></strong> — <?= e(ROTULO_PAPEL[$es['papel']]) ?><?= $es['turmas'] ? ' · ' . e($es['turmas']) : '' ?></span>
    <?= botao_acao('remover_escola_aluno', 'Tirar da escola', ['usuario_id' => $al['id'], 'org_id' => $es['org_id']], 'btn mini perigo', 'Tirar da escola? Os cursos que vinham dela serão encerrados.') ?></div>
<?php endforeach ?>
<?php if ($escolas): ?>
<?= abrir_form('adicionar_escola', 'cartao campos', ['usuario_id' => $al['id']]) ?>
  <label>Colocar na escola<select name="org_id" required><option value="">Escolha…</option><?php foreach ($escolas as $es): ?><option value="<?= e($es['id']) ?>"><?= e($es['nome']) ?></option><?php endforeach ?></select></label>
  <label>Turma (opcional)<select name="turma_id"><option value="">Sem turma</option><?php foreach ($turmas as $t): ?><option value="<?= e($t['id']) ?>"><?= e($t['escola'] . ' — ' . $t['nome']) ?></option><?php endforeach ?></select></label>
  <div><button class="btn sec">Adicionar</button></div>
</form>
<p class="pequeno suave">Ao entrar na escola, o aluno recebe os cursos da licença dela, se houver vaga.</p>
<?php endif ?>

<h2>Dados cadastrais</h2>
<?= abrir_form('salvar_aluno', 'pilha cartao', ['usuario_id' => $al['id']]) ?>
  <h3>Dados do aluno</h3>
  <div class="campos">
    <label>Nome completo<input name="nome" value="<?= e($al['nome']) ?>" required></label>
    <label>E-mail<?= tem_email_real($al['email']) ? '' : ' (opcional: hoje entra com o usuário)' ?><input name="email" type="email" value="<?= e(tem_email_real($al['email']) ? $al['email'] : '') ?>" <?= tem_email_real($al['email']) ? 'required' : '' ?>></label>
    <label>Telefone / WhatsApp<input name="telefone" type="tel" value="<?= $v('telefone') ?>"></label>
    <label>Data de nascimento<input name="nascimento" type="date" value="<?= e($al['nascimento'] ?? '') ?>"></label>
    <label>CPF do aluno<input name="cpf" inputmode="numeric" value="<?= e(formatar_doc($d['cpf'] ?? '')) ?>"></label>
  </div>
  <?= campos_endereco($d) ?>

  <fieldset class="pilha"><legend>Responsável</legend>
    <div class="campos">
      <label>Nome<input name="responsavel_nome" value="<?= e($al['responsavel_nome'] ?? '') ?>"></label>
      <label>E-mail<input name="responsavel_email" type="email" value="<?= e($al['responsavel_email'] ?? '') ?>"></label>
      <label>Telefone / WhatsApp<input name="responsavel_telefone" type="tel" value="<?= $v('responsavel_telefone') ?>"></label>
      <label>CPF<input name="responsavel_cpf" inputmode="numeric" value="<?= e(formatar_doc($d['responsavel_cpf'] ?? '')) ?>"></label>
    </div>
  </fieldset>

  <fieldset class="pilha"><legend>Financeiro</legend>
    <div class="campos">
      <label>Responsável financeiro<input name="fin_nome" value="<?= $v('fin_nome') ?>" placeholder="Quem paga"></label>
      <label>CPF do responsável financeiro<input name="fin_cpf" inputmode="numeric" value="<?= e(formatar_doc($d['fin_cpf'] ?? '')) ?>"></label>
      <label>E-mail para cobrança<input name="fin_email" type="email" value="<?= $v('fin_email') ?>"></label>
      <label>Telefone para cobrança<input name="fin_telefone" type="tel" value="<?= $v('fin_telefone') ?>"></label>
    </div>
    <label>Endereço de cobrança (se for diferente do endereço acima)<input name="fin_endereco" value="<?= $v('fin_endereco') ?>"></label>
    <?= campos_financeiro($d, 'Mensalidade') ?>
  </fieldset>
  <div><button class="btn">Salvar cadastro</button></div>
</form>
<p class="pequeno suave">Os dados financeiros e os CPFs só aparecem para a administração da plataforma.</p>
<?php rodape();
