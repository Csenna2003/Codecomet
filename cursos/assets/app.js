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

  // QR Code do Pix, desenhado no próprio navegador (assets/qrcode.js).
  document.querySelectorAll('[data-qr]').forEach(function (el) {
    if (typeof qrcode !== 'function') return;
    var qr = qrcode(0, 'M');
    qr.addData(el.getAttribute('data-qr'));
    qr.make();
    el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
  });
})();
