# Código de referência reconstruído à mão (o PDF perdeu quebras de linha e recuos). Chave: módulo e aula.
C = {}
C['M2A1'] = '''print("Olá, mundo!")
print("Meu nome é Ana")
print(2 + 3)'''
C['M2A2'] = '''preco_lanche = 7.5
quantidade = 3
preco_suco = 4
total = preco_lanche * quantidade + preco_suco
print("Total:", total)
print(type(preco_lanche))'''
C['M2A3'] = '''nome = input("Nome do personagem: ")
idade = int(input("Idade: "))
forca = int(input("Força de 1 a 10: "))
print("=== FICHA ===")
print(f"{nome} tem {idade} anos")
print("Poder total:", forca * 10)'''
C['M2A4'] = '''pontos = 0
resposta = input("Qual a capital do Brasil? ")
if resposta.lower() == "brasília":
    print("Acertou!")
    pontos += 1
else:
    print("Errou! A resposta é Brasília.")
print(f"Você fez {pontos} ponto(s)")'''
C['M2A5'] = '''import random

segredo = random.randint(1, 20)
tentativas = 0
palpite = 0
while palpite != segredo:
    palpite = int(input("Seu palpite: "))
    tentativas += 1
    if palpite < segredo:
        print("Maior!")
    elif palpite > segredo:
        print("Menor!")
print(f"Acertou em {tentativas} tentativas!")'''
C['M2A6'] = '''n = int(input("Tabuada de qual número? "))
for i in range(1, 11):
    print(f"{n} x {i} = {n * i}")

soma = 0
for i in range(1, 101):
    soma += i
print("Soma de 1 a 100:", soma)'''
C['M2A7'] = '''import turtle

t = turtle.Turtle()
t.speed(0)
t.color("gold")
for _ in range(5):
    t.forward(150)
    t.right(144)
turtle.done()'''
C['M2A8'] = '''import turtle

t = turtle.Turtle()
t.speed(0)

def estrela(tamanho, cor):
    t.color(cor)
    for _ in range(5):
        t.forward(tamanho)
        t.right(144)

estrela(100, "red")
t.penup()
t.goto(-150, 100)
t.pendown()
estrela(60, "blue")
turtle.done()'''
C['M2A9'] = '''import random

contagem = [0, 0, 0, 0, 0, 0]
for _ in range(100):
    face = random.randint(1, 6)
    contagem[face - 1] += 1
for i in range(6):
    print(f"Face {i + 1}: {contagem[i]} vezes")'''
C['M2A11'] = '''vida = 3
inventario = []

def floresta():
    escolha = input("Esquerda ou direita? ")
    if escolha == "esquerda":
        inventario.append("lanterna")
        caverna()
    else:
        print("Você caiu no rio. Fim.")

def caverna():
    print("Você chegou à caverna com", inventario)

floresta()'''
C['M3A1'] = '''def celsius_para_fahrenheit(c):
    return c * 9 / 5 + 32

def media(notas):
    return sum(notas) / len(notas)

print(celsius_para_fahrenheit(25))
print(media([7, 8.5, 6]))'''
C['M3A2'] = '''valores = [152, 163, 148, 171, 159, 160, 155, 168]
soma = 0
maior = valores[0]
menor = valores[0]
for v in valores:
    soma += v
    if v > maior:
        maior = v
    if v < menor:
        menor = v
print("Soma:", soma, "Maior:", maior, "Menor:", menor)'''
C['M3A3'] = '''itens = [
    {"nome": "caderno", "preco": 12.5, "quantidade": 3},
    {"nome": "lápis", "preco": 2.0, "quantidade": 10},
]
total = 0
for item in itens:
    print(item["nome"], item["preco"])
    total += item["preco"] * item["quantidade"]
print("Total:", total)'''
C['M3A4'] = '''def cifrar(texto, deslocamento):
    resultado = ""
    for letra in texto:
        if letra.isalpha():
            base = ord("a") if letra.islower() else ord("A")
            resultado += chr((ord(letra) - base + deslocamento) % 26 + base)
        else:
            resultado += letra
    return resultado

print(cifrar("ola mundo", 3))'''
C['M3A5'] = '''texto = input("Anotação: ")
with open("anotacoes.txt", "a", encoding="utf-8") as arq:
    arq.write(texto + "\\n")

with open("anotacoes.txt", "r", encoding="utf-8") as arq:
    for linha in arq:
        print(linha.strip())'''
C['M3A6'] = '''import csv

nomes = []
notas = []
with open("notas.csv", encoding="utf-8") as f:
    leitor = csv.reader(f)
    next(leitor)
    for linha in leitor:
        nomes.append(linha[0])
        notas.append(float(linha[1]))
print("Média:", sum(notas) / len(notas))
print("Maior nota:", nomes[notas.index(max(notas))])'''
C['M3A7'] = '''import csv
from datetime import date

valor = input("Quantas horas dormiu? ")
with open("coleta.csv", "a", newline="", encoding="utf-8") as f:
    escritor = csv.writer(f)
    escritor.writerow([date.today(), valor])'''
C['M3A8'] = '''import csv, statistics

valores = []
with open("coleta.csv", encoding="utf-8") as f:
    for linha in csv.reader(f):
        valores.append(float(linha[1]))
print("Média:", statistics.mean(valores))
print("Mediana:", statistics.median(valores))
print("Moda:", statistics.mode(valores))
print("Amplitude:", max(valores) - min(valores))'''
C['M3A9'] = '''import matplotlib.pyplot as plt

dias = ["Seg", "Ter", "Qua", "Qui", "Sex"]
valores = [7, 6.5, 8, 5, 7.5]
plt.bar(dias, valores, color="#0070B8")
plt.title("Horas de sono por dia")
plt.xlabel("Dia da semana")
plt.ylabel("Horas")
plt.show()'''
C['M3A10'] = '''import csv

limpos = []
with open("coleta_suja.csv", encoding="utf-8") as f:
    for linha in csv.reader(f):
        try:
            v = float(linha[1])
        except (ValueError, IndexError):
            continue
        if 0 <= v <= 24:
            limpos.append(v)

def media(lista):
    return sum(lista) / len(lista)

assert media([2, 4, 6]) == 4
print("Média limpa:", media(limpos))'''
C['M4A2'] = '''<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Minha página</title>
</head>
<body>
  <h1>Olá, eu sou a Ana</h1>
  <p>Gosto de robótica e jogos.</p>
  <ul>
    <li>Minecraft</li>
    <li>Xadrez</li>
  </ul>
  <a href="https://www.adororobotica.com.br">Meu curso</a>
  <img src="foto.jpg" alt="Foto da Ana sorrindo">
</body>
</html>'''
C['M4A3'] = '''<header><h1>Minha página</h1></header>
<main>
  <section>
    <h2>Fale comigo</h2>
    <form>
      <label for="nome">Nome</label>
      <input id="nome" type="text">
      <label for="email">E-mail</label>
      <input id="email" type="email">
      <button type="submit">Enviar</button>
    </form>
  </section>
</main>
<footer>Feito por Ana</footer>'''
C['M4A4'] = '''body {
  font-family: Arial, sans-serif;
  color: #14283A;
  background-color: #F2F4F6;
  margin: 0;
}
h1 {
  color: #0070B8;
}
.destaque {
  background-color: #E6F2FA;
  padding: 16px;
  border-radius: 8px;
}'''
C['M4A5'] = '''.cartoes {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}
.cartao {
  flex: 1 1 200px;
  background: #FFFFFF;
  padding: 16px;
  border-radius: 8px;
}
nav ul {
  display: flex;
  gap: 16px;
  list-style: none;
}'''
C['M4A6'] = '''<meta name="viewport" content="width=device-width, initial-scale=1">

img { max-width: 100%; height: auto; }

@media (max-width: 600px) {
  nav ul { flex-direction: column; }
  h1 { font-size: 1.6rem; }
}'''
C['M4A7'] = '''const nome = "Ana";
let idade = 12;
idade = idade + 1;
console.log("Olá, " + nome);
console.log(`Ana tem ${idade} anos`);
const total = 7.5 * 3;
console.log(total);'''
C['M4A8'] = '''function jogar() {
  const segredo = Math.floor(Math.random() * 20) + 1;
  let tentativas = 0;
  let palpite = 0;
  while (palpite !== segredo) {
    palpite = Number(prompt("Seu palpite (1 a 20):"));
    tentativas++;
    if (palpite < segredo) console.log("Maior!");
    else if (palpite > segredo) console.log("Menor!");
  }
  console.log(`Acertou em ${tentativas} tentativas!`);
}
jogar();'''
C['M4A9'] = '''const botao = document.querySelector("#botao");
const mensagem = document.querySelector("#mensagem");
let cliques = 0;
botao.addEventListener("click", () => {
  cliques++;
  mensagem.textContent = `Você clicou ${cliques} vezes`;
  mensagem.style.color = "#0070B8";
});'''
C['M4A10'] = '''const tarefas = [];
const lista = document.querySelector("#lista");
const campo = document.querySelector("#campo");

function mostrar() {
  lista.innerHTML = "";
  tarefas.forEach((t) => {
    const li = document.createElement("li");
    li.textContent = t;
    lista.appendChild(li);
  });
}

document.querySelector("#adicionar").addEventListener("click", () => {
  if (campo.value.trim() !== "") {
    tarefas.push(campo.value.trim());
    campo.value = "";
    mostrar();
  }
});'''
C['M5A1'] = '''from machine import Pin
from time import sleep

led = Pin(2, Pin.OUT)

while True:
    led.value(1)
    sleep(1)
    led.value(0)
    sleep(1)'''
C['M5A2'] = '''from machine import Pin, PWM
from time import sleep

leds = [Pin(12, Pin.OUT), Pin(13, Pin.OUT), Pin(14, Pin.OUT)]
buzzer = PWM(Pin(25))

while True:
    for led in leds:
        led.value(1)
        sleep(1)
        led.value(0)
    buzzer.freq(440)
    buzzer.duty(512)
    sleep(0.3)
    buzzer.duty(0)'''
C['M5A3'] = '''from machine import Pin, ADC, PWM
from time import sleep

btn = Pin(15, Pin.IN, Pin.PULL_UP)
pot = ADC(Pin(34))
led = PWM(Pin(12))

while True:
    valor = pot.read()
    if btn.value() == 0:
        led.duty(valor // 4)
    else:
        led.duty(0)
    sleep(0.05)'''
C['M5A4'] = '''from machine import Pin, ADC
import dht
from time import sleep

ldr = ADC(Pin(35))
sensor = dht.DHT22(Pin(4))

while True:
    luz = ldr.read() / 4095 * 100
    sensor.measure()
    temp = sensor.temperature()
    umid = sensor.humidity()
    print(f"Luz: {luz:.0f}% Temp: {temp} C Umid: {umid}%")
    sleep(2)'''
C['M5A5'] = '''from machine import Pin
import dht
from time import sleep

sensor = dht.DHT22(Pin(4))
ventilador = Pin(12, Pin.OUT)
ligado = False

while True:
    sensor.measure()
    t = sensor.temperature()
    if t >= 28:
        ligado = True
    elif t <= 26:
        ligado = False
    ventilador.value(1 if ligado else 0)
    print(t, "ligado" if ligado else "desligado")
    sleep(2)'''
C['M5A6'] = '''from machine import Pin
from time import ticks_ms, ticks_diff

led = Pin(12, Pin.OUT)
btn = Pin(15, Pin.IN, Pin.PULL_UP)
ultimo = ticks_ms()

while True:
    agora = ticks_ms()
    if ticks_diff(agora, ultimo) >= 500:
        led.value(not led.value())
        ultimo = agora
    if btn.value() == 0:
        print("Botão apertado!")'''
C['M5A8'] = '''from machine import Pin
import dht
from time import sleep, ticks_ms

sensor = dht.DHT22(Pin(4))
inicio = ticks_ms()
for i in range(30):
    sensor.measure()
    t = sensor.temperature()
    segundos = (ticks_ms() - inicio) // 1000
    print(f"{segundos},{t}")
    sleep(2)'''
C['M5A9'] = '''import network, urequests
from time import sleep

wlan = network.WLAN(network.STA_IF)
wlan.active(True)
wlan.connect("Wokwi-GUEST", "")
while not wlan.isconnected():
    sleep(0.5)
print("Conectado:", wlan.ifconfig())

r = urequests.get("https://[ENDEREÇO-DO-SERVIÇO-DE-TESTE]/dado?temp=25")
print(r.status_code)
r.close()'''
C['M6A1'] = '''class Robo:
    def __init__(self, nome, energia):
        self.nome = nome
        self.energia = energia

    def andar(self, passos):
        self.energia -= passos
        print(f"{self.nome} andou {passos} passos")

    def status(self):
        print(f"{self.nome}: energia {self.energia}")

r1 = Robo("Zeca", 100)
r2 = Robo("Lia", 80)
r1.andar(10)
r1.status()
r2.status()'''
C['M6A2'] = '''class Lutador(Robo):
    def __init__(self, nome, energia, forca):
        super().__init__(nome, energia)
        self.forca = forca

    def atacar(self, outro):
        outro.energia -= self.forca
        print(f"{self.nome} atacou {outro.nome}")

class Explorador(Robo):
    def andar(self, passos):
        self.energia -= passos // 2
        print(f"{self.nome} explorou {passos} passos")'''
C['M6A6'] = '''frutas = [
    {"peso": 150, "diam": 7, "nome": "maçã"},
    {"peso": 180, "diam": 8, "nome": "laranja"},
    # ... mais exemplos
]

def distancia(a, b):
    return ((a["peso"] - b["peso"]) ** 2 + (a["diam"] - b["diam"]) ** 2) ** 0.5

def classificar(peso, diam):
    novo = {"peso": peso, "diam": diam}
    mais_proxima = min(frutas, key=lambda f: distancia(f, novo))
    return mais_proxima["nome"]'''
