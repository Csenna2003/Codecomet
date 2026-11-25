<?php
// Página pública: qualquer pessoa com o código confere a autenticidade.
$c = um(
    'SELECT ce.*, u.nome, cu.titulo, o.nome AS org_nome FROM certificados ce
     JOIN usuarios u ON u.id = ce.usuario_id JOIN cursos cu ON cu.id = ce.curso_id
     LEFT JOIN organizacoes o ON o.id = ce.org_id WHERE ce.codigo = ?',
    [strtoupper(get('codigo'))]
);
if (!$c) nao_encontrado();
cabecalho('Certificado');
?>
<p class="aviso ok nao-imprimir">Certificado autêntico, emitido por <?= e(nome_site()) ?>.</p>
<div class="certificado">
  <h1>CERTIFICADO DE CONCLUSÃO</h1>
  <p>Certificamos que</p>
  <p class="nome"><?= e($c['nome']) ?></p>
  <p>concluiu o curso <strong><?= e($c['titulo']) ?></strong>, com carga horária de <strong><?= (int) $c['carga_horaria'] ?> horas</strong>, tendo todas as aulas concluídas e os projetos práticos aprovados.</p>
  <?php if ($c['org_nome']): ?><p>Realizado por meio de <?= e($c['org_nome']) ?>.</p><?php endif ?>
  <p>Emitido em <?= e(data_br($c['emitido_em'])) ?>.</p>
  <p class="suave pequeno">Código de verificação: <?= e($c['codigo']) ?></p>
</div>
<p class="nao-imprimir" style="margin-top:1rem"><button type="button" class="btn" data-imprimir>Imprimir / salvar em PDF</button></p>
<?php rodape();
