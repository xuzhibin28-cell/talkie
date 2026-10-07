<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\QrService;

/**
 * Controller serving pure SVG QR codes.
 */
class QrController
{
    public function render(Request $request): void
    {
        $url = (string)$request->query('url', '');
        if ($url === '') {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $basePath = (string)config('base_path', '/talkie');
            $url = $scheme . '://' . $host . rtrim($basePath, '/') . '/';
        }

        $svg = QrService::renderSvg($url, 240, '#00e676', '#111417');

        $resp = new Response();
        $resp->setStatusCode(200);
        $resp->header('Content-Type', 'image/svg+xml; charset=utf-8');
        $resp->header('Cache-Control', 'public, max-age=3600');
        $resp->setContent($svg);
        $resp->send();
    }
}
