# Vespa Controle

Painel web para controlar dispositivos ligados à placa **RoboCore Vespa** (ESP32).
A própria Vespa cria uma rede Wi‑Fi (modo Access Point): basta conectar o celular
ou computador nessa rede e abrir o endereço da placa no navegador. Não é preciso
roteador nem internet.

Dispositivos controlados:

| Dispositivo | Quantidade | O que o painel faz |
|---|---|---|
| Servomotores | 4 | Controle de ângulo (0–180°) com sliders e botão "centralizar" |
| LEDs | 3 | Liga/desliga individual e "todos" |
| Sensor de temperatura DS18B20 | 1 | Mostra a temperatura em °C (atualiza a cada 0,5 s) |
| Sensor ultrassônico HC‑SR04 | 1 | Mostra a distância em cm, com barra visual |

O rodapé também mostra a tensão da bateria da Vespa e quantos dispositivos estão
conectados na rede.

## Como acessar

1. Ligue a Vespa.
2. No celular/PC, conecte na rede Wi‑Fi **`Vespa-Controle`**, senha **`vespa1234`**.
3. Abra no navegador: **http://192.168.4.1** (ou **http://vespa.local**).
   Em muitos celulares a página abre sozinha (captive portal).

Nome da rede, senha e IP podem ser alterados em `VespaControle/config.h`.

## Ligações

Pinos reservados pela Vespa (não use): 13, 14, 27, 4 (motores DC), 15 (LED da placa),
26, 25, 33, 32 (servos), 34 (bateria), 35 (botão).

| Dispositivo | Pino da Vespa | Observação |
|---|---|---|
| Servo 1 | Conector **S1** (GPIO26) | |
| Servo 2 | Conector **S2** (GPIO25) | |
| Servo 3 | Conector **S3** (GPIO33) | |
| Servo 4 | Conector **S4** (GPIO32) | Use bateria adequada: 4 servos puxam bastante corrente |
| LED 1 | GPIO16 | Resistor de 220–330 Ω em série; catodo no GND |
| LED 2 | GPIO17 | idem |
| LED 3 | GPIO18 | idem |
| HC‑SR04 TRIG | GPIO19 | VCC em 5 V |
| HC‑SR04 ECHO | GPIO23 | **Use divisor de tensão** (ex.: 1 kΩ + 2 kΩ): o ECHO sai em 5 V e o ESP32 aceita só 3,3 V |
| DS18B20 DATA | GPIO21 | Resistor de pull‑up de 4,7 kΩ entre DATA e 3,3 V; VCC em 3,3 V |

> Confira no pinout da sua Vespa quais GPIOs estão disponíveis nos conectores de
> expansão. Se precisar trocar algum pino, altere apenas `VespaControle/config.h`.

## Como gravar (Arduino IDE)

1. Instale o pacote de placas **esp32** (Espressif) versão **3.x** no Gerenciador de Placas
   (a biblioteca da Vespa exige a versão 3).
2. No Gerenciador de Bibliotecas, instale:
   - **RoboCore - Vespa**
   - **OneWire**
   - **DallasTemperature**
3. Abra `VespaControle/VespaControle.ino`.
4. Selecione a placa **ESP32 Dev Module** (ou "RoboCore Vespa", se disponível) e a porta.
5. Clique em **Carregar**. No Monitor Serial (115200) aparecem a rede e o endereço.

## API HTTP

A página usa estas rotas, que também podem ser chamadas por outros programas:

| Rota | Descrição |
|---|---|
| `GET /api/status` | Estado completo em JSON (servos, leds, temperatura, distância, bateria) |
| `GET /api/servo?id=0..3&angulo=0..180` | Move um servo |
| `GET /api/servos/centro` | Todos os servos em 90° |
| `GET /api/led?id=0..2&estado=0\|1` | Liga/desliga um LED (`id=todos` para todos) |

Exemplo de resposta de `/api/status`:

```json
{"servos":[90,90,45,180],"leds":[true,false,false],"temperatura":24.5,
 "distancia":32.1,"bateria_mV":7400,"clientes":1,"uptime_s":120}
```

`temperatura` e `distancia` vêm como `null` quando o sensor não responde ou o objeto
está fora de alcance.

## Arquivos

- `VespaControle/VespaControle.ino` — programa principal (Wi‑Fi, servidor web, sensores, atuadores)
- `VespaControle/config.h` — rede Wi‑Fi e pinos
- `VespaControle/pagina.h` — página web (HTML/CSS/JS embutido, funciona offline)
