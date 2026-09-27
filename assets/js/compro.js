document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.compro-nav');
  const closeMenu = () => { toggle?.setAttribute('aria-expanded', 'false'); toggle?.setAttribute('aria-label', 'Buka menu'); nav?.classList.remove('open'); };
  toggle?.addEventListener('click', () => {
    const expanded = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(expanded));
    toggle.setAttribute('aria-label', expanded ? 'Tutup menu' : 'Buka menu');
    nav?.classList.toggle('open', expanded);
  });
  nav?.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  const dialog = document.querySelector('.gallery-dialog');
  document.querySelectorAll('[data-gallery]').forEach(button => {
    button.addEventListener('click', () => {
      if (!dialog) return;
      dialog.querySelector('h2').textContent = button.dataset.gallery;
      const photos = dialog.querySelector('.gallery-images');
      photos.replaceChildren();
      [button.dataset.photo, button.dataset.second].filter(Boolean).forEach(src => {
        const img = document.createElement('img'); img.src = src; img.alt = 'Dokumentasi ' + button.dataset.gallery; photos.append(img);
      });
      dialog.showModal();
    });
  });
  dialog?.querySelector('.gallery-close')?.addEventListener('click', () => dialog.close());
  dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
});
