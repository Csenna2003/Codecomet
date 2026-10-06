// ============================================================================
//  config.h - Configuracoes do projeto (Wi-Fi e pinos)
//  Placa: RoboCore Vespa (ESP32)
// ============================================================================
#pragma once

// ---------------------------------------------------------------------------
// Rede Wi-Fi criada pela propria Vespa (modo Access Point)
// ---------------------------------------------------------------------------
#define WIFI_SSID      "Vespa-Controle"   // nome da rede que aparece no celular/PC
#define WIFI_PASSWORD  "vespa1234"        // minimo 8 caracteres (ou "" para rede aberta)
#define WIFI_CHANNEL   6
#define WIFI_MAX_CLIENTS 4

// Endereco IP da placa na rede criada (acesse http://192.168.4.1)
#define AP_IP          192, 168, 4, 1
#define AP_GATEWAY     192, 168, 4, 1
#define AP_SUBNET      255, 255, 255, 0

// Nome amigavel: http://vespa.local (mDNS) - qualquer endereco digitado
// tambem e redirecionado para a placa pelo servidor DNS interno.
#define MDNS_NAME      "vespa"

// ---------------------------------------------------------------------------
// Servos - conectores dedicados S1..S4 da Vespa (definidos pela biblioteca)
//   S1 = GPIO26, S2 = GPIO25, S3 = GPIO33, S4 = GPIO32
// ---------------------------------------------------------------------------
#define SERVO_COUNT        4
#define SERVO_PULSE_MIN    500    // [us] ajuste se o seu servo nao chegar a 0/180
#define SERVO_PULSE_MAX    2500   // [us]
#define SERVO_START_ANGLE  90     // posicao inicial ao ligar

// ---------------------------------------------------------------------------
// Pinos livres da Vespa usados pelos demais dispositivos.
// Pinos RESERVADOS pela placa (NAO usar):
//   13, 14, 27, 4  -> driver dos motores DC
//   15             -> LED da placa
//   26, 25, 33, 32 -> conectores de servo
//   34             -> leitura da bateria
//   35             -> botao
// Confira no pinout da sua Vespa se os pinos abaixo estao acessiveis
// nos conectores de expansao; altere aqui se necessario.
// ---------------------------------------------------------------------------

// LEDs (com resistor de 220R-330R em serie)
#define LED_COUNT  3
#define LED1_PIN   16
#define LED2_PIN   17
#define LED3_PIN   18

// Sensor ultrassonico HC-SR04
//   ATENCAO: o ECHO do HC-SR04 sai em 5V. Use divisor de tensao
//   (ex.: 1k + 2k) para baixar para ~3,3V antes de ligar no ESP32.
#define ULTRA_TRIG_PIN  19
#define ULTRA_ECHO_PIN  23

// Sensor de temperatura DS18B20 (OneWire)
//   Resistor de pull-up de 4,7k entre DATA e 3,3V.
#define TEMP_SENSOR_PIN 21

// Intervalos de leitura [ms]
#define ULTRA_READ_INTERVAL  150
#define TEMP_READ_INTERVAL   1000
