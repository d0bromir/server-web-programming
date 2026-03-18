<?php
declare(strict_types=1);

/**
 * Front Controller – единствена входна точка на приложението.
 *
 * Всички HTTP заявки влизат тук (благодарение на .htaccess).
 * Редът на изпълнение:
 *   1. Автозареждане (Composer PSR-4)
 *   2. Сесия
 *   3. Security headers
 *   4. Маршрутизиране → Контролер → Изглед
 */

// ── Автозареждане ─────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Router;
use App\Controller\HomeController;
use App\Controller\AuthController;
use App\Controller\VenueController;
use App\Controller\ApiController;
use App\Controller\BreweryController;

// ── Сесия ─────────────────────────────────────────────────────────────
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

// ── Security headers ──────────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline'");

// ── Маршрутизатор ─────────────────────────────────────────────────────
$router = new Router();

// Публични маршрути
$router->get('/',                   [HomeController::class, 'index']);
$router->get('/brewery',            [BreweryController::class, 'index']);

// Автентикация
$router->get('/login',              [AuthController::class, 'loginForm']);
$router->post('/login',             [AuthController::class, 'login']);
$router->get('/register',           [AuthController::class, 'registerForm']);
$router->post('/register',          [AuthController::class, 'register']);
$router->post('/logout',            [AuthController::class, 'logout']);

// CRUD за заведения (изисква вход)
$router->get('/venues',             [VenueController::class, 'index']);
$router->get('/venues/create',      [VenueController::class, 'createForm']);
$router->post('/venues',            [VenueController::class, 'store']);
$router->get('/venues/{id}/edit',   [VenueController::class, 'editForm']);
$router->post('/venues/{id}',       [VenueController::class, 'update']);    // метод override: _method=PUT
$router->post('/venues/{id}/delete',[VenueController::class, 'destroy']);   // метод override: _method=DELETE

// REST API (JSON, Bearer token)
$router->get('/api/venues',         [ApiController::class, 'index']);
$router->get('/api/venues/{id}',    [ApiController::class, 'show']);
$router->post('/api/venues',        [ApiController::class, 'store']);
$router->put('/api/venues/{id}',    [ApiController::class, 'update']);
$router->delete('/api/venues/{id}', [ApiController::class, 'destroy']);

// ── Dispatch ──────────────────────────────────────────────────────────
$router->dispatch();
