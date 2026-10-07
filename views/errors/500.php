<div class="container my-auto py-5 text-center">
    <div class="card card-tactical d-inline-block p-4 p-md-5 border-secondary-subtle shadow-lg" style="max-width: 540px;">
        <div class="display-1 font-orbitron text-danger fw-bold mb-2">500</div>
        <h2 class="h4 font-orbitron text-uppercase mb-3">Transmission Hardware Error</h2>
        <p class="text-secondary small font-mono mb-4">
            <?= e($message ?? 'A transmission error occurred while processing your request.') ?>
        </p>

        <?php if (!empty($debug)): ?>
            <pre class="bg-darker text-danger text-start p-3 rounded font-mono small mb-4 overflow-x-auto" style="max-height: 200px;"><?= e($debug) ?></pre>
        <?php endif; ?>

        <a href="<?= url('/') ?>" class="btn btn-outline-success font-orbitron px-4 py-2">
            <i class="fa-solid fa-rotate-right me-2"></i> Reconnect Base Station
        </a>
    </div>
</div>
