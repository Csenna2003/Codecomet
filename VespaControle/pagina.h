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
    <h2>Servomotores</h2>
    <div id="servos"></div>
    <div class="row"><button onclick="api('/api/servos/centro')">Centralizar todos (90&deg;)</button></div>
  </section>
</main>

<footer>Bateria: <span id="bat">--</span> V &middot; Dispositivos conectados: <span id="cli">--</span></footer>

<script>
const N_SERVOS = 4, N_LEDS = 3;
let arrastando = new Array(N_SERVOS).fill(false);
let ultimoEnvio = new Array(N_SERVOS).fill(0);
let timers = new Array(N_SERVOS).fill(null);

// Monta a interface
const servosDiv = document.getElementById('servos');
for (let i = 0; i < N_SERVOS; i++) {
  servosDiv.insertAdjacentHTML('beforeend',
    `<div class="servo"><label>Servo S${i+1}<b><span id="sv${i}">90</span>&deg;</b></label>
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

async function api(url) {
  try {
    const r = await fetch(url, {cache: 'no-store'});
    atualizar(await r.json());
  } catch (e) { conexao(false); }
}

function conexao(ok) {
  document.getElementById('con').className = ok ? 'ok' : '';
  document.getElementById('st').textContent = ok ? 'conectado' : 'sem conexao com a placa';
}

function atualizar(d) {
  if (!d || !d.servos) return;
  conexao(true);
  document.getElementById('temp').textContent = d.temperatura === null ? '--' : d.temperatura.toFixed(1);
  document.getElementById('dist').textContent = d.distancia === null ? 'fora de alcance' : d.distancia.toFixed(1);
  document.getElementById('distBar').style.width = d.distancia === null ? '0' : Math.min(100, d.distancia / 4) + '%';
  document.getElementById('bat').textContent = (d.bateria_mV / 1000).toFixed(2);
  document.getElementById('cli').textContent = d.clientes;
  d.leds.forEach((on, i) => document.getElementById('l' + i).classList.toggle('on', on));
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
