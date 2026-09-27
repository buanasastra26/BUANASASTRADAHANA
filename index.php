<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\Core\Router;

$router = new Router();
require ROOT_PATH . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
