<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? 'CV BUANA SASTRA DAHANA') ?></title>
<meta name="description" content="CV BUANA SASTRA DAHANA, Purwakarta. Layanan konstruksi, renovasi, aluminium, dan supply material. Hubungi kami untuk membahas kebutuhan proyek Anda.">
<meta name="theme-color" content="#172d29">
<link rel="stylesheet" href="<?= asset('css/compro.css') ?>?v=20260925-1">
<?php if (($page ?? '') === 'home'): ?><link rel="preload" as="image" href="<?= asset('images/company/hero-owner.png') ?>"><?php endif; ?>
<link rel="manifest" href="/manifest.webmanifest"><link rel="apple-touch-icon" href="/assets/images/branding/app-192.png"><meta name="apple-mobile-web-app-capable" content="yes">
</head>
<body>
<a class="skip-link" href="#main">Lewati ke konten</a>
<div class="contact-bar"><div class="wrap"><span>Purwakarta, Jawa Barat</span><a href="mailto:Buanasastradahana@gmail.com">Buanasastradahana@gmail.com</a><a href="tel:+6281214296499">0812-1429-6499</a></div></div>
<header class="compro-header">
  <div class="wrap header-inner">
    <a class="identity" href="<?= url('') ?>" aria-label="CV BUANA SASTRA DAHANA — Beranda"><span class="logo-mark"><img src="<?= asset('images/branding/LOGO_CV_BUANA_SASTRA_DAHANA.png') ?>" alt="Logo CV BUANA SASTRA DAHANA"></span><span class="brand-name">CV BUANA<span>SASTRA DAHANA</span><small>KONSTRUKSI & SUPPLY MATERIAL</small></span></a>
    <button class="menu-toggle" aria-controls="compro-nav" aria-expanded="false" aria-label="Buka menu"><span></span><span></span></button>
    <nav id="compro-nav" class="compro-nav" aria-label="Navigasi utama">
      <a href="<?= url('') ?>#tentang">Tentang Kami</a><a href="<?= url('') ?>#layanan">Layanan</a><a href="<?= url('') ?>#project">Proyek</a><a href="<?= url('legalitas') ?>" <?= ($page ?? '') === 'legalitas' ? 'aria-current="page"' : '' ?>>Legalitas</a><a href="<?= url('') ?>#kontak">Kontak</a><a class="nav-login" href="<?= url('login') ?>">Login Client <span aria-hidden="true">↗</span></a>
    </nav>
  </div>
</header>
<main id="main"><?= $content ?></main>
<footer class="compro-footer"><div class="wrap footer-main">
  <div class="footer-brand"><a class="identity" href="<?= url('') ?>"><span class="logo-mark"><img src="<?= asset('images/branding/LOGO_CV_BUANA_SASTRA_DAHANA.png') ?>" alt="Logo CV BUANA"></span><span class="brand-name">CV BUANA<span>SASTRA DAHANA</span></span></a><p>Konstruksi, renovasi, dan supply material.<br>Mulai dari rencana. Wujudkan bersama.</p></div>
  <div><h2>Jelajahi</h2><a href="<?= url('') ?>#tentang">Tentang Kami</a><a href="<?= url('') ?>#project">Dokumentasi Proyek</a><a href="<?= url('legalitas') ?>">Legalitas Kami</a><a data-install-app href="<?= url('login') ?>#pasang-aplikasi">Unduh Aplikasi</a></div>
  <div><h2>Hubungi Kami</h2><a href="https://wa.me/6281214296499">WhatsApp: 0812-1429-6499</a><a href="https://wa.me/62882000119208">Admin: 0882-0001-19208</a><a href="mailto:customerservice@buanasastradahana.online">customerservice@buanasastradahana.online</a></div>
</div><div class="wrap footer-bottom"><span>© <?= date('Y') ?> CV BUANA SASTRA DAHANA</span><span>Purwakarta · Jawa Barat · Indonesia</span></div></footer>
<a class="floating-contact" href="https://wa.me/6281214296499" aria-label="Hubungi CV BUANA melalui WhatsApp"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6A8.4 8.4 0 0 1 12.5 3h.5a8.5 8.5 0 0 1 8 8v.5Z"/><path d="M8 8c0 4 4 8 8 8l1-2-3-1-1 1-3-3 1-1-1-3-2 1Z"/></svg><span>Hubungi Kami</span></a>
<script src="<?= asset('js/compro.js') ?>?v=20260925-1" defer></script>
<?php require ROOT_PATH.'/app/Views/partials/install.php'; ?>
</body></html>

