<?php
declare(strict_types=1);

/** Curso de demonstração, para ver a plataforma funcionando. Pode ser editado ou arquivado depois. */
function criar_curso_exemplo(): void
{
    if (valor("SELECT 1 FROM cursos WHERE slug = 'robotica-com-esp32-primeiros-passos'")) return;
    $wokwi = 'https://wokwi.com/projects/new/esp32';
    $cursoId = novo_id();
    q("INSERT INTO cursos (id, slug, titulo, descricao, nivel, carga_horaria, preco_centavos, status, criado_em)
       VALUES (?, 'robotica-com-esp32-primeiros-passos', 'Robótica com ESP32: primeiros passos', ?, 'INICIANTE', 12, 19700, 'PUBLICADO', ?)",
        [$cursoId, 'Do primeiro LED ao robô que desvia de obstáculos. Você monta no simulador, programa e entrega projetos corrigidos pelo professor.', agora()]);

    $modulos = [
        ['Acendendo o primeiro LED', [
            ['TEXTO', 'Como funciona um microcontrolador', 10, "O ESP32 é um pequeno computador que lê sensores e comanda luzes e motores através dos seus pinos (GPIOs).\n\nUm pino configurado como SAÍDA pode ficar em nível ALTO (3,3 V) ou BAIXO (0 V). Ligando um LED com um resistor de 220 Ω entre o pino e o GND, o LED acende quando o pino está em ALTO.\n\nNa próxima aula você vai montar esse circuito no simulador."],
            ['SIMULADOR', 'Montando o circuito no simulador', 20, "1. Adicione um LED e um resistor de 220 Ω.\n2. Ligue o GPIO16 ao resistor, o resistor ao ânodo do LED e o cátodo ao GND.\n3. No código, use pinMode(16, OUTPUT) e digitalWrite(16, HIGH).\n4. Aperte o botão de iniciar e veja o LED acender.", ['sim' => $wokwi]],
            ['QUIZ', 'Quiz: pinos e LEDs', 5, '', ['quiz' => [
                ['pergunta' => 'Para que serve o resistor em série com o LED?', 'opcoes' => ['Limitar a corrente e proteger o LED', 'Deixar o LED mais brilhante', 'Guardar energia'], 'correta' => 0],
                ['pergunta' => 'Qual comando coloca o pino 16 em nível ALTO?', 'opcoes' => ['pinMode(16, HIGH)', 'digitalWrite(16, HIGH)', 'analogRead(16)'], 'correta' => 1],
                ['pergunta' => 'Em qual terminal do circuito o cátodo do LED é ligado?', 'opcoes' => ['3,3 V', 'GND', 'Em nenhum'], 'correta' => 1],
            ]]],
            ['EXERCICIO', 'Desafio: semáforo com 3 LEDs', 40, '', ['sim' => $wokwi, 'ex' => [
                "Monte um semáforo com LEDs vermelho, amarelo e verde nos GPIOs 16, 17 e 18.\n\nSequência: verde por 3 s, amarelo por 1 s, vermelho por 3 s, repetindo para sempre.\n\nSalve o projeto na sua conta do Wokwi ou do Tinkercad e envie o link.",
                ['Os três LEDs têm resistor em série', 'A sequência verde → amarelo → vermelho está correta', 'Os tempos são 3 s, 1 s e 3 s', 'O código está organizado e comentado'], 3,
            ]]],
        ]],
        ['Sensores: medindo o mundo', [
            ['TEXTO', 'Sensor ultrassônico HC-SR04', 15, "O HC-SR04 mede distância emitindo um pulso de ultrassom e cronometrando o eco.\n\nDistância (cm) = duração do eco (µs) × 0,0343 ÷ 2.\n\nAtenção no hardware real: o pino ECHO sai em 5 V e o ESP32 só aceita 3,3 V — use um divisor de tensão (1 kΩ + 2 kΩ)."],
            ['EXERCICIO', 'Projeto final: alarme de proximidade', 60, '', ['sim' => $wokwi, 'ex' => [
                "Use o HC-SR04 (TRIG no GPIO19, ECHO no GPIO23) para acender o LED vermelho quando um objeto estiver a menos de 20 cm e o verde quando estiver mais longe.\n\nMostre a distância no Monitor Serial a cada 0,5 s. Envie o link do projeto e cole o código.",
                ['A distância aparece em cm no Monitor Serial', 'O LED vermelho acende abaixo de 20 cm', 'O LED verde acende acima de 20 cm'], null,
            ]]],
            ['TEXTO', 'Extra: levando o projeto para a placa Vespa', 10, "Quem tem o kit com a RoboCore Vespa pode gravar o mesmo programa na placa real pela Arduino IDE.\n\nO firmware \"Vespa Controle\" mostra LEDs, servos e sensores em um painel pelo Wi-Fi da própria placa.", ['opcional' => true]],
        ]],
    ];
    foreach ($modulos as $mi => [$titulo, $aulas]) {
        $modId = novo_id();
        q('INSERT INTO modulos (id, curso_id, ordem, titulo) VALUES (?,?,?,?)', [$modId, $cursoId, $mi + 1, $titulo]);
        foreach ($aulas as $ai => $a) {
            [$tipo, $tit, $dur, $conteudo] = $a;
            $extra = $a[4] ?? [];
            $aulaId = novo_id();
            q('INSERT INTO aulas (id, modulo_id, ordem, titulo, tipo, conteudo, simulador_url, quiz, obrigatoria, duracao_min) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$aulaId, $modId, $ai + 1, $tit, $tipo, $conteudo, $extra['sim'] ?? null,
                    isset($extra['quiz']) ? json_encode($extra['quiz'], JSON_UNESCAPED_UNICODE) : null, empty($extra['opcional']) ? 1 : 0, $dur]);
            if (isset($extra['ex'])) {
                q('INSERT INTO exercicios (id, aula_id, enunciado, rubrica, tipo_entrega, max_tentativas) VALUES (?,?,?,?,?,?)',
                    [novo_id(), $aulaId, $extra['ex'][0], json_encode($extra['ex'][1], JSON_UNESCAPED_UNICODE), 'LINK_SIMULADOR', $extra['ex'][2]]);
            }
        }
    }
}
