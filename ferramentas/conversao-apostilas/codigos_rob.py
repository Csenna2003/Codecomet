# Código dos Guias de Robótica reconstruído à mão (o docx perdeu as quebras de linha). Chave: módulo e aula (AP = projeto).
C = {}
C['R1A7'] = '''quando a bandeira verde for clicada
  vá para x: -100 y: 100
  mova 100 passos
  gire 90 graus para a direita
  mova 50 passos'''
C['R1A8'] = '''quando a bandeira verde for clicada
  apague tudo
  abaixe a caneta
  repita 4 vezes
    mova 100 passos
    espere 0,3 segundo
    gire 90 graus para a direita
    espere 0,3 segundo
  fim do repita
  levante a caneta'''
C['R1A9'] = '''quando a bandeira verde for clicada
  sempre
    se tocando na cor da parede então
      mova -10 passos
      gire 90 graus para a direita
    senão
      mova 5 passos
    fim do se
  fim do sempre'''
C['R1A10'] = '''definir limiar para ____   // ponto médio das suas medições
para sempre
  imprimir “toque:” e leitura digital do pino 2
  imprimir “luz:” e leitura analógica do pino A0
  se leitura analógica A0 > limiar então
    acender o LED verde (pino 8) e apagar o vermelho
  senão
    acender o LED vermelho (pino 9) e apagar o verde
  esperar 0,2 segundo'''
C['R1A11'] = '''quando a bandeira verde for clicada
  defina toques para 0
  sempre
    se tecla espaço pressionada então
      adicione 1 a toques
      espere até não tecla espaço pressionada
    se toques = 5 então
      toque o som Miau até o fim
      defina toques para 0'''
C['R1A12'] = '''// Programa com defeito: deveria parar ao tocar a parede
quando a bandeira verde for clicada
  repita 4 vezes                        // erro 1: deveria ser “sempre”
    se não tocando na cor da parede     // erro 2: condição invertida
    então pare todos
    senão mova 5 passos

// Correção:
// troque “repita 4 vezes” por “sempre”
// troque “não tocando” por “tocando”'''
C['R1AP'] = '''quando iniciar
  definir limite para 10   // diferença mínima entre os sensores
  para sempre
    definir esquerda para luz (porta 1)
    definir direita para luz (porta 2)
    se esquerda > 90 e direita > 90 então
      parar   // chegou perto da lanterna
    senão se esquerda - direita > limite então
      girar à esquerda, potência 40%
    senão se direita - esquerda > limite então
      girar à direita, potência 40%
    senão
      mover para frente, potência 50%
    esperar 0,1 segundo'''
C['R2A5'] = '''void setup() {
  pinMode(8, OUTPUT);   // pino 8 será uma saída
}

void loop() {
  digitalWrite(8, HIGH);   // acende
  delay(1000);             // espera 1 s
  digitalWrite(8, LOW);    // apaga
  delay(1000);
}'''
C['R2A6'] = '''void setup() {
  for (int i = 8; i <= 11; i++) {
    pinMode(i, OUTPUT);   // configura os 4 pinos
  }
}

void loop() {
  for (int i = 8; i <= 11; i++) {   // ida
    digitalWrite(i, HIGH);
    delay(100);
    digitalWrite(i, LOW);
  }
  for (int i = 10; i >= 9; i--) {   // volta
    digitalWrite(i, HIGH);
    delay(100);
    digitalWrite(i, LOW);
  }
}'''
C['R2A7'] = '''const int BOTAO = 2;
const int LED = 8;
bool estadoLed = false;

void setup() {
  pinMode(BOTAO, INPUT_PULLUP);
  pinMode(LED, OUTPUT);
}

void loop() {
  if (digitalRead(BOTAO) == LOW) {   // apertado
    estadoLed = !estadoLed;          // inverte o estado
    digitalWrite(LED, estadoLed);
    delay(50);                       // evita o repique
    while (digitalRead(BOTAO) == LOW) {}   // espera soltar
    delay(50);
  }
}'''
C['R2A8'] = '''int contador = 0;
bool botaoAnterior = HIGH;   // solto

void setup() {
  pinMode(2, INPUT_PULLUP);
  for (int p = 8; p <= 10; p++) pinMode(p, OUTPUT);
}

void loop() {
  bool botao = digitalRead(2);
  if (botaoAnterior == HIGH && botao == LOW) {   // acabou de apertar
    contador = (contador + 1) % 4;               // 0, 1, 2, 3, 0...
    delay(50);                                   // repique
  }
  botaoAnterior = botao;
  digitalWrite(8, contador >= 1);
  digitalWrite(9, contador >= 2);
  digitalWrite(10, contador >= 3);
}'''
C['R2A9'] = '''void setup() {
  pinMode(9, OUTPUT);
}

void loop() {
  int leitura = analogRead(A0);                  // 0 a 1023
  int brilho = map(leitura, 0, 1023, 0, 255);    // 0 a 255
  analogWrite(9, brilho);
}'''
C['R2A10'] = '''const int LED = 9;   // pino com PWM (~)

void setup() {
  pinMode(LED, OUTPUT);
}

void loop() {
  for (int b = 0; b <= 255; b++) {   // sobe
    analogWrite(LED, b);
    delay(5);
  }
  for (int b = 255; b >= 0; b--) {   // desce
    analogWrite(LED, b);
    delay(5);
  }
}'''
C['R2A11'] = '''const int BUZZER = 8;
// Dó, Ré, Mi, Fá, Sol, Sol, Fá, Mi, Ré, Dó
int notas[] = {262, 294, 330, 349, 392, 392, 349, 330, 294, 262};

void setup() {}

void loop() {
  for (int i = 0; i < 10; i++) {
    tone(BUZZER, notas[i], 300);   // toca 300 ms
    delay(350);                    // 50 ms de pausa
  }
  noTone(BUZZER);
  delay(1500);   // espera antes de repetir
}'''
C['R2A12'] = '''void setup() {
  Serial.begin(9600);   // inicia a comunicação
}

void loop() {
  int luz = analogRead(A0);
  int pot = analogRead(A1);
  Serial.print("Luz: ");
  Serial.print(luz);
  Serial.print("  Pot: ");
  Serial.println(pot);   // pula linha
  delay(200);
}'''
C['R2AP'] = '''const int V_VERM = 2, V_AMAR = 3, V_VERDE = 4;   // veículos
const int P_VERM = 5, P_VERDE = 6;               // pedestres
const int BOTAO = 7, BUZZER = 9, LDR = A0;
const int LIMITE_ESCURO = 300;   // ajustar com o Monitor Serial
bool pedido = false;

void setup() {
  for (int p = 2; p <= 6; p++) pinMode(p, OUTPUT);
  pinMode(BOTAO, INPUT_PULLUP);
}

void loop() {
  if (analogRead(LDR) < LIMITE_ESCURO) {
    modoNoturno();   // amarelo piscando
  } else {
    cicloNormal();   // verde, amarelo, vermelho
  }
}

// Funções a escrever em equipe:
// cicloNormal(), modoNoturno(), travessiaPedestres(), aguardar(ms)
// 'aguardar' consulta o botão e guarda o pedido sem travar'''
C['R3A1'] = '''const int ledVerde = 8;
const int ledVermelho = 9;
const int tempoEspera = 500;   // meio segundo entre trocas

void setup() {
  pinMode(ledVerde, OUTPUT);
  pinMode(ledVermelho, OUTPUT);
}

void loop() {
  digitalWrite(ledVerde, HIGH);
  digitalWrite(ledVermelho, LOW);
  delay(tempoEspera);
  digitalWrite(ledVerde, LOW);
  digitalWrite(ledVermelho, HIGH);
  delay(tempoEspera);
}'''
C['R3A2'] = '''void setup() {
  pinMode(8, OUTPUT);
  pinMode(9, OUTPUT);
  pinMode(10, OUTPUT);
}

void loop() {
  piscar(8, 200);
  piscar(9, 500);
  piscar(10, 1000);
}

// pisca o LED do pino indicado, com o tempo (ms) de cada metade
void piscar(int pino, int tempo) {
  digitalWrite(pino, HIGH);
  delay(tempo);
  digitalWrite(pino, LOW);
  delay(tempo);
}'''
C['R3A3'] = '''const int pinos[] = {2, 3, 4, 5, 6};
const int total = 5;

void setup() {
  for (int i = 0; i < total; i++) {
    pinMode(pinos[i], OUTPUT);
  }
}

void loop() {
  for (int i = 0; i < total; i++) {   // ida
    digitalWrite(pinos[i], HIGH);
    delay(150);
    digitalWrite(pinos[i], LOW);
  }
  for (int i = total - 2; i > 0; i--) {   // volta
    digitalWrite(pinos[i], HIGH);
    delay(150);
    digitalWrite(pinos[i], LOW);
  }
}'''
C['R3A4'] = '''#include <Wire.h>
#include <LiquidCrystal_I2C.h>

LiquidCrystal_I2C lcd(0x27, 16, 2);   // endereço, colunas, linhas

void setup() {
  lcd.init();
  lcd.backlight();
  lcd.setCursor(0, 0);
  lcd.print("Adoro Robotica");
}

void loop() {
  lcd.setCursor(0, 1);
  lcd.print("Tempo: ");
  lcd.print(millis() / 1000);
  lcd.print(" s   ");   // espaços apagam sobras
  delay(200);
}'''
C['R3A5'] = '''#include <Servo.h>

Servo braco;
const int pinoPot = A0;

void setup() {
  braco.attach(9);
}

void loop() {
  int leitura = analogRead(pinoPot);            // 0 a 1023
  int angulo = map(leitura, 0, 1023, 0, 180);
  braco.write(angulo);
  delay(15);   // tempo para o servo chegar
}'''
C['R3A6'] = '''#include <Wire.h>
#include <LiquidCrystal_I2C.h>

LiquidCrystal_I2C lcd(0x27, 16, 2);
const int trig = 9, echo = 10;

float lerDistancia() {
  digitalWrite(trig, LOW);
  delayMicroseconds(2);
  digitalWrite(trig, HIGH);
  delayMicroseconds(10);
  digitalWrite(trig, LOW);
  long duracao = pulseIn(echo, HIGH, 30000);   // limite 30 ms
  return duracao * 0.0343 / 2;
}

void setup() {
  pinMode(trig, OUTPUT);
  pinMode(echo, INPUT);
  lcd.init();
  lcd.backlight();
  lcd.print("Distancia:");
}

void loop() {
  lcd.setCursor(0, 1);
  lcd.print(lerDistancia(), 1);
  lcd.print(" cm   ");
  delay(200);
}'''
C['R3A7'] = '''void motorEsq(int vel) {   // -255 (ré) a 255 (frente)
  digitalWrite(7, vel > 0);
  digitalWrite(8, vel < 0);
  analogWrite(5, abs(vel));
}

void motorDir(int vel) {
  digitalWrite(4, vel > 0);
  digitalWrite(12, vel < 0);
  analogWrite(6, abs(vel));
}

void setup() {
  int pinos[] = {4, 5, 6, 7, 8, 12};
  for (int i = 0; i < 6; i++) pinMode(pinos[i], OUTPUT);
}

void loop() {
  motorEsq(200); motorDir(200); delay(2000);     // frente
  motorEsq(0); motorDir(0); delay(1000);         // parado
  motorEsq(-200); motorDir(-200); delay(2000);   // ré
  motorEsq(0); motorDir(0); delay(1000);
}'''
C['R3A8'] = '''const int sensEsq = 2, sensDir = 3;
const int PRETO = HIGH;   // troque por LOW se o seu módulo for inverso

void setup() {
  pinMode(sensEsq, INPUT);
  pinMode(sensDir, INPUT);
  Serial.begin(9600);
}

void loop() {
  bool esqNaLinha = (digitalRead(sensEsq) == PRETO);
  bool dirNaLinha = (digitalRead(sensDir) == PRETO);
  if (esqNaLinha) Serial.println("Virar a esquerda");
  else if (dirNaLinha) Serial.println("Virar a direita");
  else Serial.println("Seguir reto");
  delay(100);
}'''
C['R3A9'] = '''enum Estado { PARADO, ANDANDO, DESVIANDO };
Estado estado = PARADO;

float lerDistancia() {   // da aula 6, em cm
  digitalWrite(9, HIGH);
  delayMicroseconds(10);
  digitalWrite(9, LOW);
  return pulseIn(10, HIGH, 30000) * 0.0343 / 2;
}

void setup() {
  pinMode(2, INPUT_PULLUP);   // apertado = LOW
  pinMode(9, OUTPUT);
  pinMode(10, INPUT);
  Serial.begin(9600);
}

void loop() {
  long d = lerDistancia();
  bool perto = (d > 0 && d < 20);
  if (estado == PARADO && digitalRead(2) == LOW) estado = ANDANDO;
  else if (estado == ANDANDO && perto) estado = DESVIANDO;
  else if (estado == DESVIANDO && !perto) estado = ANDANDO;
  Serial.println(estado);   // 0 PARADO, 1 ANDANDO, 2 DESVIANDO
  delay(100);
  // motores entram em cada estado (aula 7)
}'''
C['R3A10'] = '''const int ledA = 8, ledB = 9, botao = 2;
const unsigned long intervaloA = 500;
unsigned long ultimoA = 0;
bool estadoA = false;

void setup() {
  pinMode(ledA, OUTPUT);
  pinMode(ledB, OUTPUT);
  pinMode(botao, INPUT_PULLUP);
}

void loop() {
  unsigned long agora = millis();
  if (agora - ultimoA >= intervaloA) {   // tarefa 1: piscar
    ultimoA = agora;
    estadoA = !estadoA;
    digitalWrite(ledA, estadoA);
  }
  digitalWrite(ledB, digitalRead(botao) == LOW);   // tarefa 2: botão
}'''
C['R3A11'] = '''// usa motorEsq(vel) e motorDir(vel) da aula 7
const int velBase = 150;
const float Kp = 0.2;

void setup() {
  // pinMode dos motores igual à aula 7
}

void loop() {
  int esq = analogRead(A0);
  int dir = analogRead(A1);
  int erro = esq - dir;   // positivo = mais preto no lado esquerdo
  int correcao = Kp * erro;
  int velEsq = constrain(velBase - correcao, 0, 255);
  int velDir = constrain(velBase + correcao, 0, 255);
  motorEsq(velEsq);
  motorDir(velDir);
}'''
C['R3A12'] = '''#include <Stepper.h>

const int passosPorVolta = 2048;
Stepper motor(passosPorVolta, 8, 10, 9, 11);   // ordem IN1, IN3, IN2, IN4

void setup() {
  motor.setSpeed(10);   // 10 rpm
  pinMode(2, INPUT_PULLUP);
}

void loop() {
  if (digitalRead(2) == LOW) {             // botão apertado
    motor.step(passosPorVolta / 8);        // 256 passos = 45 graus
    delay(300);                            // evita repetir o aperto
  }
}'''
C['R3AP'] = '''#include <Servo.h>

enum Estado { PARADO, ANDANDO, OLHANDO, DESVIANDO };
Estado estado = PARADO;
Servo olho;
const int distanciaMinima = 20;   // cm
long distEsq, distDir;

void setup() {
  olho.attach(3);
  olho.write(90);
  // pinMode dos motores, trig, echo e botão (pino 2, INPUT_PULLUP)
}

void loop() {
  switch (estado) {
    case PARADO:    /* se botão apertado -> ANDANDO */ break;
    case ANDANDO:   /* andar(); se lerDistancia() < distanciaMinima -> parar(); OLHANDO */ break;
    case OLHANDO:   /* olho em 30 e 150, ler distEsq e distDir; -> DESVIANDO */ break;
    case DESVIANDO: /* girar() para o lado maior por um tempo; olho em 90; -> ANDANDO */ break;
  }
}'''
C['R4A3'] = '''const int IN1 = 7;
const int IN2 = 8;
const int ENA = 9;   // pino PWM

void setup() {
  pinMode(IN1, OUTPUT);
  pinMode(IN2, OUTPUT);
  pinMode(ENA, OUTPUT);
  digitalWrite(IN1, HIGH);   // sentido de giro
  digitalWrite(IN2, LOW);
}

void loop() {
  for (int v = 0; v <= 255; v += 5) {
    analogWrite(ENA, v);   // velocidade 0 a 255
    delay(100);
  }
  for (int v = 255; v >= 0; v -= 5) {
    analogWrite(ENA, v);
    delay(100);
  }
}'''
C['R4A12'] = '''// Teste dos dois motores: frente 2 s, para 1 s
const int IN1 = 7, IN2 = 8, ENA = 9;   // motor A
const int IN3 = 4, IN4 = 5, ENB = 6;   // motor B

void setup() {
  for (int p = 4; p <= 9; p++) pinMode(p, OUTPUT);
}

void loop() {
  digitalWrite(IN1, HIGH); digitalWrite(IN2, LOW);
  digitalWrite(IN3, HIGH); digitalWrite(IN4, LOW);
  analogWrite(ENA, 180);   // velocidade 0 a 255
  analogWrite(ENB, 180);
  delay(2000);
  analogWrite(ENA, 0);
  analogWrite(ENB, 0);
  delay(1000);
}'''
C['R4AP'] = '''// Esqueleto: robô móvel com chassi impresso
const int IN1 = 7, IN2 = 8, ENA = 9;   // motor A
const int IN3 = 4, IN4 = 5, ENB = 6;   // motor B
const int TRIG = 12, ECHO = 13;        // HC-SR04
int velocidade = 180;                  // PWM de 0 a 255

void setup() {
  for (int p = 4; p <= 9; p++) pinMode(p, OUTPUT);
  pinMode(TRIG, OUTPUT);
  pinMode(ECHO, INPUT);
}

long distanciaCm() {
  digitalWrite(TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG, LOW);
  return pulseIn(ECHO, HIGH, 30000) / 58;
}

void andar(int a, int b) {   // negativo = ré
  digitalWrite(IN1, a > 0); digitalWrite(IN2, a < 0);
  digitalWrite(IN3, b > 0); digitalWrite(IN4, b < 0);
  analogWrite(ENA, abs(a));
  analogWrite(ENB, abs(b));
}

void loop() {
  long d = distanciaCm();
  if (d > 0 && d < 20) {
    andar(velocidade, -velocidade);
    delay(400);
  } else andar(velocidade, velocidade);
}'''
C['R5A2'] = '''const int LED = 2;   // LED embutido na maioria das placas

void setup() {
  pinMode(LED, OUTPUT);
  Serial.begin(115200);
}

void loop() {
  digitalWrite(LED, HIGH);
  Serial.println("LED aceso");
  delay(1000);
  digitalWrite(LED, LOW);
  delay(1000);
}'''
C['R5A3'] = '''#include <WiFi.h>

const char* ssid = "NOME_DA_REDE";
const char* senha = "SENHA_DA_REDE";

void setup() {
  Serial.begin(115200);
  WiFi.begin(ssid, senha);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\\nConectado!");
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());
  Serial.print("Sinal (dBm): ");
  Serial.println(WiFi.RSSI());
}

void loop() {}'''
C['R5A4'] = '''#include <DHT.h>

#define PINO_DHT 4
DHT dht(PINO_DHT, DHT22);

void setup() {
  Serial.begin(115200);
  dht.begin();
}

void loop() {
  delay(2000);   // intervalo mínimo do sensor
  float t = dht.readTemperature();
  float u = dht.readHumidity();
  if (isnan(t) || isnan(u)) {
    Serial.println("Falha na leitura");
    return;
  }
  Serial.printf("Temp: %.1f C | Umid: %.0f %%\\n", t, u);
}'''
C['R5A5'] = '''#include <WiFi.h>
#include <WebServer.h>

WebServer server(80);
const int LED = 23;

void pagina() {
  server.send(200, "text/html",
    "<h1>Painel</h1><a href='/ligar'>Ligar</a> | <a href='/desligar'>Desligar</a>");
}
void ligar() { digitalWrite(LED, HIGH); pagina(); }
void desligar() { digitalWrite(LED, LOW); pagina(); }

void setup() {
  pinMode(LED, OUTPUT);
  WiFi.begin("NOME_DA_REDE", "SENHA_DA_REDE");
  while (WiFi.status() != WL_CONNECTED) delay(500);
  server.on("/", pagina);
  server.on("/ligar", ligar);
  server.on("/desligar", desligar);
  server.begin();
}

void loop() { server.handleClient(); }'''
C['R5A6'] = '''#include <BluetoothSerial.h>

BluetoothSerial SerialBT;
const int LED = 2;

void setup() {
  Serial.begin(115200);
  pinMode(LED, OUTPUT);
  SerialBT.begin("RoboAdoro");   // nome visível no celular
}

void loop() {
  if (SerialBT.available()) {
    char c = SerialBT.read();
    Serial.println(c);
    if (c == 'F') digitalWrite(LED, HIGH);   // frente
    if (c == 'P') digitalWrite(LED, LOW);    // parar
  }
}'''
C['R5A7'] = '''#include <WiFi.h>
#include <PubSubClient.h>

WiFiClient rede;
PubSubClient mqtt(rede);
const char* MEU = "adoro-m5/sala1/placaA";     // publica aqui
const char* OUTRO = "adoro-m5/sala1/placaB";   // escuta aqui
int anterior = -1;

void aoReceber(char* t, byte* p, unsigned int n) {
  digitalWrite(2, (n > 0 && p[0] == '1') ? HIGH : LOW);
}

void setup() {
  pinMode(2, OUTPUT);
  pinMode(0, INPUT_PULLUP);
  WiFi.begin("NOME_DA_REDE", "SENHA_DA_REDE");
  while (WiFi.status() != WL_CONNECTED) delay(500);
  mqtt.setServer("test.mosquitto.org", 1883);
  mqtt.setCallback(aoReceber);
}

void loop() {
  if (!mqtt.connected()) {
    if (mqtt.connect("adoroA-7391")) mqtt.subscribe(OUTRO);
    else { delay(2000); return; }
  }
  mqtt.loop();
  int b = !digitalRead(0);   // 1 = BOOT pressionado
  if (b != anterior) {
    mqtt.publish(MEU, b ? "1" : "0");
    anterior = b;
  }
}'''
C['R5A8'] = '''#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>

DHT dht(4, DHT22);
String chave = "SUA_WRITE_API_KEY";

void setup() {
  dht.begin();
  WiFi.begin("NOME_DA_REDE", "SENHA_DA_REDE");
  while (WiFi.status() != WL_CONNECTED) delay(500);
}

void loop() {
  float t = dht.readTemperature(), u = dht.readHumidity();
  if (!isnan(t) && !isnan(u)) {
    HTTPClient http;
    http.begin("http://api.thingspeak.com/update?api_key=" + chave +
               "&field1=" + String(t) + "&field2=" + String(u));
    Serial.println(http.GET());   // 200 = ok
    http.end();
  }
  delay(20000);   // respeita o limite de 15 s
}'''
C['R5A9'] = '''const int RELE = 26;
// Este módulo liga com LOW (ativo em nível baixo)

void setup() {
  pinMode(RELE, OUTPUT);
  digitalWrite(RELE, HIGH);   // começa desligado
}

void loop() {
  digitalWrite(RELE, LOW);    // liga a carga
  delay(3000);
  digitalWrite(RELE, HIGH);   // desliga a carga
  delay(3000);
}'''
C['R5A10'] = '''#include <DHT.h>

DHT dht(4, DHT22);
const int RELE = 26;
const float TMAX = 28.0, TMIN = 26.0;
bool ligado = false;

void setup() {
  pinMode(RELE, OUTPUT);
  digitalWrite(RELE, HIGH);   // relé ativo em LOW: começa desligado
  dht.begin();
}

void loop() {
  delay(2000);
  float t = dht.readTemperature();
  if (isnan(t)) return;
  if (t > TMAX && !ligado) { ligado = true; digitalWrite(RELE, LOW); }
  if (t < TMIN && ligado) { ligado = false; digitalWrite(RELE, HIGH); }
}'''
C['R5A11'] = '''// Dentro da função de cada rota (ligar, desligar, pagina):
const char* USUARIO = "professor";
const char* SENHA_PAINEL = "troque-esta-frase-longa-42";

void ligar() {
  if (!server.authenticate(USUARIO, SENHA_PAINEL)) {
    return server.requestAuthentication();   // pede login
  }
  digitalWrite(LED, HIGH);
  pagina();
}'''
C['R5A12'] = '''RTC_DATA_ATTR int acordou = 0;   // guarda valor durante o sono

void setup() {
  Serial.begin(115200);
  pinMode(2, OUTPUT);
  acordou++;
  Serial.printf("Despertar numero %d\\n", acordou);
  digitalWrite(2, HIGH);
  delay(2000);   // aqui entraria ler e enviar o dado
  digitalWrite(2, LOW);
  esp_sleep_enable_timer_wakeup(10 * 1000000ULL);   // 10 s em microssegundos
  esp_deep_sleep_start();
}

void loop() {}   // nunca é executado'''
C['R5AP'] = '''#include <WiFi.h>
#include <HTTPClient.h>
#include <DHT.h>

DHT dht(4, DHT22);
const int RELE = 26;
const float TMAX = 28.0, TMIN = 26.0;
bool ligado = false;
unsigned long ultimoEnvio = 0;

void conectarWiFi() { /* WiFi.begin e espera por WL_CONNECTED */ }
void enviarNuvem(float t, float u) { /* HTTPClient para o ThingSpeak */ }
void aplicarRegra(float t) { /* histerese: liga acima de TMAX, desliga abaixo de TMIN */ }

void setup() {
  pinMode(RELE, OUTPUT);
  digitalWrite(RELE, HIGH);
  dht.begin();
  conectarWiFi();
}

void loop() {
  float t = dht.readTemperature(), u = dht.readHumidity();
  if (!isnan(t)) aplicarRegra(t);
  if (millis() - ultimoEnvio > 20000) {
    enviarNuvem(t, u);
    ultimoEnvio = millis();
  }
}'''
C['R6A2'] = '''kp, ki, kd = 1.0, 0.0, 0.0          # ganhos: mude e rode de novo
alvo, vel = 100.0, 0.0              # velocidade desejada e atual
integral, erro_ant, dt = 0.0, 0.0, 0.1
for passo in range(80):
    erro = alvo - vel
    integral += erro * dt
    derivada = (erro - erro_ant) / dt
    saida = kp*erro + ki*integral + kd*derivada
    erro_ant = erro
    vel += (saida - 0.5*vel) * dt   # motor com atrito
    print(passo, round(vel, 1))'''
C['R6A3'] = '''const int S[5] = {2, 3, 4, 11, 12};   // sensores da esquerda p/ direita
const int PESO[5] = {-2, -1, 0, 1, 2};
float kp = 25, kd = 60;
int base = 120;
float erroAnt = 0;

void motor(int en, int a, int b, int v) {   // v de -255 a 255
  digitalWrite(a, v > 0);
  digitalWrite(b, v < 0);
  analogWrite(en, constrain(abs(v), 0, 255));
}

void setup() {
  for (int i = 0; i < 5; i++) pinMode(S[i], INPUT);
  for (int p = 5; p <= 10; p++) pinMode(p, OUTPUT);
}

void loop() {
  int soma = 0, n = 0;
  for (int i = 0; i < 5; i++)
    if (digitalRead(S[i]) == HIGH) { soma += PESO[i]; n++; }   // HIGH = linha
  float erro = (n > 0) ? (float)soma / n : erroAnt;   // sem linha: repete o último
  float corr = kp * erro + kd * (erro - erroAnt);
  erroAnt = erro;
  motor(5, 7, 8, base + corr);    // esquerdo: ENA=5, IN1=7, IN2=8
  motor(6, 9, 10, base - corr);   // direito: ENB=6, IN3=9, IN4=10
}'''
C['R6A4'] = '''const int N = 5;    // tamanho da janela: teste 3, 5, 10, 20
int amostras[N];    // começam em 0: a média sobe nas primeiras leituras
int idx = 0;
long soma = 0;

void setup() { Serial.begin(9600); }

void loop() {
  int bruto = analogRead(A0);
  soma -= amostras[idx];    // tira a leitura mais antiga
  amostras[idx] = bruto;    // guarda a nova
  soma += bruto;
  idx = (idx + 1) % N;      // volta ao início ao chegar no fim
  Serial.print(bruto);
  Serial.print(' ');
  Serial.println(soma / N); // duas linhas no Plotter: bruto e filtrado
  delay(20);
}'''
C['R6A5'] = '''volatile long pE = 0, pD = 0;   // contadores das rodas
const long ALVO = 98;           // pulsos para 1 m

void isrE() { pE++; }
void isrD() { pD++; }

void motor(int en, int a, int b, int v) {
  digitalWrite(a, v > 0);
  digitalWrite(b, v < 0);
  analogWrite(en, constrain(abs(v), 0, 255));
}

void setup() {
  for (int p = 5; p <= 10; p++) pinMode(p, OUTPUT);
  attachInterrupt(digitalPinToInterrupt(2), isrE, RISING);
  attachInterrupt(digitalPinToInterrupt(3), isrD, RISING);
  delay(2000);   // tempo para soltar o robô
}

void loop() {
  if (pE < ALVO) {
    int c = 3 * (pE - pD);   // se a esquerda está na frente, freia ela
    motor(5, 7, 8, 110 - c);
    motor(6, 9, 10, 110 + c);
  } else {
    motor(5, 7, 8, 0);
    motor(6, 9, 10, 0);
  }
}'''
C['R6A6'] = '''#include <Wire.h>

float biasZ = 0, ang = 0;
unsigned long t0;

int16_t lerGz() {   // gyro eixo Z, registrador 0x47
  Wire.beginTransmission(0x68);
  Wire.write(0x47);
  Wire.endTransmission(false);
  Wire.requestFrom(0x68, 2);
  int16_t hi = Wire.read();
  int16_t lo = Wire.read();
  return (hi << 8) | lo;
}

void setup() {
  Serial.begin(9600);
  Wire.begin();
  Wire.beginTransmission(0x68);
  Wire.write(0x6B);
  Wire.write(0);   // acorda
  Wire.endTransmission();
  delay(100);
  for (int i = 0; i < 500; i++) { biasZ += lerGz(); delay(2); }
  biasZ /= 500;
  t0 = micros();   // calibra com o robô parado
}

void loop() {
  unsigned long t = micros();
  float dt = (t - t0) / 1000000.0;
  t0 = t;
  ang += (lerGz() - biasZ) / 131.0 * dt;
  Serial.println(ang);
  delay(5);
}'''
C['R6A7'] = '''enum Estado { PROCURAR, ATACAR, RECUAR };
Estado estado = PROCURAR;
unsigned long tEstado = 0;
const int TRIG = 12, ECHO = 13, BORDA = A0;

long distCm() {
  digitalWrite(TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG, LOW);
  long d = pulseIn(ECHO, HIGH, 12000);   // máximo ~2 m
  return d == 0 ? 999 : d * 0.034 / 2;
}

void muda(Estado e) { estado = e; tEstado = millis(); }

void loop() {
  bool borda = analogRead(BORDA) > 300;   // ajuste: depende do módulo
  long d = distCm();
  if (borda) { if (estado != RECUAR) muda(RECUAR); }
  else if (estado == RECUAR && millis() - tEstado > 400) muda(PROCURAR);
  else if (estado != RECUAR) muda(d < 30 ? ATACAR : PROCURAR);
  // andar(esq, dir) é a sua função de motores da aula 3
  if (estado == RECUAR) andar(-150, -100);
  if (estado == ATACAR) andar(255, 255);
  if (estado == PROCURAR) andar(100, -100);   // gira no lugar
}'''
C['R6A8'] = '''import cv2

cam = cv2.VideoCapture(0)   # ou "http://192.168.0.50:81/stream"
while True:
    ok, quadro = cam.read()
    if not ok:
        break
    hsv = cv2.cvtColor(quadro, cv2.COLOR_BGR2HSV)
    m1 = cv2.inRange(hsv, (0, 120, 70), (10, 255, 255))     # vermelho baixo
    m2 = cv2.inRange(hsv, (170, 120, 70), (180, 255, 255))  # vermelho alto
    mascara = cv2.bitwise_or(m1, m2)
    cont, _ = cv2.findContours(mascara, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    if cont:
        c = max(cont, key=cv2.contourArea)   # maior mancha
        if cv2.contourArea(c) > 500:         # ignora manchas pequenas
            x, y, w, h = cv2.boundingRect(c)
            cv2.rectangle(quadro, (x, y), (x+w, y+h), (0, 255, 0), 2)
            print("centro x:", x + w//2)
    cv2.imshow("quadro", quadro)
    if cv2.waitKey(1) == 27:
        break   # ESC sai
cam.release()
cv2.destroyAllWindows()'''
C['R6AP'] = '''// Esqueleto do programa final: adapte os estados à sua proposta
enum Estado { PARADO, EXECUTAR, SEGURANCA };
Estado estado = PARADO;
unsigned long tEstado = 0;

void muda(Estado e) { estado = e; tEstado = millis(); }

int lerSensorFiltrado() {   // média simples de 5 leituras
  long s = 0;
  for (int i = 0; i < 5; i++) s += analogRead(A0);
  return s / 5;
}

void setup() {
  Serial.begin(9600);
  // pinMode dos sensores, motores e botão de início
}

void loop() {
  int leitura = lerSensorFiltrado();
  if (estado == PARADO && digitalRead(2) == LOW) muda(EXECUTAR);   // botão no pino 2
  if (estado == EXECUTAR) {
    // sua lógica principal: PID, decisão por sensores, etc.
    if (leitura > 900) muda(SEGURANCA);   // exemplo de condição crítica
  }
  if (estado == SEGURANCA) { /* motores parados */ }
}'''
