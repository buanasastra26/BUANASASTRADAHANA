<?php use App\Core\Auth; ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? env('APP_NAME','CV BUANA SASTRA DAHANA')) ?></title>
<meta name="description" content="CV BUANA SASTRA DAHANA - konstruksi, renovasi, pekerjaan aluminium, dan supply material.">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="manifest" href="/manifest.webmanifest"><link rel="apple-touch-icon" href="/assets/images/branding/app-192.png"><meta name="apple-mobile-web-app-capable" content="yes">
</head>
<body>
<header class="site-header">
  <div class="container nav-wrap">
    <a class="brand" href="<?= url('') ?>"><img src="<?= asset('images/branding/LOGO_CV_BUANA_SASTRA_DAHANA.png') ?>" alt="Logo CV BUANA SASTRA DAHANA"><span>CV BUANA SASTRA DAHANA</span></a>
    <button class="nav-toggle" type="button" aria-label="Buka menu">☰</button>
    <nav class="main-nav">
      <a href="<?= url('') ?>#tentang">Tentang Kami</a><a href="<?= url('') ?>#layanan">Layanan</a><a href="<?= url('') ?>#project">Project</a><a href="<?= url('estimasi') ?>">Estimasi Biaya</a><a href="<?= url('') ?>#kontak">Kontak</a>
      <?php if (Auth::check()): ?><a class="nav-cta" href="<?= url((Auth::user()['role']??'client')==='admin'?'admin':'client') ?>">Dashboard</a><?php else: ?><a class="nav-cta" href="<?= url('login') ?>">Login Client</a><?php endif; ?>
    </nav>
  </div>
</header>
<?php if ($m=flash('success')): ?><div class="flash success container"><?= e($m) ?></div><?php endif; ?>
<?php if ($m=flash('error')): ?><div class="flash error container"><?= e($m) ?></div><?php endif; ?>
<main><?= $content ?></main>
<footer class="site-footer"><div class="container footer-grid"><div><img class="footer-logo" src="<?= asset('images/branding/LOGO_CV_BUANA_SASTRA_DAHANA.png') ?>" alt="Logo"><p>Platform digital CV BUANA SASTRA DAHANA.</p></div><div><strong>Navigasi</strong><a href="<?= url('estimasi') ?>">Estimasi</a><a href="<?= url('survey') ?>">Ajukan Survey</a><a href="<?= url('login') ?>">Portal Client</a></div><div><strong>Rencanakan pekerjaan Anda</strong><p>Mulai dengan estimasi awal dan ajukan survey untuk menentukan kebutuhan proyek.</p></div></div></footer>
<script src="<?= asset('js/app.js') ?>"></script>
<?php require ROOT_PATH.'/app/Views/partials/install.php'; ?>
</body></html>

