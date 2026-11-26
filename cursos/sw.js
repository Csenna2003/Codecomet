// Aplicativo (PWA) da plataforma: guarda só os arquivos visuais e a página "sem internet".
// As páginas com dados do aluno nunca ficam guardadas no aparelho.
const VERSAO = 'adoro-v1';
const ARQUIVOS = ['offline.html', 'assets/estilo.css', 'assets/app.js', 'assets/logo.png', 'assets/icone-app-192.png', 'assets/icone-512.png'];

self.addEventListener('install', (ev) => {
  ev.waitUntil(caches.open(VERSAO).then((c) => c.addAll(ARQUIVOS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (ev) => {
  ev.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== VERSAO).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (ev) => {
  const req = ev.request;
  if (req.method !== 'GET' || new URL(req.url).origin !== location.origin) return;
  if (req.mode === 'navigate') {
    // Páginas: sempre da internet; sem conexão, mostra o aviso.
    ev.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }
  if (new URL(req.url).pathname.includes('/assets/')) {
    // Arquivos visuais: da internet quando der (para pegar atualizações), do aparelho quando não.
    ev.respondWith(fetch(req).then((r) => {
      const copia = r.clone();
      if (r.ok) caches.open(VERSAO).then((c) => c.put(req, copia));
      return r;
    }).catch(() => caches.match(req, { ignoreSearch: true })));
  }
});
