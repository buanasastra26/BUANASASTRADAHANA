<?php
require dirname(__DIR__) . '/bootstrap.php';
use App\Core\DB;

if (PHP_SAPI !== 'cli') exit("Jalankan melalui CLI.\n");
$email = $argv[1] ?? null;
$password = $argv[2] ?? null;
$name = $argv[3] ?? 'Administrator CV BUANA';
if (!$email || !$password || strlen($password) < 8) exit("Cara: php scripts/create_admin.php email@domain.com password_min_8 'Nama Admin'\n");
$st=DB::conn()->prepare("INSERT INTO users (name,whatsapp,email,password_hash,role,created_at,updated_at) VALUES (?,'-',?,?, 'admin',NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),role='admin',updated_at=NOW()");
$st->execute([$name,strtolower($email),password_hash($password,PASSWORD_DEFAULT)]);
echo "Admin siap: {$email}\n";
