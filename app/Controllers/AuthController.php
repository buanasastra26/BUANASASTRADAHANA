<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\View;

final class AuthController
{
    public function loginForm(): void { View::render('auth/login', ['title' => 'Login Client']); }
    public function registerForm(): void { View::render('auth/register', ['title' => 'Daftar Client']); }

    public function login(): void
    {
        Csrf::enforce();
        if (Auth::attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
            $role = Auth::user()['role'] ?? 'client';
            redirect($role === 'admin' ? 'admin' : 'client');
        }
        flash('error', 'Email atau password tidak sesuai.');
        redirect('login');
    }

    public function register(): void
    {
        Csrf::enforce();
        $name = trim((string)($_POST['name'] ?? ''));
        $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        if ($name === '' || $whatsapp === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            flash('error', 'Lengkapi data dengan benar. Password minimal 8 karakter.');
            redirect('register');
        }
        $pdo = DB::conn();
        $check = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) { flash('error', 'Email sudah terdaftar.'); redirect('register'); }
        $st = $pdo->prepare("INSERT INTO users (name, whatsapp, email, password_hash, role, created_at, updated_at) VALUES (?, ?, ?, ?, 'client', NOW(), NOW())");
        $st->execute([$name, $whatsapp, $email, password_hash($password, PASSWORD_DEFAULT)]);
        Auth::attempt($email, $password);
        flash('success', 'Akun berhasil dibuat.');
        redirect('client');
    }

    public function logout(): void
    {
        Csrf::enforce();
        Auth::logout();
        redirect('');
    }
}
