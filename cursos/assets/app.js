// Comportamentos pequenos da plataforma. Tudo funciona sem JavaScript, exceto o QR Code do Pix.
(function () {
  // Pede confirmação antes de ações como remover ou cancelar.
  document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.getAttribute('data-confirmar'))) ev.preventDefault();
    });
  });

  // Evita envio duplo de formulários.
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (ev.defaultPrevented) return;
      var b = f.querySelector('button');
      if (f.dataset.enviando) { ev.preventDefault(); return; }
      f.dataset.enviando = '1';
      if (b) setTimeout(function () { b.disabled = true; }, 0);
    });
  });

  document.querySelectorAll('[data-copiar]').forEach(function (b) {
    b.addEventListener('click', function () {
      var campo = document.getElementById(b.getAttribute('data-copiar'));
      var texto = campo.value;
      var feito = function () {
        var antes = b.textContent;
        b.textContent = 'Código copiado ✓';
        setTimeout(function () { b.textContent = antes; }, 2500);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(texto).then(feito);
      } else {
        campo.select();
        document.execCommand('copy');
        feito();
      }
    });
  });

  document.querySelectorAll('[data-imprimir]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });

  // CEP: preenche rua, bairro, cidade e UF pelo ViaCEP (serviço público e gratuito).
  document.querySelectorAll('input[data-cep]').forEach(function (campo) {
    campo.addEventListener('blur', function () {
      var cep = campo.value.replace(/\D/g, '');
      if (cep.length !== 8 || !window.fetch) return;
      var grupo = campo.closest('fieldset') || document;
      fetch('https://viacep.com.br/ws/' + cep + '/json/').then(function (r) { return r.json(); }).then(function (d) {
        if (d.erro) return;
        campo.value = cep.slice(0, 5) + '-' + cep.slice(5);
        grupo.querySelectorAll('[data-end]').forEach(function (el) {
          var v = d[el.getAttribute('data-end')];
          if (v && !el.value) el.value = v;
        });
        var rua = grupo.querySelector('[data-end="logradouro"]');
        if (rua && d.logradouro && rua.value === d.logradouro) { rua.value = d.logradouro + ', '; rua.focus(); }
      }).catch(function () {});
    });
  });

  // Aplicativo: registra o service worker e mostra o botão "Instalar aplicativo".
  if ('serviceWorker' in navigator && window.isSecureContext) {
    navigator.serviceWorker.register('sw.js').catch(function () {});
  }
  var instalar = document.querySelector('[data-instalar]');
  var dicaIos = document.querySelector('[data-instalar-ios]');
  var jaInstalado = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
  var pedido = null;
  if (instalar && !jaInstalado) {
    window.addEventListener('beforeinstallprompt', function (ev) {
      ev.preventDefault(); pedido = ev;
      instalar.closest('[data-app]').hidden = false;
    });
    instalar.addEventListener('click', function () {
      if (!pedido) return;
      pedido.prompt();
      pedido.userChoice.then(function () { instalar.closest('[data-app]').hidden = true; pedido = null; });
    });
    // iPhone/iPad não têm o botão: mostra o passo a passo do Safari.
    if (/iphone|ipad|ipod/i.test(navigator.userAgent) && dicaIos) {
      dicaIos.closest('[data-app]').hidden = false; dicaIos.hidden = false; instalar.hidden = true;
    }
  }

  // Entregas com fotos: reduz no próprio aparelho antes de enviar (foto de celular tem vários MB).
  function reduzirFoto(f) {
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) return Promise.resolve(f);
    return new Promise(function (pronto) {
      var img = new Image(), u = URL.createObjectURL(f);
      img.onload = function () {
        var k = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
        if (k === 1 && f.size < 400000) { URL.revokeObjectURL(u); pronto(f); return; }
        var c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * k); c.height = Math.round(img.naturalHeight * k);
        var g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); g.drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (b) {
          URL.revokeObjectURL(u);
          if (!b || b.size >= f.size) { pronto(f); return; }
          pronto(new File([b], f.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
        }, 'image/jpeg', 0.82);
      };
      img.onerror = function () { URL.revokeObjectURL(u); pronto(f); };
      img.src = u;
    });
  }
  document.querySelectorAll('input[data-reduzir-fotos]').forEach(function (inp) {
    var status = inp.closest('label').querySelector('[data-fotos-status]');
    var botao = inp.form.querySelector('button:not([type=button])');
    var max = +inp.dataset.max, limite = +inp.dataset.limite;
    var mb = function (n) { return (n / 1048576).toFixed(1).replace('.', ',') + ' MB'; };
    function conferir() {
      var arqs = Array.prototype.slice.call(inp.files);
      var grande = arqs.filter(function (f) { return f.size > limite; })[0];
      if (grande) { status.textContent = '“' + grande.name + '” tem ' + mb(grande.size) + ' e passa do limite de ' + mb(limite) + '. Escolha outro arquivo.'; inp.value = ''; return; }
      status.textContent = arqs.length ? arqs.length + (arqs.length > 1 ? ' arquivos prontos: ' : ' arquivo pronto: ') + arqs.map(function (f) { return f.name; }).join(', ') : '';
    }
    inp.addEventListener('change', function () {
      var arqs = Array.prototype.slice.call(inp.files);
      if (arqs.length > max) { status.textContent = 'Escolha no máximo ' + max + ' arquivos.'; inp.value = ''; return; }
      if (!window.DataTransfer || !window.File || !HTMLCanvasElement.prototype.toBlob) { conferir(); return; }
      if (botao) botao.disabled = true;
      status.textContent = 'Preparando as fotos…';
      Promise.all(arqs.map(reduzirFoto)).then(function (novos) {
        try { var dt = new DataTransfer(); novos.forEach(function (f) { dt.items.add(f); }); inp.files = dt.files; } catch (e) {}
      }).then(conferir, conferir).then(function () { if (botao) botao.disabled = false; });
    });
  });

  // Simuladores da plataforma avisam a própria altura, para o quadro crescer junto (sem rolagem dupla no celular).
  window.addEventListener('message', function (ev) {
    if (ev.origin !== location.origin || !ev.data || typeof ev.data.alturaSimulador !== 'number') return;
    document.querySelectorAll('iframe[data-sim-interno]').forEach(function (f) {
      if (f.contentWindow === ev.source) f.style.height = Math.min(Math.max(ev.data.alturaSimulador, 300), 3000) + 'px';
    });
  });

  // QR Code do Pix, desenhado no próprio navegador (assets/qrcode.js).
  document.querySelectorAll('[data-qr]').forEach(function (el) {
    if (typeof qrcode !== 'function') return;
    var qr = qrcode(0, 'M');
    qr.addData(el.getAttribute('data-qr'));
    qr.make();
    el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
  });
})();
