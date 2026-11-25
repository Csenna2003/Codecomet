<?php
$cursos = todos(
    "SELECT c.*, (SELECT COUNT(*) FROM aulas a JOIN modulos m ON m.id = a.modulo_id WHERE m.curso_id = c.id) AS n_aulas
     FROM cursos c WHERE c.status = 'PUBLICADO' ORDER BY c.criado_em"
);
$trilhas = todos('SELECT * FROM trilhas ORDER BY criado_em');
cabecalho('');
?>
<section class="heroi">
  <h1>Aprenda robótica montando, programando e entregando projetos.</h1>
  <p class="suave">Cada curso tem simulador dentro da aula (Tinkercad e Wokwi), exercícios corrigidos por professores e certificado com código de verificação.</p>
</section>

<h2>Cursos</h2>
<?php if (!$cursos): ?><p class="suave">Nenhum curso publicado ainda.</p><?php endif ?>
<div class="grade">
<?php foreach ($cursos as $c): ?>
  <article class="cartao">
    <span class="etiqueta"><?= e(ROTULO_NIVEL[$c['nivel']]) ?></span>
    <h3 style="margin-top:.5rem"><?= e($c['titulo']) ?></h3>
    <p class="suave pequeno"><?= e($c['descricao']) ?></p>
    <div class="fim linha">
      <span class="pequeno"><?= (int) $c['n_aulas'] ?> aulas · <?= (int) $c['carga_horaria'] ?>h · <strong><?= e(brl((int) $c['preco_centavos'])) ?></strong></span>
      <a class="btn mini" href="<?= e(url('curso', ['slug' => $c['slug']])) ?>">Ver curso</a>
    </div>
  </article>
<?php endforeach ?>
</div>

<?php if ($trilhas): ?><h2>Trilhas de aprendizado</h2><?php endif ?>
<?php foreach ($trilhas as $t):
    $itens = todos('SELECT c.titulo, c.slug, c.status FROM trilha_cursos tc JOIN cursos c ON c.id = tc.curso_id WHERE tc.trilha_id = ? ORDER BY tc.ordem', [$t['id']]); ?>
  <section class="cartao">
    <h3><?= e($t['titulo']) ?></h3>
    <p class="suave pequeno"><?= e($t['descricao']) ?></p>
    <ol>
    <?php foreach ($itens as $i): ?>
      <li><?php if ($i['status'] === 'PUBLICADO'): ?><a href="<?= e(url('curso', ['slug' => $i['slug']])) ?>"><?= e($i['titulo']) ?></a><?php else: ?><span class="suave"><?= e($i['titulo']) ?> (em breve)</span><?php endif ?></li>
    <?php endforeach ?>
    </ol>
  </section>
<?php endforeach ?>
<?php rodape();
