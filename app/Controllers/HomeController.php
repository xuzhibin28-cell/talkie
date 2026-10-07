<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Services\QrService;

/**
 * Controller for the application landing / join page.
 */
class HomeController
{
    public function index(Request $request): void
    {
        $savedNickname = (string)Session::get('nickname', '');
        $savedChannel = (string)Session::get('channel', 'alpha-team');
        $errorMessage = Session::flash('error');

        // Detect current server URL or LAN IP for mobile QR Code
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        ) ? 'https' : 'http';

        $basePath = (string)config('base_path', '/talkie');
        $currentUrl = $scheme . '://' . $host . rtrim($basePath, '/') . '/';

        // Render QR Code SVG for quick mobile pairing
        $qrSvg = QrService::renderSvg($currentUrl, 160, '#00e676', '#12161a');

        View::render('join', [
            'title' => 'Join Channel - Walkie Talkie PWA',
            'savedNickname' => $savedNickname,
            'savedChannel' => $savedChannel,
            'errorMessage' => $errorMessage,
            'currentUrl' => $currentUrl,
            'qrSvg' => $qrSvg,
        ], 'main');
    }
}
