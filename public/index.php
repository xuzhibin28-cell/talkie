<?php
declare(strict_types=1);

/**
 * Walkie-Talkie Web Application
 * Front Controller
 */

define('APP_START', microtime(true));

// Setup class autoloader for App namespace
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Load helpers
require_once dirname(__DIR__) . '/app/Helpers/helpers.php';

// Configure error reporting
$isDebug = (bool)config('debug', false);
if ($isDebug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Start secure session
\App\Core\Session::start();

try {
    $router = new \App\Core\Router();
    require dirname(__DIR__) . '/config/routes.php';

    $request = new \App\Core\Request();
    $router->dispatch($request);
} catch (\Throwable $e) {
    \App\Core\Logger::error("Uncaught exception: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    if (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
        \App\Core\Response::json([
            'success' => false,
            'error' => $isDebug ? $e->getMessage() : 'An unexpected server error occurred.',
        ], 500);
    } else {
        \App\Core\View::render('errors.500', [
            'title' => 'Server Error',
            'message' => $isDebug ? $e->getMessage() : 'An unexpected error occurred while processing your request.',
            'debug' => $isDebug ? $e->getTraceAsString() : null,
        ], 'main');
    }
}
