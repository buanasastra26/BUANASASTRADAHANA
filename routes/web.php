<?php

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\ClientController;
use App\Controllers\EstimatorController;
use App\Controllers\HomeController;
use App\Controllers\SurveyController;

$router->get('/', [HomeController::class, 'index']);
$router->get('/legalitas', [HomeController::class, 'legalitas']);
$router->get('/estimasi', [EstimatorController::class, 'create']);
$router->post('/estimasi', [EstimatorController::class, 'store']);
$router->get('/survey', [SurveyController::class, 'create']);
$router->post('/survey', [SurveyController::class, 'store']);

$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'registerForm']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/client', [ClientController::class, 'dashboard']);
$router->get('/client/progress', [ClientController::class, 'progress']);
$router->get('/client/rab-kontrak', [ClientController::class, 'rabContract']);
$router->post('/client/rab/{id}/approve', [ClientController::class, 'approveRab']);
$router->post('/client/contract/{id}/sign', [ClientController::class, 'signContract']);
$router->get('/client/pembayaran', [ClientController::class, 'payments']);
$router->post('/client/pembayaran', [ClientController::class, 'uploadPayment']);
$router->get('/client/dokumen', [ClientController::class, 'documents']);
$router->get('/client/kwitansi/{id}', [ClientController::class, 'receipt']);

$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/customers', [AdminController::class, 'customers']);
$router->get('/admin/estimates', [AdminController::class, 'estimates']);
$router->get('/admin/surveys', [AdminController::class, 'surveys']);
$router->get('/admin/rab', [AdminController::class, 'rabs']);
$router->get('/admin/contracts', [AdminController::class, 'contracts']);
$router->get('/admin/projects', [AdminController::class, 'projects']);
$router->get('/admin/payments', [AdminController::class, 'payments']);
$router->post('/admin/payments/{id}/verify', [AdminController::class, 'verifyPayment']);
$router->get('/admin/documents', [AdminController::class, 'documents']);
$router->get('/admin/master-harga', [AdminController::class, 'masterPrices']);
$router->post('/admin/master-harga', [AdminController::class, 'saveMasterPrice']);
