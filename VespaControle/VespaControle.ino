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
#include <DNSServer.h>
#include <ESPmDNS.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include "RoboCore_Vespa.h"

#include "config.h"
#include "pagina.h"

// ---------------------------------------------------------------------------
// Objetos globais
// ---------------------------------------------------------------------------
WebServer server(80);
DNSServer dnsServer;

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

// GET /api/servos/centro -> todos os servos em 90 graus
void handleServosCenter() {
  for (uint8_t i = 0; i < SERVO_COUNT; i++) setServo(i, 90);
  sendJsonStatus();
}

void handleRoot() {
  server.send_P(200, "text/html; charset=utf-8", PAGINA_HTML);
}

// Qualquer outro endereco (captive portal) volta para a pagina principal
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
void setupWiFi() {
  WiFi.mode(WIFI_AP);
  WiFi.softAPConfig(IPAddress(AP_IP), IPAddress(AP_GATEWAY), IPAddress(AP_SUBNET));
  WiFi.softAP(WIFI_SSID, WIFI_PASSWORD, WIFI_CHANNEL, false, WIFI_MAX_CLIENTS);

  // Responde todas as consultas DNS com o IP da Vespa
  dnsServer.start(53, "*", WiFi.softAPIP());

  if (MDNS.begin(MDNS_NAME)) {
    MDNS.addService("http", "tcp", 80);
  }

  Serial.println();
  Serial.println("=== Vespa Controle ===");
  Serial.print("Rede Wi-Fi: "); Serial.println(WIFI_SSID);
  Serial.print("Senha:      "); Serial.println(WIFI_PASSWORD);
  Serial.print("Acesse:     http://"); Serial.println(WiFi.softAPIP());
  Serial.print("      ou:   http://"); Serial.print(MDNS_NAME); Serial.println(".local");
}

void setupServer() {
  server.on("/", HTTP_GET, handleRoot);
  server.on("/api/status", HTTP_GET, sendJsonStatus);
  server.on("/api/servo", HTTP_GET, handleServo);
  server.on("/api/servos/centro", HTTP_GET, handleServosCenter);
  server.on("/api/led", HTTP_GET, handleLed);
  server.onNotFound(handleNotFound);
  server.begin();
}

void setup() {
  Serial.begin(115200);

  for (uint8_t i = 0; i < SERVO_COUNT; i++) {
    servos[i].attach(SERVO_PINS[i], SERVO_PULSE_MIN, SERVO_PULSE_MAX);
    setServo(i, SERVO_START_ANGLE);
  }

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

  setupWiFi();
  setupServer();
}

void loop() {
  dnsServer.processNextRequest();
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
}
