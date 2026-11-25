<?php
$u = usuario_atual();
$curso = carregar_curso('slug', get('slug'));
if (!$curso || ($curso['status'] !== 'PUBLICADO' && !eh_admin($u))) nao_encontrado();
$modo = $u ? modo_acesso($u, $curso['id']) : null;
$est = ($u && $modo === 'aluno') ? estado_curso($u['id'], $curso) : null;
$cert = $u ? um('SELECT codigo FROM certificados WHERE usuario_id = ? AND curso_id = ?', [$u['id'], $curso['id']]) : null;
cabecalho($curso['titulo']);
?>
<span class="etiqueta"><?= e(ROTULO_NIVEL[$curso['nivel']]) ?></span>
<h1 style="margin-top:.5rem"><?= e($curso['titulo']) ?></h1>
<p class="suave"><?= e($curso['descricao']) ?></p>

<div class="cartao linha">
<?php if ($est): ?>
  <div style="flex:1;min-width:200px"><?= barra($est['percentual']) ?><span class="pequeno"><?= $est['percentual'] ?>% concluído</span></div>
  <?php if ($cert): ?>
    <a class="btn" href="<?= e(url('certificado', ['codigo' => $cert['codigo']])) ?>">Ver certificado</a>
  <?php elseif ($est['proxima']): ?>
    <a class="btn" href="<?= e(url('aula', ['id' => $est['proxima']])) ?>"><?= $est['percentual'] === 0 ? 'Começar o curso' : 'Continuar' ?></a>
  <?php else: ?>
    <span class="etiqueta alerta">Aulas concluídas — aguardando aprovação dos exercícios</span>
  <?php endif ?>
<?php elseif ($modo === 'preview'): ?>
  <span>Você está vendo este curso como equipe (sem registrar progresso).</span>
<?php else: ?>
  <span><?= (int) $curso['carga_horaria'] ?>h de carga horária · <strong><?= e(brl((int) $curso['preco_centavos'])) ?></strong></span>
  <?php if ($u): ?>
    <?= botao_acao('matricular', $curso['preco_centavos'] ? 'Comprar curso' : 'Matricular-se grátis', ['curso_id' => $curso['id']], 'btn') ?>
  <?php else: ?>
    <a class="btn" href="<?= e(url('cadastro')) ?>">Criar conta para começar</a>
  <?php endif ?>
<?php endif ?>
</div>

<?php foreach ($curso['modulos'] as $m): ?>
  <section class="cartao">
    <h3>Módulo <?= (int) $m['ordem'] ?>: <?= e($m['titulo']) ?></h3>
    <ul class="aulas">
    <?php foreach ($m['aulas'] as $a):
        $s = $est['por_aula'][$a['id']] ?? null;
        $aberta = $modo === 'preview' || !empty($s['liberada']); ?>
      <li>
        <span class="marca-aula" aria-hidden="true"><?= !empty($s['concluida']) ? '✅' : ($aberta ? '▶️' : '🔒') ?></span>
        <span class="titulo <?= $aberta ? '' : 'bloqueada' ?>">
          <?php if ($aberta): ?><a href="<?= e(url('aula', ['id' => $a['id']])) ?>"><?= e($a['titulo']) ?></a><?php else: ?><?= e($a['titulo']) ?><?php endif ?>
          <?php if (!$a['obrigatoria']): ?><span class="suave pequeno"> (opcional)</span><?php endif ?>
        </span>
        <span class="etiqueta"><?= e(ROTULO_TIPO_AULA[$a['tipo']]) ?></span>
      </li>
    <?php endforeach ?>
    </ul>
  </section>
<?php endforeach ?>
<?php rodape();
