<?php
namespace App\Core;

final class Auth
{
    private static ?array $userCache = null;

    public static function id(): ?int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
    public static function check(): bool { return self::id() !== null; }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        if (self::$userCache) return self::$userCache;
        $st = DB::conn()->prepare('SELECT id, name, whatsapp, email, role, created_at FROM users WHERE id = ? LIMIT 1');
        $st->execute([self::id()]);
        self::$userCache = $st->fetch() ?: null;
        return self::$userCache;
    }

    public static function attempt(string $email, string $password): bool
    {
        $st = DB::conn()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $st->execute([strtolower(trim($email))]);
        $user = $st->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) return false;
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        self::$userCache = null;
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        self::$userCache = null;
        session_regenerate_id(true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('error', 'Silakan login terlebih dahulu.');
            redirect('login');
        }
    }

    public static function requireRole(string $role): void
    {
        self::requireLogin();
        if ((self::user()['role'] ?? '') !== $role) {
            http_response_code(403);
            exit('Akses ditolak.');
        }
    }
}
