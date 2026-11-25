<?php
$u = exigir_usuario();
if (!da_aulas_particulares($u)) ir('painel');
$filtroProf = eh_admin($u) ? '1=1' : 'p.professor_id = ?';
$par = eh_admin($u) ? [] : [$u['id']];
$vinculos = todos(
    "SELECT p.*, al.nome AS aluno, pr.nome AS professor FROM particulares p
     JOIN usuarios al ON al.id = p.aluno_id JOIN usuarios pr ON pr.id = p.professor_id
     WHERE p.ativo = 1 AND $filtroProf ORDER BY al.nome",
    $par
);
$sel = "SELECT ap.*, al.nome AS aluno, pr.nome AS professor FROM aulas_particulares ap
        JOIN usuarios al ON al.id = ap.aluno_id JOIN usuarios pr ON pr.id = ap.professor_id
        JOIN particulares p ON p.id = ap.particular_id WHERE $filtroProf";
$andamento = todos("$sel AND ap.status = 'EM_ANDAMENTO' ORDER BY ap.chegada", $par);
$recentes = todos("$sel AND ap.status <> 'EM_ANDAMENTO' ORDER BY ap.chegada DESC LIMIT 30", $par);
$emAndamento = array_column($andamento, 'particular_id');
cabecalho('Aulas particulares');
?>
<h1>Aulas particulares</h1>

<?php foreach ($andamento as $a): ?>
  <section class="cartao">
    <div class="linha"><h3>Aula em andamento: <?= e($a['aluno']) ?></h3><span class="etiqueta">Chegada <?= e(data_br($a['chegada'])) ?> às <span class="hora"><?= e(hora($a['chegada'])) ?></span><?= $a['chegada_manual'] ? ' (informada)' : '' ?></span></div>
    <?php if (eh_admin($u)): ?><p class="pequeno suave">Professor: <?= e($a['professor']) ?></p><?php endif ?>
    <?= abrir_form('finalizar_aula_particular', 'pilha', ['aula_id' => $a['id']]) ?>
      <label>Conteúdo apresentado na aula<textarea name="conteudo" rows="5" required placeholder="Ex.: Montagem do semáforo com 3 LEDs no Arduino; uso de delay(); o aluno programou a sequência sozinho."></textarea></label>
      <label>Observações (opcional: comportamento, dificuldades, tarefa para casa)<textarea name="observacoes" rows="2"></textarea></label>
      <div class="linha">
        <button class="btn grande">Finalizar aula</button>
      </div>
    </form>
    <div style="margin-top:.5rem"><?= botao_acao('cancelar_aula_particular', 'Cancelar este registro', ['aula_id' => $a['id']], 'btn mini perigo', 'Cancelar o registro de chegada? Use só se foi registrado por engano.') ?></div>
  </section>
<?php endforeach ?>

<h2>Meus alunos</h2>
<?php if (!$vinculos): ?><p class="suave">Nenhum aluno particular cadastrado. Peça à administração para cadastrar.</p><?php endif ?>
<div class="grade">
<?php foreach ($vinculos as $v): ?>
  <div class="cartao">
    <h3><a href="<?= e(url('aluno', ['id' => $v['aluno_id']])) ?>"><?= e($v['aluno']) ?></a></h3>
    <p class="pequeno suave">Responsável: <?= e($v['responsavel_nome']) ?><?= eh_admin($u) ? ' · professor: ' . e($v['professor']) : '' ?></p>
    <div class="fim">
    <?php if (in_array($v['id'], $emAndamento, true)): ?>
      <span class="etiqueta alerta">Aula em andamento</span>
    <?php else: ?>
      <?= botao_acao('registrar_chegada', 'Cheguei agora', ['vinculo_id' => $v['id']], 'btn grande') ?>
      <details style="margin-top:.5rem"><summary class="pequeno">Esqueceu de registrar? Informar o horário</summary>
        <?= abrir_form('registrar_chegada', 'campos', ['vinculo_id' => $v['id']]) ?>
          <label>Data<input type="date" name="data" value="<?= e(date('Y-m-d')) ?>" required></label>
          <label>Horário de chegada<input type="time" name="horario" required></label>
          <div><button class="btn mini sec">Registrar</button></div>
        </form>
        <p class="pequeno suave">O horário digitado aparece como “informado” para o responsável e para a escola.</p>
      </details>
    <?php endif ?>
    </div>
  </div>
<?php endforeach ?>
</div>

<h2>Últimas aulas</h2>
<?php if (!$recentes): ?><p class="suave">Nenhuma aula registrada ainda.</p><?php else: ?>
<div class="cartao tabela-rolagem"><table>
  <thead><tr><th>Data</th><th>Aluno</th><th>Chegada</th><th>Saída</th><th>Duração</th><th>Confirmação</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($recentes as $a): ?>
    <tr><td><?= e(data_br($a['chegada'])) ?></td><td><?= e($a['aluno']) ?></td>
      <td class="hora"><?= e(hora($a['chegada'])) ?><?= $a['chegada_manual'] ? '*' : '' ?></td><td class="hora"><?= e(hora($a['saida'])) ?></td><td><?= e(duracao($a['chegada'], $a['saida'])) ?></td>
      <td><span class="etiqueta <?= CLASSE_PARTICULAR[$a['status']] ?>"><?= e(ROTULO_PARTICULAR[$a['status']]) ?></span></td>
      <td><a href="<?= e(url('particular_aula', ['id' => $a['id']])) ?>">Ver</a></td></tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<p class="pequeno suave">* horário de chegada informado manualmente.</p>
<?php endif ?>
<?php rodape();
