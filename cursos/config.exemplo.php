<?php
// Normalmente o instalar.php cria o config.php sozinho.
// Use este modelo só se o instalador não conseguir gravar o arquivo:
// copie para config.php e preencha.
return [
    'nome_site' => 'Adoro Robótica',
    'db' => [
        'host' => 'localhost',
        'nome' => 'usuario_cursos',     // no cPanel o nome vem com o prefixo da conta
        'usuario' => 'usuario_cursos',
        'senha' => '',
    ],
    'pix' => [
        'chave' => '',   // exatamente como cadastrada no banco: CPF/CNPJ só números, +5511999998888, e-mail ou chave aleatória
        'nome' => '',    // titular da conta (até 25 letras)
        'cidade' => '',  // cidade do titular (até 15 letras)
    ],
];
