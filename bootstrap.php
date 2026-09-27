<?php

declare(strict_types=1);

define('ROOT_PATH', __DIR__);

define('STORAGE_PATH', ROOT_PATH . '/storage');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = ROOT_PATH . '/app/' . $relative . '.php';
    if (is_file($file)) require $file;
});

require ROOT_PATH . '/app/Core/helpers.php';

App\Core\Env::load(ROOT_PATH . '/.env');

if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('session.use_strict_mode', '1');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    set_exception_handler(function (\Throwable $error): void {
        error_log((string) $error);
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Layanan belum tersedia</title><body><h1>Layanan belum tersedia</h1><p>Silakan coba kembali beberapa saat lagi.</p><a href="/">Kembali ke beranda</a></body></html>';
    });
}

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Jakarta'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name((string) env('SESSION_NAME', 'cv_buana_session'));
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}
