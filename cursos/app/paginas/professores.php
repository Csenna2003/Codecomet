<?php
exigir_admin();
$comp = in_array(get('competencia'), COMPETENCIAS, true) ? get('competencia') : '';
$dia = isset(DIAS[get('dia')]) ? get('dia') : '';
$per = isset(PERIODOS[get('periodo')]) ? get('periodo') : '';
$profs = todos(
    'SELECT p.*, u.nome, u.email,
            (SELECT COUNT(*) FROM particulares pa WHERE pa.professor_id = p.usuario_id AND pa.ativo = 1) AS n_alunos
     FROM professores p JOIN usuarios u ON u.id = p.usuario_id ORDER BY p.ativo DESC, u.nome'
);
// Filtros feitos aqui porque competências e horários ficam guardados como lista.
$profs = array_values(array_filter($profs, function ($p) use ($comp, $dia, $per) {
    $c = json_decode((string) $p['competencias'], true) ?: [];
    $d = json_decode((string) $p['disponibilidade'], true) ?: [];
    if ($comp !== '' && !in_array($comp, $c, true)) return false;
    if ($dia !== '' || $per !== '') {
        $ok = false;
        foreach ($d as $slot) {
            [$sd, $sp] = explode('-', $slot);
            if (($dia === '' || $sd === $dia) && ($per === '' || $sp === $per)) $ok = true;
        }
        if (!$ok) return false;
    }
    return true;
}));
cabecalho('Professores');
?>
<p class="pequeno"><a href="<?= e(url('admin')) ?>">← Administração</a></p>
<h1>Professores</h1>

<form method="get" action="index.php" class="cartao campos">
  <input type="hidden" name="r" value="professores">
  <label>Competência<select name="competencia"><option value="">Todas</option><?php foreach (COMPETENCIAS as $c): ?><option <?= $comp === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach ?></select></label>
  <label>Dia disponível<select name="dia"><?= opcoes(DIAS, $dia, 'Qualquer') ?></select></label>
  <label>Período<select name="periodo"><?= opcoes(PERIODOS, $per, 'Qualquer') ?></select></label>
  <div><button class="btn sec">Filtrar</button></div>
</form>

<?php if (!$profs): ?><p class="suave">Nenhum professor encontrado.</p><?php endif ?>
<div class="grade">
<?php foreach ($profs as $p):
    $c = json_decode((string) $p['competencias'], true) ?: [];
    $d = json_decode((string) $p['disponibilidade'], true) ?: []; ?>
  <article class="cartao">
    <div class="linha"><h3><?= e($p['nome']) ?></h3><?= $p['ativo'] ? '' : '<span class="etiqueta">Inativo</span>' ?></div>
    <p class="pequeno suave"><?= e($p['email']) ?><?= $p['telefone'] ? ' · ' . e($p['telefone']) : '' ?><br>
      <?= e(trim(($p['cidade'] ?? '') . ($p['uf'] ? '/' . $p['uf'] : ''))) ?><?= $p['modalidade'] ? ' · ' . e(MODALIDADES[$p['modalidade']] ?? '') : '' ?> · <?= (int) $p['n_alunos'] ?> aluno(s) particular(es)</p>
    <p class="pequeno"><?= $c ? e(implode(', ', $c)) : '<span class="suave">Competências não preenchidas</span>' ?></p>
    <p class="pequeno"><strong>Disponível:</strong> <?= $d ? e(implode(', ', array_map(fn($s) => DIAS[explode('-', $s)[0]] . ' ' . mb_strtolower(PERIODOS[explode('-', $s)[1]]), $d))) : '<span class="suave">não informado</span>' ?></p>
    <div class="fim"><a class="btn mini sec" href="<?= e(url('perfil_professor', ['id' => $p['usuario_id']])) ?>">Ver / editar perfil</a></div>
  </article>
<?php endforeach ?>
</div>

<h2>Cadastrar professor</h2>
<?= abrir_form('cadastrar_professor', 'cartao campos') ?>
  <label>E-mail do professor<input name="email" type="email" required></label>
  <label>Nome (se ainda não tem conta)<input name="nome"></label>
  <div><button class="btn sec">Cadastrar professor</button></div>
</form>
<p class="pequeno suave">Se a pessoa ainda não tem conta, ela é criada e recebe um e-mail para criar a senha e preencher o próprio perfil.</p>
<?php rodape();
