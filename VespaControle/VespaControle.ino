// ============================================================================
//  VespaControle - Painel web de controle pela rede Wi-Fi da propria Vespa
//
//  A placa RoboCore Vespa cria uma rede Wi-Fi (Access Point). O usuario
//  conecta nessa rede e abre http://192.168.4.1 (ou http://vespa.local)
//  para controlar:
//    - 4 servomotores (S1..S4)
//    - 3 LEDs
//    - Sensor de temperatura DS18B20
//    - Sensor ultrassonico HC-SR04
//
//  Bibliotecas (Gerenciador de Bibliotecas da Arduino IDE):
//    - RoboCore - Vespa
//    - OneWire
//    - DallasTemperature
//  Placa: "RoboCore Vespa" (ou "ESP32 Dev Module") com o pacote esp32 3.x
// ============================================================================

#include <WiFi.h>
#include <WebServer.h>
#include <ESPmDNS.h>
#include <esp_wifi.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include "RoboCore_Vespa.h"

#include "config.h"
#include "pagina.h"
#include "dns_local.h"

// ---------------------------------------------------------------------------
// Objetos globais
// ---------------------------------------------------------------------------
WebServer server(80);
LocalDNS dnsServer;

VespaServo servos[SERVO_COUNT];
const uint8_t SERVO_PINS[SERVO_COUNT] = {VESPA_SERVO_S1, VESPA_SERVO_S2,
                                         VESPA_SERVO_S3, VESPA_SERVO_S4};
int servoAngles[SERVO_COUNT];

const uint8_t LED_PINS[LED_COUNT] = {LED1_PIN, LED2_PIN, LED3_PIN};
bool ledStates[LED_COUNT];

OneWire oneWire(TEMP_SENSOR_PIN);
DallasTemperature tempSensor(&oneWire);
float temperatureC = NAN;

float distanceCm = -1;  // -1 = fora de alcance / sem leitura

VespaBattery battery;

const char *resetReason = "";
uint32_t wifiDrops = 0;            // quantas vezes um aparelho caiu da rede

void onWiFiEvent(WiFiEvent_t event, WiFiEventInfo_t info) {
  if (event == ARDUINO_EVENT_WIFI_AP_STACONNECTED) {
    Serial.println("[wifi] aparelho conectou");
  } else if (event == ARDUINO_EVENT_WIFI_AP_STADISCONNECTED) {
    wifiDrops++;
    Serial.printf("[wifi] aparelho desconectou (motivo %d)\n", info.wifi_ap_stadisconnected.reason);
  }
}

unsigned long lastUltraRead = 0;
unsigned long lastTempRead = 0;

// ---------------------------------------------------------------------------
// Dispositivos
// ---------------------------------------------------------------------------
void setServo(uint8_t index, int angle) {
  angle = constrain(angle, 0, 180);
  servoAngles[index] = angle;
  servos[index].write(angle);
}

void setLed(uint8_t index, bool on) {
  ledStates[index] = on;
  digitalWrite(LED_PINS[index], on ? HIGH : LOW);
}

void readUltrasonic() {
  digitalWrite(ULTRA_TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(ULTRA_TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(ULTRA_TRIG_PIN, LOW);

  // timeout de 25 ms ~ 4 m (alcance maximo do HC-SR04)
  unsigned long duration = pulseIn(ULTRA_ECHO_PIN, HIGH, 25000UL);
  distanceCm = (duration == 0) ? -1 : duration * 0.0343f / 2.0f;
}

void readTemperature() {
  // Leitura nao bloqueante: le o resultado da conversao anterior
  // e ja solicita a proxima.
  float t = tempSensor.getTempCByIndex(0);
  temperatureC = (t == DEVICE_DISCONNECTED_C) ? NAN : t;
  tempSensor.requestTemperatures();
}

// ---------------------------------------------------------------------------
// Rotas HTTP
// ---------------------------------------------------------------------------
void sendJsonStatus() {
  String json;
  json.reserve(256);
  json += "{\"servos\":[";
  for (uint8_t i = 0; i < SERVO_COUNT; i++) {
    if (i) json += ',';
    json += servoAngles[i];
  }
  json += "],\"leds\":[";
  for (uint8_t i = 0; i < LED_COUNT; i++) {
    if (i) json += ',';
    json += ledStates[i] ? "true" : "false";
  }
  json += "],\"temperatura\":";
  json += isnan(temperatureC) ? String("null") : String(temperatureC, 1);
  json += ",\"distancia\":";
  json += (distanceCm < 0) ? String("null") : String(distanceCm, 1);
  json += ",\"bateria_mV\":";
  json += battery.readVoltage();
  json += ",\"clientes\":";
  json += WiFi.softAPgetStationNum();
  json += ",\"uptime_s\":";
  json += millis() / 1000;
  json += ",\"quedas\":";
  json += wifiDrops;
  json += ",\"reset\":\"";
  json += resetReason;
  json += '"';
  json += '}';

  server.sendHeader("Cache-Control", "no-store");
  server.send(200, "application/json", json);
}

void sendError(const char *msg) {
  server.send(400, "application/json", String("{\"erro\":\"") + msg + "\"}");
}

// GET /api/servo?id=0..3&angulo=0..180
void handleServo() {
  if (!server.hasArg("id") || !server.hasArg("angulo")) return sendError("use id e angulo");
  int id = server.arg("id").toInt();
  if (id < 0 || id >= SERVO_COUNT) return sendError("id invalido");
  setServo(id, server.arg("angulo").toInt());
  sendJsonStatus();
}

// GET /api/led?id=0..2&estado=0|1   (id=todos para todos os LEDs)
void handleLed() {
  if (!server.hasArg("id") || !server.hasArg("estado")) return sendError("use id e estado");
  bool on = server.arg("estado").toInt() != 0;
  if (server.arg("id") == "todos") {
    for (uint8_t i = 0; i < LED_COUNT; i++) setLed(i, on);
  } else {
    int id = server.arg("id").toInt();
    if (id < 0 || id >= LED_COUNT) return sendError("id invalido");
    setLed(id, on);
  }
  sendJsonStatus();
}

// GET /api/servos?s1=..&s2=..&s3=..&s4=..  -> move varios servos de uma vez
// (usado pelo "mouse" da pagina; parametros ausentes sao ignorados)
void handleServos() {
  for (uint8_t i = 0; i < SERVO_COUNT; i++) {
    String name = String("s") + (i + 1);
    if (server.hasArg(name)) setServo(i, server.arg(name).toInt());
  }
  sendJsonStatus();
}

// GET /api/servos/centro -> todos os servos em 90 graus
void handleServosCenter() {
  for (uint8_t i = 0; i < SERVO_COUNT; i++) setServo(i, 90);
  sendJsonStatus();
}

void handleRoot() {
  // sem cache: o navegador sempre carrega a pagina nova apos gravar o firmware
  server.sendHeader("Cache-Control", "no-cache, no-store, must-revalidate");
  server.sendHeader("Pragma", "no-cache");
  server.sendHeader("Expires", "0");
  server.send_P(200, "text/html; charset=utf-8", PAGINA_HTML);
}

// Testes de conectividade dos celulares. Respondendo "ha internet", o celular
// nao abre a janelinha de login (que desconecta ao ser fechada no iPhone) e
// nao abandona a rede da Vespa trocando para os dados moveis.
void handleConnectivityCheck() {
  String uri = server.uri();
  if (uri == "/generate_204" || uri == "/gen_204") {          // Android / Chrome
    server.send(204);
  } else if (uri == "/connecttest.txt") {                    // Windows
    server.send(200, "text/plain", "Microsoft Connect Test");
  } else if (uri == "/ncsi.txt") {                           // Windows (antigo)
    server.send(200, "text/plain", "Microsoft NCSI");
  } else {                                                   // Apple (iOS/macOS)
    server.send(200, "text/html",
                "<HTML><HEAD><TITLE>Success</TITLE></HEAD><BODY>Success</BODY></HTML>");
  }
}

// Qualquer outro endereco volta para a pagina principal
void handleNotFound() {
  if (server.uri().startsWith("/api/")) {
    server.send(404, "application/json", "{\"erro\":\"rota nao encontrada\"}");
    return;
  }
  server.sendHeader("Location", String("http://") + WiFi.softAPIP().toString() + "/", true);
  server.send(302, "text/plain", "");
}

// ---------------------------------------------------------------------------
// Setup / Loop
// ---------------------------------------------------------------------------
const char *describeResetReason(esp_reset_reason_t r) {
  switch (r) {
    case ESP_RST_POWERON:  return "ligado";
    case ESP_RST_BROWNOUT: return "queda de tensao (brownout)";
    case ESP_RST_PANIC:    return "erro de software";
    case ESP_RST_INT_WDT:
    case ESP_RST_TASK_WDT:
    case ESP_RST_WDT:      return "watchdog";
    case ESP_RST_SW:       return "reinicio por software";
    case ESP_RST_EXT:      return "botao reset";
    default:               return "outro";
  }
}

void setupWiFi() {
  WiFi.onEvent(onWiFiEvent);
  WiFi.persistent(false);       // nao grava config na flash a cada boot
  WiFi.mode(WIFI_AP);
  WiFi.setSleep(false);         // sem economia de energia: resposta rapida e estavel

  WiFi.softAP(WIFI_SSID, WIFI_PASSWORD, WIFI_CHANNEL, false, WIFI_MAX_CLIENTS);
  delay(100);                   // aguarda o AP subir antes de configurar o IP
  WiFi.softAPConfig(IPAddress(AP_IP), IPAddress(AP_GATEWAY), IPAddress(AP_SUBNET));
  WiFi.setTxPower(WIFI_TX_POWER);

  // Aparelhos parados (celular com tela apagada) nao derrubam a conexao tao cedo
  esp_wifi_set_inactive_time(WIFI_IF_AP, 60);

  // DNS: so responde testes de conectividade e o nome da placa (ver dns_local.h)
  dnsServer.begin(WiFi.softAPIP(), MDNS_NAME);

  if (MDNS.begin(MDNS_NAME)) {
    MDNS.addService("http", "tcp", 80);
  }

  Serial.println();
  Serial.println("=== Vespa Controle v1.3 (joystick) ===");
  Serial.print("Rede Wi-Fi: "); Serial.println(WIFI_SSID);
  Serial.print("Senha:      "); Serial.println(WIFI_PASSWORD);
  Serial.print("Acesse:     http://"); Serial.println(WiFi.softAPIP());
  Serial.print("      ou:   http://"); Serial.print(MDNS_NAME); Serial.println(".local");
  Serial.print("Ultimo reset: "); Serial.println(resetReason);
}

void setupServer() {
  server.on("/", HTTP_GET, handleRoot);
  server.on("/api/status", HTTP_GET, sendJsonStatus);
  server.on("/api/servo", HTTP_GET, handleServo);
  server.on("/api/servos", HTTP_GET, handleServos);
  server.on("/api/servos/centro", HTTP_GET, handleServosCenter);
  server.on("/api/led", HTTP_GET, handleLed);
  const char *checks[] = {"/generate_204", "/gen_204", "/hotspot-detect.html",
                          "/library/test/success.html", "/connecttest.txt", "/ncsi.txt"};
  for (const char *uri : checks) server.on(uri, HTTP_GET, handleConnectivityCheck);
  server.onNotFound(handleNotFound);
  server.begin();
}

void setup() {
  Serial.begin(115200);
  resetReason = describeResetReason(esp_reset_reason());

  // 1) Wi-Fi primeiro: a rede aparece o mais rapido possivel
  setupWiFi();
  setupServer();

  for (uint8_t i = 0; i < LED_COUNT; i++) {
    pinMode(LED_PINS[i], OUTPUT);
    setLed(i, false);
  }

  pinMode(ULTRA_TRIG_PIN, OUTPUT);
  pinMode(ULTRA_ECHO_PIN, INPUT);
  digitalWrite(ULTRA_TRIG_PIN, LOW);

  tempSensor.begin();
  tempSensor.setWaitForConversion(false);
  tempSensor.requestTemperatures();

  // 2) Servos um de cada vez: os 4 se movendo juntos ao ligar puxam muita
  //    corrente e podem reiniciar a placa (a rede some e volta)
  for (uint8_t i = 0; i < SERVO_COUNT; i++) {
    servos[i].attach(SERVO_PINS[i], SERVO_PULSE_MIN, SERVO_PULSE_MAX);
    setServo(i, SERVO_START_ANGLE);
    unsigned long t0 = millis();
    while (millis() - t0 < SERVO_START_DELAY) {   // continua atendendo a rede
      dnsServer.process();
      server.handleClient();
      delay(1);
    }
  }
}

void loop() {
  dnsServer.process();
  server.handleClient();

  unsigned long now = millis();
  if (now - lastUltraRead >= ULTRA_READ_INTERVAL) {
    lastUltraRead = now;
    readUltrasonic();
  }
  if (now - lastTempRead >= TEMP_READ_INTERVAL) {
    lastTempRead = now;
    readTemperature();
  }
  delay(1);   // cede tempo para as tarefas do Wi-Fi
}
