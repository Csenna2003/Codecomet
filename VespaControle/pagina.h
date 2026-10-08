// ============================================================================
//  pagina.h - Pagina web (HTML/CSS/JS) servida pela Vespa
//  Tudo embutido: funciona sem internet.
// ============================================================================
#pragma once

const char PAGINA_HTML[] PROGMEM = R"rawliteral(
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vespa Controle</title>
<style>
  :root{--bg:#0f172a;--card:#1e293b;--txt:#e2e8f0;--mut:#94a3b8;--acc:#f59e0b;--on:#22c55e;--off:#475569}
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--txt)}
  header{padding:16px;text-align:center;border-bottom:1px solid #334155}
  header h1{margin:0;font-size:1.4rem}
  header small{color:var(--mut)}
  #con{display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--off);margin-right:6px}
  #con.ok{background:var(--on)}
  main{max-width:900px;margin:auto;padding:16px;display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
  .card{background:var(--card);border-radius:12px;padding:16px}
  .card h2{margin:0 0 12px;font-size:1.05rem;color:var(--acc)}
  .big{font-size:2.4rem;font-weight:700}
  .unit{font-size:1rem;color:var(--mut)}
  .bar{height:10px;background:#334155;border-radius:6px;overflow:hidden;margin-top:10px}
  .bar div{height:100%;background:var(--acc);width:0;transition:width .2s}
  .servo{margin-bottom:14px}
  .servo label{display:flex;justify-content:space-between;font-size:.95rem}
  input[type=range]{width:100%;accent-color:var(--acc);height:28px}
  .leds{display:flex;gap:10px;flex-wrap:wrap}
  button{border:0;border-radius:8px;padding:12px 14px;font-size:1rem;cursor:pointer;color:#fff;background:var(--off);flex:1}
  button.on{background:var(--on)}
  .row{display:flex;gap:8px;margin-top:10px}
  .row button{background:#334155;font-size:.9rem;padding:10px}
  footer{text-align:center;color:var(--mut);font-size:.8rem;padding:12px}
  .mouse-wrap{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));align-items:start}
  #pad{position:relative;width:100%;max-width:320px;aspect-ratio:1;margin:auto;border-radius:16px;
       background:radial-gradient(circle,#334155 0,#1e293b 70%);border:2px solid #475569;touch-action:none;cursor:crosshair;user-select:none}
  #pad.ativo{border-color:var(--acc)}
  #pad::before,#pad::after{content:"";position:absolute;background:#475569}
  #pad::before{left:50%;top:8%;bottom:8%;width:1px}
  #pad::after{top:50%;left:8%;right:8%;height:1px}
  #pad span{position:absolute;font-size:.75rem;color:var(--mut)}
  #knob{position:absolute;width:44px;height:44px;margin:-22px 0 0 -22px;left:50%;top:50%;border-radius:50%;
        background:var(--acc);box-shadow:0 0 12px #f59e0b88;pointer-events:none;z-index:1}
  .robo{position:relative;width:220px;height:240px;margin:auto}
  .corpo{position:absolute;left:60px;top:30px;width:100px;height:180px;border-radius:14px;background:#334155;border:2px solid #475569;
         display:flex;align-items:center;justify-content:center;color:var(--mut);font-size:.8rem;text-align:center}
  .frente{position:absolute;left:0;right:0;top:0;text-align:center;color:var(--acc);font-size:.8rem}
  .sv{position:absolute;width:56px;padding:6px 0;border-radius:10px;background:#0f172a;border:2px solid var(--acc);text-align:center;font-size:.75rem}
  .sv b{display:block;font-size:1.05rem}
  .opts{display:flex;flex-direction:column;gap:8px;font-size:.9rem;margin-top:12px}
  .opts label{display:flex;align-items:center;gap:8px}
  #mix{color:var(--mut);font-size:.85rem;text-align:center;margin-top:8px}
  details summary{cursor:pointer;color:var(--acc);margin:14px 0 10px}
</style>
</head>
<body>
<header>
  <h1>Vespa Controle</h1>
  <small><span id="con"></span><span id="st">conectando...</span></small>
</header>

<main>
  <section class="card">
    <h2>Temperatura</h2>
    <div><span class="big" id="temp">--</span> <span class="unit">&deg;C</span></div>
  </section>

  <section class="card">
    <h2>Distancia (ultrassonico)</h2>
    <div><span class="big" id="dist">--</span> <span class="unit">cm</span></div>
    <div class="bar"><div id="distBar"></div></div>
  </section>

  <section class="card">
    <h2>LEDs</h2>
    <div class="leds" id="leds"></div>
    <div class="row">
      <button onclick="ledTodos(1)">Ligar todos</button>
      <button onclick="ledTodos(0)">Desligar todos</button>
    </div>
  </section>

  <section class="card" style="grid-column:1/-1">
    <h2>Servomotores &mdash; controle por mouse</h2>
    <div class="mouse-wrap">
      <div>
        <div id="pad">
          <span style="top:6px;left:50%;transform:translateX(-50%)">frente</span>
          <span style="bottom:6px;left:50%;transform:translateX(-50%)">tr&aacute;s</span>
          <span style="left:8px;top:50%;transform:translateY(-50%)">esq.</span>
          <span style="right:8px;top:50%;transform:translateY(-50%)">dir.</span>
          <div id="knob"></div>
        </div>
        <div id="mix">Arraste a bolinha com o mouse ou o dedo (no PC tamb&eacute;m funciona com as setas/WASD)</div>
      </div>
      <div>
        <div class="robo">
          <div class="frente">&#9650; FRENTE</div>
          <div class="corpo">ROB&Ocirc;<br>(vista de cima)</div>
          <div class="sv" style="left:0;top:40px">S1<b id="r0">90&deg;</b>esq. frente</div>
          <div class="sv" style="left:0;top:150px">S2<b id="r1">90&deg;</b>esq. tr&aacute;s</div>
          <div class="sv" style="right:0;top:40px">S3<b id="r2">90&deg;</b>dir. frente</div>
          <div class="sv" style="right:0;top:150px">S4<b id="r3">90&deg;</b>dir. tr&aacute;s</div>
        </div>
        <div class="opts">
          <label><input type="checkbox" id="optInv" checked> Inverter lado direito (servos espelhados)</label>
          <label><input type="checkbox" id="optCentro" checked> Voltar ao centro ao soltar</label>
          <label>Alcance: <input type="range" id="optAlc" min="10" max="90" value="90" style="flex:1"> <b id="alcV">90</b>&deg;</label>
        </div>
      </div>
    </div>
    <details>
      <summary>Ajuste individual de cada servo</summary>
      <div id="servos"></div>
    </details>
    <div class="row"><button onclick="parar()">Centralizar todos (90&deg;)</button></div>
  </section>
</main>

<footer>Bateria: <span id="bat">--</span> V &middot; Dispositivos conectados: <span id="cli">--</span> &middot; &Uacute;ltimo rein&iacute;cio: <span id="rst">--</span></footer>

<script>
const N_SERVOS = 4, N_LEDS = 3;
const NOMES = ['esq. frente', 'esq. tr\u00e1s', 'dir. frente', 'dir. tr\u00e1s'];
let arrastando = new Array(N_SERVOS).fill(false);
let ultimoEnvio = new Array(N_SERVOS).fill(0);
let timers = new Array(N_SERVOS).fill(null);

// Monta a interface
const servosDiv = document.getElementById('servos');
for (let i = 0; i < N_SERVOS; i++) {
  servosDiv.insertAdjacentHTML('beforeend',
    `<div class="servo"><label>Servo S${i+1} (${NOMES[i]})<b><span id="sv${i}">90</span>&deg;</b></label>
     <input type="range" min="0" max="180" value="90" id="s${i}"></div>`);
  const r = document.getElementById('s' + i);
  r.addEventListener('pointerdown', () => arrastando[i] = true);
  r.addEventListener('pointerup',   () => { arrastando[i] = false; enviarServo(i, true); });
  r.addEventListener('input', () => { document.getElementById('sv'+i).textContent = r.value; enviarServo(i, false); });
}
const ledsDiv = document.getElementById('leds');
for (let i = 0; i < N_LEDS; i++) {
  ledsDiv.insertAdjacentHTML('beforeend', `<button id="l${i}" onclick="toggleLed(${i})">LED ${i+1}</button>`);
}

// ---------------- Mouse (joystick) dos servos ----------------
// Lado esquerdo: S1 (frente) e S2 (tras). Lado direito: S3 (frente) e S4 (tras).
// Eixo Y (cima/baixo) = frente/tras; eixo X (esq./dir.) = girar.
// Os dois servos de um mesmo lado recebem o mesmo angulo.
const pad = document.getElementById('pad'), knob = document.getElementById('knob');
const optInv = document.getElementById('optInv'), optCentro = document.getElementById('optCentro');
const optAlc = document.getElementById('optAlc');
let mouseX = 0, mouseY = 0, mouseAtivo = false, mouseTimer = null, mouseUltimo = 0;

function limitar(v) { return Math.max(-1, Math.min(1, v)); }

function anguloLados() {
  const alc = +optAlc.value;
  const esq = limitar(mouseY + mouseX), dir = limitar(mouseY - mouseX);
  return {
    esq: Math.round(90 + esq * alc),
    dir: Math.round(90 + (optInv.checked ? -dir : dir) * alc)
  };
}

function posicionar(x, y) {
  mouseX = limitar(x); mouseY = limitar(y);
  knob.style.left = (50 + mouseX * 50) + '%';
  knob.style.top  = (50 - mouseY * 50) + '%';
  const a = anguloLados();
  document.getElementById('mix').textContent = `Lado esquerdo: ${a.esq}\u00b0  \u00b7  Lado direito: ${a.dir}\u00b0`;
  enviarMouse();
}

// Limita a ~12 envios/s, garantindo que a ultima posicao sempre seja enviada
function enviarMouse() {
  clearTimeout(mouseTimer);
  const envia = () => {
    mouseUltimo = Date.now();
    const a = anguloLados();
    api(`/api/servos?s1=${a.esq}&s2=${a.esq}&s3=${a.dir}&s4=${a.dir}`);
  };
  if (Date.now() - mouseUltimo > 80) envia(); else mouseTimer = setTimeout(envia, 80);
}

function moverPeloPonteiro(e) {
  const r = pad.getBoundingClientRect();
  posicionar(((e.clientX - r.left) / r.width) * 2 - 1, 1 - ((e.clientY - r.top) / r.height) * 2);
}
pad.addEventListener('pointerdown', e => {
  pad.setPointerCapture(e.pointerId); mouseAtivo = true; pad.classList.add('ativo'); moverPeloPonteiro(e);
});
pad.addEventListener('pointermove', e => { if (mouseAtivo) moverPeloPonteiro(e); });
function soltar() {
  if (!mouseAtivo) return;
  mouseAtivo = false; pad.classList.remove('ativo');
  if (optCentro.checked) posicionar(0, 0);
}
pad.addEventListener('pointerup', soltar);
pad.addEventListener('pointercancel', soltar);

function parar() { posicionar(0, 0); }

optAlc.addEventListener('input', () => { document.getElementById('alcV').textContent = optAlc.value; posicionar(mouseX, mouseY); });
optInv.addEventListener('change', () => posicionar(mouseX, mouseY));

// Teclado (PC): setas ou WASD
const teclas = {};
const MAPA = {ArrowUp:'u', KeyW:'u', ArrowDown:'d', KeyS:'d', ArrowLeft:'l', KeyA:'l', ArrowRight:'r', KeyD:'r'};
function teclado(e, apertada) {
  const k = MAPA[e.code];
  if (!k || e.target.tagName === 'INPUT') return;
  e.preventDefault();
  if (!!teclas[k] === apertada) return;
  teclas[k] = apertada;
  const x = (teclas.r ? 1 : 0) - (teclas.l ? 1 : 0), y = (teclas.u ? 1 : 0) - (teclas.d ? 1 : 0);
  if (x || y || optCentro.checked) posicionar(x, y);
}
document.addEventListener('keydown', e => teclado(e, true));
document.addEventListener('keyup',   e => teclado(e, false));

// Envia o angulo do servo limitando a ~12 envios/s enquanto arrasta
function enviarServo(i, forcar) {
  const agora = Date.now();
  clearTimeout(timers[i]);
  const envia = () => { ultimoEnvio[i] = Date.now(); api(`/api/servo?id=${i}&angulo=${document.getElementById('s'+i).value}`); };
  if (forcar || agora - ultimoEnvio[i] > 80) envia();
  else timers[i] = setTimeout(envia, 80);
}

function toggleLed(i) {
  const ligado = document.getElementById('l' + i).classList.contains('on');
  api(`/api/led?id=${i}&estado=${ligado ? 0 : 1}`);
}
function ledTodos(e) { api(`/api/led?id=todos&estado=${e}`); }

// Uma requisicao por vez para o status (nao acumula fila na placa) e
// tempo limite de 2 s para nao travar quando a rede oscila.
let statusPendente = false;
async function api(url) {
  const ehStatus = url === '/api/status';
  if (ehStatus && statusPendente) return;
  if (ehStatus) statusPendente = true;
  const ctl = new AbortController();
  const t = setTimeout(() => ctl.abort(), 2000);
  try {
    const r = await fetch(url, {cache: 'no-store', signal: ctl.signal});
    atualizar(await r.json());
  } catch (e) { conexao(false); }
  finally { clearTimeout(t); if (ehStatus) statusPendente = false; }
}

function conexao(ok) {
  document.getElementById('con').className = ok ? 'ok' : '';
  document.getElementById('st').textContent = ok ? 'conectado' : 'reconectando...';
}

function atualizar(d) {
  if (!d || !d.servos) return;
  conexao(true);
  document.getElementById('temp').textContent = d.temperatura === null ? '--' : d.temperatura.toFixed(1);
  document.getElementById('dist').textContent = d.distancia === null ? 'fora de alcance' : d.distancia.toFixed(1);
  document.getElementById('distBar').style.width = d.distancia === null ? '0' : Math.min(100, d.distancia / 4) + '%';
  document.getElementById('bat').textContent = (d.bateria_mV / 1000).toFixed(2);
  document.getElementById('cli').textContent = d.clientes;
  document.getElementById('rst').textContent = d.reset || '--';
  d.leds.forEach((on, i) => document.getElementById('l' + i).classList.toggle('on', on));
  d.servos.forEach((a, i) => document.getElementById('r' + i).textContent = a + '\u00b0');
  d.servos.forEach((a, i) => {
    if (arrastando[i]) return; // nao sobrescreve enquanto o usuario arrasta
    document.getElementById('s' + i).value = a;
    document.getElementById('sv' + i).textContent = a;
  });
}

// Atualiza os sensores periodicamente
setInterval(() => api('/api/status'), 500);
api('/api/status');
</script>
</body>
</html>
)rawliteral";
