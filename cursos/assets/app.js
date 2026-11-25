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

  // QR Code do Pix, desenhado no próprio navegador (assets/qrcode.js).
  document.querySelectorAll('[data-qr]').forEach(function (el) {
    if (typeof qrcode !== 'function') return;
    var qr = qrcode(0, 'M');
    qr.addData(el.getAttribute('data-qr'));
    qr.make();
    el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
  });
})();
