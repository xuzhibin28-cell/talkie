<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d1117">
    <meta name="description" content="Real-time Web-Based Push-to-Talk Digital Walkie-Talkie PWA">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Walkie Talkie">

    <title><?= e($title ?? 'Walkie Talkie PWA') ?></title>

    <!-- PWA Manifest & Icons -->
    <link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= asset('icons/icon.svg') ?>">
    <link rel="apple-touch-icon" href="<?= asset('icons/icon-192.png') ?>">

    <!-- Google Fonts: Orbitron (Tactical HUD) + Rajdhani (Headers) + Inter (Body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Orbitron:wght@600;800;900&family=Rajdhani:wght@600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome 6 Icons via CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Custom Walkie-Talkie Stylesheet -->
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="bg-dark text-light d-flex flex-column min-vh-100">

    <!-- Top Status / Branding Bar -->
    <nav class="navbar navbar-dark bg-darker border-bottom border-secondary-subtle px-3 py-2 sticky-top shadow-sm">
        <div class="container-fluid d-flex align-items-center justify-content-between">
            <a class="navbar-brand d-flex align-items-center gap-2 m-0" href="<?= url('/') ?>">
                <div class="brand-icon-wrapper">
                    <i class="fa-solid fa-walkie-talkie text-success pulse-icon"></i>
                </div>
                <div class="d-flex flex-column lh-1">
                    <span class="brand-title font-orbitron">TALKIE<span class="text-success">.PWA</span></span>
                    <small class="brand-sub text-muted font-mono">PUSH-TO-TALK VOICE</small>
                </div>
            </a>

            <div class="d-flex align-items-center gap-2">
                <!-- PWA Install Button (Shown if install prompt is available) -->
                <button id="btnPwaInstall" class="btn btn-outline-success btn-sm d-none align-items-center gap-1">
                    <i class="fa-solid fa-download"></i>
                    <span class="d-none d-sm-inline">Install App</span>
                </button>

                <!-- Audio Sound FX Toggle -->
                <button id="btnToggleSoundFx" class="btn btn-outline-secondary btn-sm" title="Toggle Sound FX (Chirps & Roger Beep)" type="button">
                    <i class="fa-solid fa-volume-high text-success" id="soundFxIcon"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="flex-grow-1 d-flex flex-column">
        <?= $content ?>
    </main>

    <!-- Global Toast Alert Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
        <div id="appToast" class="toast align-items-center text-bg-dark border-secondary shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2" id="toastMessage">
                    <i class="fa-solid fa-circle-info text-info"></i>
                    <span>Notification</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- App Sound Effects Engine -->
    <script src="<?= asset('js/sound-fx.js') ?>"></script>

    <!-- PWA Service Worker Registration -->
    <script src="<?= asset('js/pwa.js') ?>"></script>
</body>
</html>
