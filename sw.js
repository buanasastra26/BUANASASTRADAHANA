// Network only: never store account pages, OTPs, documents or payments offline.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', event => {
  if (event.request.mode !== 'navigate' || event.request.method !== 'GET') return;
  event.respondWith(fetch(event.request).catch(() => new Response('<!doctype html><html lang="id"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tidak ada koneksi</title><body style="font-family:system-ui;padding:32px"><h1>Anda sedang offline</h1><p>Hubungkan internet untuk mengakses portal CV BUANA.</p><button onclick="location.reload()">Coba lagi</button></body></html>', {status:503,headers:{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'}})));
});
