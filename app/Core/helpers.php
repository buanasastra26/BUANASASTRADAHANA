<?php

use App\Core\Env;
use App\Core\Csrf;

function env(string $key, mixed $default = null): mixed { return Env::get($key, $default); }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string {
    $base = rtrim((string) env('APP_URL', ''), '/');
    if ($base === '') return '/' . ltrim($path, '/');
    return $base . '/' . ltrim($path, '/');
}
function asset(string $path): string { return url('assets/' . ltrim($path, '/')); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function csrf_field(): string { return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">'; }
function flash(string $key, ?string $value = null): ?string {
    if ($value !== null) { $_SESSION['_flash'][$key] = $value; return null; }
    $out = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $out;
}
function request_input(string $key, mixed $default = null): mixed { return $_POST[$key] ?? $_GET[$key] ?? $default; }
function money(int|float|string|null $amount): string { return 'Rp ' . number_format((float)($amount ?? 0), 0, ',', '.'); }
