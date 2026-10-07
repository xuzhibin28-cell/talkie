<div class="container-fluid flex-grow-1 d-flex flex-column py-2 py-md-3" id="channelView">
    <div class="row justify-content-center flex-grow-1">
        <div class="col-12 col-lg-10 col-xl-8 d-flex flex-column">

            <!-- Tactical Channel Header Bar -->
            <div class="card card-tactical mb-2 mb-md-3 border-secondary-subtle shadow-sm">
                <div class="card-body p-2 p-md-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    
                    <!-- Channel & Nickname Info -->
                    <div class="d-flex align-items-center gap-3">
                        <div class="channel-badge font-orbitron">
                            <i class="fa-solid fa-tower-broadcast text-success me-1"></i>
                            <span class="text-uppercase" id="displayChannelName">#<?= e($channel) ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2 border-start border-secondary ps-3 font-mono small text-secondary">
                            <i class="fa-solid fa-id-card text-success"></i>
                            <span class="text-light fw-semibold" id="displayUserNickname"><?= e($nickname) ?></span>
                            <span class="badge bg-darker border border-secondary text-muted d-none d-sm-inline">YOU</span>
                        </div>
                    </div>

                    <!-- Connection Status Badge & Actions -->
                    <div class="d-flex align-items-center gap-2">
                        <!-- Connection Status Badge -->
                        <div class="connection-status-badge status-connecting" id="connectionBadge">
                            <span class="status-indicator-dot"></span>
                            <span class="status-text font-mono" id="connectionStatusText">CONNECTING</span>
                        </div>

                        <!-- Leave Button -->
                        <a href="<?= url('/leave') ?>" class="btn btn-outline-danger btn-sm font-mono d-flex align-items-center gap-1" id="btnLeaveChannel">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            <span class="d-none d-sm-inline">Leave</span>
                        </a>
                    </div>

                </div>
            </div>

            <!-- Main Walkie-Talkie Operating Area (2 Columns on large screens) -->
            <div class="row g-2 g-md-3 flex-grow-1 align-items-stretch">
                
                <!-- Left / Center: Main PTT & Floor Control Deck -->
                <div class="col-12 col-md-7 d-flex flex-column">
                    <div class="card card-tactical flex-grow-1 border-secondary-subtle shadow d-flex flex-column justify-content-between p-3 p-md-4">
                        
                        <!-- Speaker Status Banner -->
                        <div class="speaker-banner speaker-idle p-3 rounded mb-3 text-center" id="speakerBanner">
                            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                                <span class="speaker-led-indicator" id="speakerLed"></span>
                                <span class="speaker-label font-orbitron fw-bold" id="speakerLabel">WAITING FOR SPEAKER</span>
                            </div>
                            <div class="speaker-subtext font-mono small text-secondary" id="speakerSubtext">
                                Channel is open. Hold PTT to transmit.
                            </div>

                            <!-- Floor Timeout Progress Bar (Active when someone is speaking) -->
                            <div class="progress mt-2 floor-progress-bar d-none" id="floorProgressContainer" style="height: 4px; background-color: rgba(255,255,255,0.1);">
                                <div class="progress-bar bg-danger progress-bar-striped progress-bar-animated" id="floorProgressBar" role="progressbar" style="width: 100%;"></div>
                            </div>
                        </div>

                        <!-- Center: PTT Button Deck -->
                        <div class="ptt-deck-container my-auto py-3 text-center d-flex flex-column align-items-center justify-content-center">
                            
                            <div class="ptt-button-wrapper">
                                <!-- Outer Mechanical Bezel -->
                                <div class="ptt-bezel" id="pttBezel">
                                    <!-- Main Circular PTT Button -->
                                    <button type="button" 
                                            class="ptt-button ptt-idle font-orbitron" 
                                            id="btnPtt"
                                            role="button" 
                                            aria-pressed="false"
                                            aria-label="Push To Talk"
                                            tabindex="0">
                                        
                                        <div class="ptt-inner-glow"></div>
                                        
                                        <div class="ptt-content d-flex flex-column align-items-center justify-content-center">
                                            <i class="fa-solid fa-microphone-lines ptt-icon mb-1" id="pttIcon"></i>
                                            <span class="ptt-text font-orbitron fw-bold" id="pttText">HOLD TO TALK</span>
                                            <span class="ptt-sub font-mono small" id="pttSubtext">[ SPACEBAR ]</span>
                                        </div>

                                    </button>
                                </div>
                            </div>

                            <!-- Keyboard / Touch Instruction -->
                            <div class="mt-3 text-center font-mono small text-secondary">
                                <span class="d-none d-md-inline"><kbd class="bg-darker border border-secondary text-light">SPACEBAR</kbd> or </span>Press & Hold button to speak
                            </div>

                        </div>

                        <!-- Audio VU Meter & Real-time Oscilloscope -->
                        <div class="audio-monitor-deck bg-darker p-3 rounded border border-secondary-subtle mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-1 font-mono small">
                                <span class="text-secondary">
                                    <i class="fa-solid fa-chart-simple text-success me-1"></i> AUDIO LEVEL MONITOR
                                </span>
                                <span class="badge bg-dark border border-secondary text-muted" id="audioSourceLabel">MONITOR: IDLE</span>
                            </div>
                            
                            <!-- HTML5 Canvas for VU meter and live waveform -->
                            <div class="canvas-wrapper">
                                <canvas id="audioVisualizerCanvas" height="50" class="w-100 rounded"></canvas>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Right / Side: Participants List & Hardware Controls -->
                <div class="col-12 col-md-5 d-flex flex-column">
                    <div class="card card-tactical flex-grow-1 border-secondary-subtle shadow d-flex flex-column p-3">
                        
                        <!-- Participant Header -->
                        <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom border-secondary-subtle">
                            <div class="font-orbitron small fw-bold text-uppercase text-light">
                                <i class="fa-solid fa-users text-success me-1"></i> ON THIS CHANNEL
                            </div>
                            <span class="badge bg-darker border border-secondary text-success font-mono" id="peerCountBadge">
                                1 / <?= (int)$maxPeers ?> USERS
                            </span>
                        </div>

                        <!-- Participant List Container -->
                        <div class="peer-list-container flex-grow-1 overflow-y-auto mb-3 pe-1" id="peerList">
                            <!-- Dynamic Peer Cards inserted here -->
                            <div class="peer-card-item peer-card-self p-2 mb-2 rounded bg-darker border border-secondary-subtle d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="peer-dot peer-dot-online"></span>
                                    <span class="font-mono text-light fw-bold"><?= e($nickname) ?></span>
                                    <span class="badge bg-secondary text-dark small" style="font-size: 0.65rem;">YOU</span>
                                </div>
                                <span class="badge bg-dark text-secondary border border-secondary font-mono small">LISTENING</span>
                            </div>
                        </div>

                        <!-- Hardware / Mic Diagnostics Box -->
                        <div class="p-2 bg-darker rounded border border-secondary-subtle small font-mono">
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-secondary-subtle">
                                <span class="text-secondary"><i class="fa-solid fa-microphone me-1"></i> Microphone:</span>
                                <span class="text-warning fw-bold" id="micStatusText">READY (MUTED)</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-secondary-subtle">
                                <span class="text-secondary"><i class="fa-solid fa-shield-halved me-1"></i> Floor Limit:</span>
                                <span class="text-secondary">30s Auto-Release</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-1">
                                <span class="text-secondary"><i class="fa-solid fa-wave-square me-1"></i> Audio Codec:</span>
                                <span class="text-success">Opus / WebRTC</span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<!-- Hidden WebRTC Audio Elements Container for Remote Peers -->
<div id="remoteAudioContainer" class="d-none" aria-hidden="true"></div>

<!-- Inject Configuration for Frontend JavaScript Modules -->
<script>
window.TALKIE_CONFIG = {
    channel: <?= json_encode($channel) ?>,
    nickname: <?= json_encode($nickname) ?>,
    peerId: <?= json_encode($peerId) ?>,
    token: <?= json_encode($token) ?>,
    signalUrl: <?= json_encode($signalUrl) ?>,
    leaveUrl: <?= json_encode($leaveUrl) ?>,
    iceServers: <?= json_encode($iceServers) ?>,
    floorTimeoutMs: <?= (int)$floorTimeoutMs ?>,
    maxPeers: <?= (int)$maxPeers ?>,
    basePath: <?= json_encode((string)config('base_path', '/talkie')) ?>
};
</script>

<!-- Load JavaScript Modules in Order -->
<script src="<?= asset('js/audio-level.js') ?>"></script>
<script src="<?= asset('js/signaling.js') ?>"></script>
<script src="<?= asset('js/webrtc.js') ?>"></script>
<script src="<?= asset('js/ptt.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
