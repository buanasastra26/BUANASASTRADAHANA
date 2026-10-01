(() => {
  let pending;
  const standalone = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  window.addEventListener('beforeinstallprompt', event => { event.preventDefault(); pending = event; });
  window.addEventListener('appinstalled', () => { pending = null; document.getElementById('install-help')?.close(); });
  if ('serviceWorker' in navigator && window.isSecureContext) navigator.serviceWorker.register('/sw.js').catch(() => {});
  document.querySelectorAll('[data-install-app]').forEach(button => button.addEventListener('click', async event => {
    event.preventDefault();
    if (standalone()) { location.assign('/login'); return; }
    if (pending) {
      const prompt = pending; pending = null;
      try { await prompt.prompt(); await prompt.userChoice; return; } catch (_) { /* Show manual instructions. */ }
    }
    const dialog = document.getElementById('install-help');
    document.getElementById('install-status').textContent = 'Jika dialog instalasi belum tersedia, gunakan menu browser berikut. Jika sudah terpasang, buka ikon CV BUANA di perangkat Anda.';
    if (dialog && !dialog.open) dialog.showModal();
  }));
})();
