<div class="container my-auto py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6 col-xl-5">

            <!-- Tactical Walkie Card -->
            <div class="card card-tactical shadow-lg border-secondary-subtle">
                <!-- Card Antenna Accent -->
                <div class="card-antenna"></div>

                <div class="card-body p-4 p-md-5">

                    <!-- Header Brand & Status Light -->
                    <div class="text-center mb-4">
                        <div class="radio-led-indicator mb-2">
                            <span class="led-dot led-green"></span>
                            <span class="font-mono text-muted small">SYSTEM READY</span>
                        </div>
                        <h1 class="h2 font-orbitron text-uppercase fw-bold mb-1 tracking-wider">
                            WALKIE <span class="text-success">TALKIE</span>
                        </h1>
                        <p class="text-secondary small mb-0">
                            Push-to-Talk Real-Time Digital Voice Radio
                        </p>
                    </div>

                    <!-- Error Alert -->
                    <?php if (!empty($errorMessage)): ?>
                        <div class="alert alert-danger alert-dismissible fade show border-0 d-flex align-items-center gap-2 mb-4" role="alert">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <div class="small"><?= e($errorMessage) ?></div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Join Channel Form -->
                    <form action="<?= url('/join') ?>" method="POST" id="joinForm" autocomplete="off">
                        <?= csrf_field() ?>

                        <!-- Nickname Field -->
                        <div class="mb-3">
                            <label for="nickname" class="form-label font-mono small text-uppercase text-secondary fw-semibold">
                                <i class="fa-solid fa-user-tag text-success me-1"></i> Nickname / Call Sign
                            </label>
                            <div class="input-group input-group-tactical">
                                <span class="input-group-text bg-darker border-secondary-subtle text-secondary">
                                    <i class="fa-solid fa-id-badge"></i>
                                </span>
                                <input type="text" 
                                       class="form-control form-control-lg bg-dark text-light border-secondary-subtle font-mono" 
                                       id="nickname" 
                                       name="nickname" 
                                       placeholder="e.g. Maverick, Alex, Alpha-1" 
                                       value="<?= e($savedNickname) ?>" 
                                       maxlength="24" 
                                       required 
                                       autofocus>
                            </div>
                            <div class="form-text text-secondary small">Max 24 characters. Shown to team members.</div>
                        </div>

                        <!-- Channel Field -->
                        <div class="mb-4">
                            <label for="channel" class="form-label font-mono small text-uppercase text-secondary fw-semibold">
                                <i class="fa-solid fa-tower-broadcast text-success me-1"></i> Channel / Frequency
                            </label>
                            <div class="input-group input-group-tactical">
                                <span class="input-group-text bg-darker border-secondary-subtle text-secondary font-mono fw-bold">
                                    #
                                </span>
                                <input type="text" 
                                       class="form-control form-control-lg bg-dark text-light border-secondary-subtle font-mono" 
                                       id="channel" 
                                       name="channel" 
                                       placeholder="e.g. team-alpha, security, room-101" 
                                       value="<?= e($savedChannel) ?>" 
                                       maxlength="64" 
                                       required>
                            </div>
                            <!-- Preset Quick Chips -->
                            <div class="d-flex flex-wrap gap-1 mt-2 align-items-center">
                                <small class="text-muted font-mono me-1">Quick:</small>
                                <button type="button" class="badge bg-dark border border-secondary text-secondary chip-channel" data-channel="team-alpha">#team-alpha</button>
                                <button type="button" class="badge bg-dark border border-secondary text-secondary chip-channel" data-channel="security">#security</button>
                                <button type="button" class="badge bg-dark border border-secondary text-secondary chip-channel" data-channel="ops-101">#ops-101</button>
                                <button type="button" class="badge bg-dark border border-secondary text-secondary chip-channel" data-channel="patrol-4">#patrol-4</button>
                            </div>
                        </div>

                        <!-- Instruction Box -->
                        <div class="p-3 mb-4 rounded bg-darker border border-secondary-subtle small text-secondary d-flex gap-2 align-items-start">
                            <i class="fa-solid fa-circle-info text-info mt-1"></i>
                            <div>
                                Enter the same channel name as your team members to communicate together in real-time.
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-success btn-lg font-orbitron py-3 fw-bold tracking-wider btn-join-tactical">
                                <i class="fa-solid fa-right-to-bracket me-2"></i> JOIN CHANNEL
                            </button>
                        </div>
                    </form>

                    <!-- Mobile QR Code Pairing Section -->
                    <div class="mt-4 pt-3 border-top border-secondary-subtle text-center">
                        <button class="btn btn-sm btn-link text-decoration-none text-secondary d-flex align-items-center justify-content-center gap-1 mx-auto" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#qrCodeCollapse" 
                                aria-expanded="false" 
                                aria-controls="qrCodeCollapse">
                            <i class="fa-solid fa-qrcode text-success"></i>
                            <span>Open on Mobile / Phone</span>
                            <i class="fa-solid fa-chevron-down small ms-1"></i>
                        </button>

                        <div class="collapse mt-3" id="qrCodeCollapse">
                            <div class="p-3 bg-darker rounded border border-secondary-subtle d-inline-block">
                                <div class="qr-code-box mb-2">
                                    <?= $qrSvg ?>
                                </div>
                                <div class="font-mono small text-secondary">
                                    Scan to open on smartphone
                                </div>
                                <div class="font-mono text-muted small mt-1 text-break" style="font-size: 0.75rem;">
                                    <?= e($currentUrl) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Footer Notes -->
            <div class="text-center mt-3 text-secondary font-mono small">
                Voice Only &bull; WebRTC Mesh &bull; PWA Enabled
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Quick channel chips
    const channelInput = document.getElementById('channel');
    document.querySelectorAll('.chip-channel').forEach(btn => {
        btn.addEventListener('click', () => {
            if (channelInput) {
                channelInput.value = btn.dataset.channel;
                channelInput.focus();
            }
        });
    });

    // Auto-normalize channel input while typing
    if (channelInput) {
        channelInput.addEventListener('input', (e) => {
            let val = e.target.value.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-_]/g, '');
            if (val.length > 64) val = val.substring(0, 64);
            e.target.value = val;
        });
    }
});
</script>
