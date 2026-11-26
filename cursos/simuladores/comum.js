/* Simuladores da plataforma Adoro Robótica: ajudantes comuns e um pequeno motor 3D em canvas.
   Sem bibliotecas externas, para funcionar em qualquer hospedagem e no celular. */
(function () {
  'use strict';
  var Sim = {};

  // ---------- Altura: o quadro da aula cresce junto com o simulador ----------
  function avisarAltura() {
    if (window.parent === window) return;
    window.parent.postMessage({ alturaSimulador: Math.ceil(document.documentElement.scrollHeight) + 4 }, location.origin);
  }
  window.addEventListener('load', avisarAltura);
  window.addEventListener('resize', avisarAltura);
  if (window.ResizeObserver) new ResizeObserver(avisarAltura).observe(document.documentElement);
  Sim.avisarAltura = avisarAltura;

  // ---------- Números ----------
  Sim.mm = function (v, casas) {
    casas = casas === undefined ? 1 : casas;
    return (Math.round(v * Math.pow(10, casas)) / Math.pow(10, casas)).toFixed(casas).replace('.', ',') + ' mm';
  };
  Sim.num = function (v, casas) { return (+v).toFixed(casas === undefined ? 1 : casas).replace('.', ','); };
  Sim.perto = function (a, b, tol) { return Math.abs(a - b) <= (tol === undefined ? 1e-6 : tol); };

  /* Liga um campo deslizante a um campo numérico. Devolve { valor(), definir(v) }.
     html: <div class="campo"><label for="x">Rótulo</label><input type="number" id="x"><input type="range"></div> */
  Sim.campo = function (id, aoMudar) {
    var num = document.getElementById(id);
    var bloco = num.closest('.campo');
    var faixa = bloco ? bloco.querySelector('input[type=range]') : null;
    function ler(el) { var v = parseFloat(String(el.value).replace(',', '.')); return isNaN(v) ? 0 : v; }
    if (faixa) {
      faixa.min = num.min; faixa.max = num.max; faixa.step = num.step || 1; faixa.value = num.value;
      faixa.setAttribute('aria-label', (bloco.querySelector('label') || {}).textContent || id);
      faixa.addEventListener('input', function () { num.value = faixa.value; aoMudar(); });
    }
    num.addEventListener('input', function () { if (faixa) faixa.value = ler(num); aoMudar(); });
    return {
      valor: function () { return ler(num); },
      definir: function (v) { num.value = v; if (faixa) faixa.value = v; }
    };
  };

  /* Lista de desafios que se corrigem sozinhos. itens: [{ texto, teste: function () -> bool }] */
  Sim.desafios = function (elId, itens, fim) {
    var ul = document.getElementById(elId);
    var feitos = itens.map(function () { return false; });
    ul.innerHTML = '';
    var lis = itens.map(function (it, i) {
      var li = document.createElement('li');
      li.innerHTML = '<span class="marca" aria-hidden="true"></span><span></span>';
      li.lastChild.innerHTML = '<strong>' + (i + 1) + '.</strong> ' + it.texto;
      ul.appendChild(li);
      return li;
    });
    var msg = document.createElement('p');
    msg.className = 'parabens'; msg.setAttribute('role', 'status'); msg.hidden = true;
    msg.textContent = fim || 'Parabéns! Você cumpriu todos os desafios deste simulador.';
    ul.after(msg);
    function verificar() {
      // Só o desafio atual é corrigido: assim o aluno faz um de cada vez, na ordem.
      var atual = feitos.indexOf(false);
      if (atual >= 0) {
        try { if (itens[atual].teste()) feitos[atual] = true; } catch (e) { /* estado incompleto */ }
      }
      atual = feitos.indexOf(false);
      lis.forEach(function (li, i) {
        li.classList.toggle('feito', feitos[i]);
        li.classList.toggle('atual', i === atual);
        li.firstChild.textContent = feitos[i] ? '✓' : '';
        li.setAttribute('aria-label', 'Desafio ' + (i + 1) + (feitos[i] ? ': concluído' : i === atual ? ': atual' : ''));
      });
      msg.hidden = atual !== -1;
      avisarAltura();
      return atual;
    }
    verificar();
    return { verificar: verificar, feitos: function () { return feitos.slice(); } };
  };

  // ---------- Canvas nítido em qualquer tela ----------
  Sim.canvas = function (canvas, aoRedimensionar) {
    var ctx = canvas.getContext('2d');
    function ajustar() {
      var r = canvas.getBoundingClientRect(), d = window.devicePixelRatio || 1;
      canvas.width = Math.max(1, Math.round(r.width * d)); canvas.height = Math.max(1, Math.round(r.height * d));
      ctx.setTransform(d, 0, 0, d, 0, 0);
      aoRedimensionar(r.width, r.height);
    }
    if (window.ResizeObserver) new ResizeObserver(ajustar).observe(canvas); else window.addEventListener('resize', ajustar);
    ajustar();
    return ctx;
  };

  // ---------- Motor 3D simples (Z para cima, como no Tinkercad) ----------
  function sub(a, b) { return [a[0] - b[0], a[1] - b[1], a[2] - b[2]]; }
  function cruz(a, b) { return [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]]; }
  function pontoEsc(a, b) { return a[0] * b[0] + a[1] * b[1] + a[2] * b[2]; }
  function norm(a) { var l = Math.hypot(a[0], a[1], a[2]) || 1; return [a[0] / l, a[1] / l, a[2] / l]; }
  function hexRgb(h) { h = h.replace('#', ''); return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)]; }

  /* Uma face é { p: [[x,y,z], ...] (anti-horário visto de fora), cor: '#rrggbb', alfa?, duasFaces? } */
  var Geo = {};
  Geo.caixa = function (x, y, z, w, d, h, cor, extra) {
    var X = x + w, Y = y + d, Z = z + h, f = function (p) { return Object.assign({ p: p, cor: cor, aresta: true }, extra || {}); };
    return [
      f([[x, y, z], [x, Y, z], [X, Y, z], [X, y, z]]),       // baixo
      f([[x, y, Z], [X, y, Z], [X, Y, Z], [x, Y, Z]]),       // cima
      f([[x, y, z], [X, y, z], [X, y, Z], [x, y, Z]]),       // frente (y mínimo)
      f([[X, Y, z], [x, Y, z], [x, Y, Z], [X, Y, Z]]),       // fundo
      f([[x, Y, z], [x, y, z], [x, y, Z], [x, Y, Z]]),       // esquerda
      f([[X, y, z], [X, Y, z], [X, Y, Z], [X, y, Z]])        // direita
    ];
  };
  /* Anel de revolução (tubo ou cilindro maciço se r1 = 0) em volta do eixo Z, de a0 a a1 graus.
     Com corte (a1 - a0 < 360) fecha as faces do corte para ver o interior. */
  Geo.anel = function (cx, cy, r1, r2, z1, z2, cor, a0, a1, lados, corCorte) {
    a0 = a0 === undefined ? 0 : a0; a1 = a1 === undefined ? 360 : a1; lados = lados || 48;
    var faces = [], n = Math.max(3, Math.round(lados * (a1 - a0) / 360)), P = function (r, ang, z) {
      var t = ang * Math.PI / 180; return [cx + r * Math.cos(t), cy + r * Math.sin(t), z];
    };
    if (r2 <= r1 || z2 <= z1) return faces;
    for (var i = 0; i < n; i++) {
      var t0 = a0 + (a1 - a0) * i / n, t1 = a0 + (a1 - a0) * (i + 1) / n;
      faces.push({ p: [P(r2, t0, z1), P(r2, t1, z1), P(r2, t1, z2), P(r2, t0, z2)], cor: cor });            // externa
      if (r1 > 0) faces.push({ p: [P(r1, t1, z1), P(r1, t0, z1), P(r1, t0, z2), P(r1, t1, z2)], cor: cor }); // interna
      faces.push({ p: r1 > 0 ? [P(r1, t0, z2), P(r2, t0, z2), P(r2, t1, z2), P(r1, t1, z2)] : [[cx, cy, z2], P(r2, t0, z2), P(r2, t1, z2)], cor: cor }); // topo
      faces.push({ p: r1 > 0 ? [P(r1, t1, z1), P(r2, t1, z1), P(r2, t0, z1), P(r1, t0, z1)] : [[cx, cy, z1], P(r2, t1, z1), P(r2, t0, z1)], cor: cor }); // base
    }
    if (a1 - a0 < 360) {
      var cc = corCorte || cor;
      faces.push({ p: [P(r1, a0, z1), P(r1, a0, z2), P(r2, a0, z2), P(r2, a0, z1)], cor: cc, duasFaces: true });
      faces.push({ p: [P(r2, a1, z1), P(r2, a1, z2), P(r1, a1, z2), P(r1, a1, z1)], cor: cc, duasFaces: true });
    }
    return faces;
  };

  /* Cena com câmera orbital. opcoes: { alvo:[x,y,z], dist, giro, inclinacao, grade:{tam, passo}, eixos:true } */
  Sim.Cena3D = function (canvas, opcoes) {
    opcoes = opcoes || {};
    var cena = this, larg = 1, alt = 1;
    this.alvo = opcoes.alvo || [0, 0, 0];
    this.dist = opcoes.dist || 200;
    this.giro = opcoes.giro === undefined ? -35 : opcoes.giro;           // graus em volta de Z
    this.inclinacao = opcoes.inclinacao === undefined ? 30 : opcoes.inclinacao; // graus acima do plano
    this.faces = []; this.rotulos = []; this.linhas = [];
    this.grade = opcoes.grade === undefined ? { tam: 100, passo: 10 } : opcoes.grade;
    this.eixos = opcoes.eixos !== false;
    var ctx = Sim.canvas(canvas, function (w, h) { larg = w; alt = h; if (ctx && cena.desenhar) cena.desenhar(); });

    function base() {
      var g = cena.giro * Math.PI / 180, i = cena.inclinacao * Math.PI / 180;
      var olho = [cena.alvo[0] + cena.dist * Math.cos(i) * Math.sin(g), cena.alvo[1] - cena.dist * Math.cos(i) * Math.cos(g), cena.alvo[2] + cena.dist * Math.sin(i)];
      var frente = norm(sub(cena.alvo, olho)), dir = norm(cruz(frente, [0, 0, 1])), cima = cruz(dir, frente);
      return { olho: olho, frente: frente, dir: dir, cima: cima };
    }
    this.projetar = function (p, b) {
      b = b || base();
      var v = sub(p, b.olho), z = pontoEsc(v, b.frente);
      if (z < 1) z = 1;
      var f = Math.min(larg, alt) * 1.6;
      return [larg / 2 + pontoEsc(v, b.dir) * f / z, alt / 2 - pontoEsc(v, b.cima) * f / z, z];
    };
    this.desenhar = function () {
      var b = base(), luz = norm([0.4, -0.6, 0.9]);
      ctx.clearRect(0, 0, larg, alt);
      ctx.fillStyle = '#fbfdff'; ctx.fillRect(0, 0, larg, alt);
      // grade do plano de trabalho
      if (cena.grade) {
        var t = cena.grade.tam, s = cena.grade.passo, x0 = cena.grade.x0 || 0, y0 = cena.grade.y0 || 0;
        ctx.lineWidth = 1;
        for (var k = 0; k <= t; k += s) {
          ctx.strokeStyle = k % (s * 5) === 0 ? '#b9cfe4' : '#dde8f3';
          linha([x0 + k, y0, 0], [x0 + k, y0 + t, 0]); linha([x0, y0 + k, 0], [x0 + t, y0 + k, 0]);
        }
      }
      function linha(a, c) { var p = cena.projetar(a, b), q = cena.projetar(c, b); ctx.beginPath(); ctx.moveTo(p[0], p[1]); ctx.lineTo(q[0], q[1]); ctx.stroke(); }
      // faces: pintor (de trás para a frente)
      var lista = [];
      cena.faces.forEach(function (f) {
        var n = norm(cruz(sub(f.p[1], f.p[0]), sub(f.p[f.p.length - 1], f.p[0])));
        var centro = f.p.reduce(function (a, p) { return [a[0] + p[0], a[1] + p[1], a[2] + p[2]]; }, [0, 0, 0]).map(function (v) { return v / f.p.length; });
        var paraOlho = sub(b.olho, centro);
        if (pontoEsc(n, paraOlho) < 0 && !f.duasFaces && !f.alfa) return; // face de costas
        var pts = f.p.map(function (p) { return cena.projetar(p, b); });
        lista.push({ f: f, n: n, pts: pts, prof: Math.hypot(paraOlho[0], paraOlho[1], paraOlho[2]) });
      });
      lista.sort(function (a, c) { return c.prof - a.prof; });
      lista.forEach(function (it) {
        var c = hexRgb(it.f.cor), l = 0.55 + 0.45 * Math.abs(pontoEsc(it.n, luz));
        ctx.beginPath();
        it.pts.forEach(function (p, i) { if (i) ctx.lineTo(p[0], p[1]); else ctx.moveTo(p[0], p[1]); });
        ctx.closePath();
        ctx.fillStyle = 'rgba(' + Math.round(c[0] * l) + ',' + Math.round(c[1] * l) + ',' + Math.round(c[2] * l) + ',' + (it.f.alfa || 1) + ')';
        ctx.fill();
        // contorno da mesma cor esconde as emendas entre faces vizinhas; faces retas (caixas) ganham aresta leve
        ctx.strokeStyle = it.f.alfa ? 'rgba(0,60,110,.25)' : (it.f.aresta ? 'rgba(0,0,0,.18)' : ctx.fillStyle); ctx.lineWidth = it.f.aresta ? 0.8 : 0.7; ctx.stroke();
      });
      // eixos
      if (cena.eixos) {
        [[[1, 0, 0], '#d33', 'X'], [[0, 1, 0], '#2a2', 'Y'], [[0, 0, 1], '#26c', 'Z']].forEach(function (e) {
          var o = cena.projetar([0, 0, 0], b), p = cena.projetar(e[0].map(function (v) { return v * (cena.grade ? cena.grade.tam * 0.25 : 30); }), b);
          ctx.strokeStyle = e[1]; ctx.lineWidth = 2.5; ctx.beginPath(); ctx.moveTo(o[0], o[1]); ctx.lineTo(p[0], p[1]); ctx.stroke();
          ctx.fillStyle = e[1]; ctx.font = 'bold 13px system-ui'; ctx.fillText(e[2], p[0] + 4, p[1] - 4);
        });
      }
      // linhas de cota e rótulos
      cena.linhas.forEach(function (l) {
        ctx.strokeStyle = l.cor || '#333'; ctx.lineWidth = l.largura || 1.5; ctx.setLineDash(l.tracejada ? [5, 4] : []);
        linha(l.a, l.b); ctx.setLineDash([]);
      });
      ctx.font = '600 12px system-ui';
      cena.rotulos.forEach(function (r) {
        var p = cena.projetar(r.p, b), w = ctx.measureText(r.texto).width + 8;
        ctx.fillStyle = 'rgba(255,255,255,.9)'; ctx.fillRect(p[0] - w / 2, p[1] - 9, w, 18);
        ctx.fillStyle = r.cor || '#1b2333'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(r.texto, p[0], p[1]);
        ctx.textAlign = 'start'; ctx.textBaseline = 'alphabetic';
      });
    };
    this.vista = function (giro, inclinacao) { cena.giro = giro; cena.inclinacao = inclinacao; cena.desenhar(); };

    // girar com mouse/dedo; aproximar com a roda ou com dois dedos
    var toques = {}, ultimo = null, distToque = 0;
    canvas.addEventListener('pointerdown', function (e) { canvas.setPointerCapture(e.pointerId); toques[e.pointerId] = [e.clientX, e.clientY]; ultimo = [e.clientX, e.clientY]; });
    canvas.addEventListener('pointermove', function (e) {
      if (!toques[e.pointerId]) return;
      toques[e.pointerId] = [e.clientX, e.clientY];
      var ids = Object.keys(toques);
      if (ids.length === 2) {
        var a = toques[ids[0]], c = toques[ids[1]], d = Math.hypot(a[0] - c[0], a[1] - c[1]);
        if (distToque) cena.dist = Math.min(2000, Math.max(30, cena.dist * distToque / d));
        distToque = d; cena.desenhar(); return;
      }
      cena.giro += (e.clientX - ultimo[0]) * 0.5;
      cena.inclinacao = Math.max(-80, Math.min(89, cena.inclinacao + (e.clientY - ultimo[1]) * 0.4));
      ultimo = [e.clientX, e.clientY]; cena.desenhar();
    });
    function soltar(e) { delete toques[e.pointerId]; distToque = 0; }
    canvas.addEventListener('pointerup', soltar); canvas.addEventListener('pointercancel', soltar);
    canvas.addEventListener('wheel', function (e) { e.preventDefault(); cena.dist = Math.min(2000, Math.max(30, cena.dist * (e.deltaY > 0 ? 1.1 : 0.9))); cena.desenhar(); }, { passive: false });
    canvas.setAttribute('role', 'img');
  };

  /* Botões de vista: <div class="vistas" data-vistas></div> */
  Sim.botoesVista = function (el, cena) {
    [['Inicial', -35, 30], ['Frente', 0, 0], ['Cima', 0, 89], ['Lado', 90, 0]].forEach(function (v) {
      var b = document.createElement('button'); b.type = 'button'; b.textContent = v[0];
      b.addEventListener('click', function () { cena.vista(v[1], v[2]); });
      el.appendChild(b);
    });
  };

  Sim.Geo = Geo;
  window.Sim = Sim;
})();
