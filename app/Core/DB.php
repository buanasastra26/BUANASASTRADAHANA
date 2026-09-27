<?php
namespace App\Core;

use PDO;
use PDOException;

final class DB
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo) return self::$pdo;

        $driver = (string) env('DB_DRIVER', 'mysql');
        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . ROOT_PATH . '/' . (string) env('DB_NAME', 'database/database.sqlite');
            self::$pdo = new PDO($dsn);
        } else {
            $host = (string) env('DB_HOST', 'localhost');
            $port = (string) env('DB_PORT', '3306');
            $name = (string) env('DB_NAME', 'cv_buana');
            $user = (string) env('DB_USER', 'root');
            $pass = (string) env('DB_PASS', '');
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $user, $pass);
        }
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return self::$pdo;
    }
}
