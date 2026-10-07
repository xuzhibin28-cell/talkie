<?php
declare(strict_types=1);

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\ChannelController;
use App\Controllers\SignalController;
use App\Controllers\QrController;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimiter;

/**
 * Application Route Definitions
 *
 * @var Router $router
 */

$router->get('/', [HomeController::class, 'index']);
$router->post('/join', [ChannelController::class, 'join'], [CsrfMiddleware::class]);
$router->get('/channel/{name}', [ChannelController::class, 'show']);
$router->get('/leave', [ChannelController::class, 'leave']);

// WebRTC Signaling API with rate limiting
$router->post('/signal', [SignalController::class, 'handle'], [RateLimiter::class]);

// QR Code generation
$router->get('/qr', [QrController::class, 'render']);
