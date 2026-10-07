/**
 * Progressive Web App (PWA) Handler
 * Registers Service Worker and manages install prompt banner.
 */

(function () {
    let deferredPrompt = null;
    const btnInstall = document.getElementById('btnPwaInstall');

    // Register Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const basePath = (window.TALKIE_CONFIG && window.TALKIE_CONFIG.basePath) ? window.TALKIE_CONFIG.basePath : '/talkie';
            const swUrl = basePath.replace(/\/$/, '') + '/sw.js';

            navigator.serviceWorker.register(swUrl)
                .then(reg => {
                    console.log('[PWA] Service Worker registered:', reg.scope);
                })
                .catch(err => {
                    console.warn('[PWA] Service Worker registration failed:', err);
                });
        });
    }

    // Capture install prompt
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        if (btnInstall) {
            btnInstall.classList.remove('d-none');
            btnInstall.classList.add('d-inline-flex');

            btnInstall.addEventListener('click', async () => {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`[PWA] Install prompt outcome: ${outcome}`);
                deferredPrompt = null;
                btnInstall.classList.add('d-none');
                btnInstall.classList.remove('d-inline-flex');
            });
        }
    });

    window.addEventListener('appinstalled', () => {
        console.log('[PWA] Walkie Talkie was successfully installed.');
        if (btnInstall) {
            btnInstall.classList.add('d-none');
        }
    });
})();
