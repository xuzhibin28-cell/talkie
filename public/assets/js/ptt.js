/**
 * Push-To-Talk (PTT) Controller
 * Manages tactile user inputs (Mouse, Touch, Spacebar), floor floor acquisition, sound FX, vibration, and 30s countdown timer.
 */
class PttController {
    constructor(config, signaling, webrtc) {
        this.config = config;
        this.signaling = signaling;
        this.webrtc = webrtc;

        this.btnPtt = document.getElementById('btnPtt');
        this.pttText = document.getElementById('pttText');
        this.pttSubtext = document.getElementById('pttSubtext');
        this.pttIcon = document.getElementById('pttIcon');

        this.isPressed = false;
        this.isTransmitting = false;
        this.spaceKeyPressed = false;
        this.floorTimer = null;
        this.floorDurationMs = config.floorTimeoutMs || 30000;
        this.floorStartTime = 0;

        this.initEventListeners();
    }

    initEventListeners() {
        if (!this.btnPtt) return;

        // Pointer / Mouse events
        this.btnPtt.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return; // Left click only
            this.handlePttPress();
        });

        window.addEventListener('mouseup', () => {
            if (this.isPressed && !this.spaceKeyPressed) {
                this.handlePttRelease();
            }
        });

        // Touch events (with passive: false to prevent scrolling)
        this.btnPtt.addEventListener('touchstart', (e) => {
            e.preventDefault();
            this.handlePttPress();
        }, { passive: false });

        this.btnPtt.addEventListener('touchend', (e) => {
            e.preventDefault();
            this.handlePttRelease();
        }, { passive: false });

        this.btnPtt.addEventListener('touchcancel', (e) => {
            e.preventDefault();
            this.handlePttRelease();
        }, { passive: false });

        // Keyboard Spacebar event
        window.addEventListener('keydown', (e) => {
            // Ignore if focus is in an input or textarea
            const targetTag = e.target.tagName.toLowerCase();
            if (targetTag === 'input' || targetTag === 'textarea') return;

            if (e.code === 'Space' || e.key === ' ') {
                e.preventDefault();
                if (!e.repeat && !this.spaceKeyPressed) {
                    this.spaceKeyPressed = true;
                    this.handlePttPress();
                }
            }
        });

        window.addEventListener('keyup', (e) => {
            if (e.code === 'Space' || e.key === ' ') {
                if (this.spaceKeyPressed) {
                    this.spaceKeyPressed = false;
                    this.handlePttRelease();
                }
            }
        });

        // Context menu suppression on PTT button
        this.btnPtt.addEventListener('contextmenu', (e) => e.preventDefault());
    }

    vibrate(pattern = [30]) {
        if (navigator.vibrate) {
            try { navigator.vibrate(pattern); } catch (e) {}
        }
    }

    async handlePttPress() {
        if (this.isPressed) return;
        this.isPressed = true;

        // Visual tactile press
        this.vibrate([25]);

        // Pre-initialize microphone on first user gesture
        this.webrtc.initMicrophone().catch(() => {});

        // Set UI to requesting state
        this.setButtonState('requesting');

        // Send floor request to signaling server
        const result = await this.signaling.requestPtt();

        if (this.isPressed && result.granted) {
            // Floor GRANTED! Start transmitting!
            this.startTransmission();
        } else if (!result.granted) {
            // Floor DENIED (Channel is busy)
            this.handleFloorDenied(result.message || 'Channel is busy');
        }
    }

    startTransmission() {
        this.isTransmitting = true;
        this.floorStartTime = Date.now();
        this.setButtonState('transmitting');

        // Play chirp sound FX
        if (window.soundFx) {
            window.soundFx.playTxStart();
        }

        // Tactile double vibration
        this.vibrate([40, 20, 40]);

        // Start WebRTC audio transmission
        const connectedPeers = window.currentPeersList || [];
        this.webrtc.startTransmitting(connectedPeers);

        // Start 30s countdown timer
        this.startFloorTimer();
    }

    async handlePttRelease() {
        if (!this.isPressed && !this.isTransmitting) return;

        const wasTransmitting = this.isTransmitting;
        this.isPressed = false;
        this.isTransmitting = false;

        this.stopFloorTimer();

        if (wasTransmitting) {
            // Stop WebRTC audio transmission
            this.webrtc.stopTransmitting();

            // Notify signaling server
            await this.signaling.releasePtt();

            // Play Roger Beep + static squelch sound FX
            if (window.soundFx) {
                window.soundFx.playTxEnd();
            }

            this.vibrate([30]);
        }

        this.setButtonState('idle');
    }

    handleFloorDenied(message) {
        this.isPressed = false;
        this.isTransmitting = false;
        this.setButtonState('busy');

        // Play warning buzz tone
        if (window.soundFx) {
            window.soundFx.playChannelBusy();
        }

        this.vibrate([80, 50, 80]);

        if (window.showToast) {
            window.showToast(message, 'warning');
        }

        // Return to idle after 1.5s
        setTimeout(() => {
            if (!this.isPressed) {
                this.setButtonState('idle');
            }
        }, 1500);
    }

    startFloorTimer() {
        this.stopFloorTimer();

        const progressContainer = document.getElementById('floorProgressContainer');
        const progressBar = document.getElementById('floorProgressBar');

        if (progressContainer && progressBar) {
            progressContainer.classList.remove('d-none');
            progressBar.style.width = '100%';
        }

        const interval = 100;
        this.floorTimer = setInterval(() => {
            const elapsed = Date.now() - this.floorStartTime;
            const remaining = Math.max(0, this.floorDurationMs - elapsed);
            const percentage = (remaining / this.floorDurationMs) * 100;

            if (progressBar) {
                progressBar.style.width = `${percentage}%`;
            }

            // Floor timeout reached (30s max limit)
            if (remaining <= 0) {
                this.stopFloorTimer();
                if (window.showToast) {
                    window.showToast('Talk time limit reached (30s). Floor released.', 'info');
                }
                this.handlePttRelease();
            }
        }, interval);
    }

    stopFloorTimer() {
        if (this.floorTimer) {
            clearInterval(this.floorTimer);
            this.floorTimer = null;
        }

        const progressContainer = document.getElementById('floorProgressContainer');
        if (progressContainer) {
            progressContainer.classList.add('d-none');
        }
    }

    setButtonState(state) {
        if (!this.btnPtt) return;

        this.btnPtt.classList.remove('ptt-idle', 'ptt-transmitting', 'ptt-busy', 'ptt-requesting');
        this.btnPtt.setAttribute('aria-pressed', state === 'transmitting' ? 'true' : 'false');

        switch (state) {
            case 'transmitting':
                this.btnPtt.classList.add('ptt-transmitting');
                this.pttText.textContent = 'TRANSMITTING';
                this.pttSubtext.textContent = 'RELEASE TO FINISH';
                this.pttIcon.className = 'fa-solid fa-microphone-lines ptt-icon mb-1';
                break;

            case 'requesting':
                this.btnPtt.classList.add('ptt-requesting');
                this.pttText.textContent = 'CONNECTING...';
                this.pttSubtext.textContent = 'SECURING FLOOR';
                this.pttIcon.className = 'fa-solid fa-spinner fa-spin ptt-icon mb-1';
                break;

            case 'busy':
                this.btnPtt.classList.add('ptt-busy');
                this.pttText.textContent = 'CHANNEL BUSY';
                this.pttSubtext.textContent = 'WAIT FOR FLOOR';
                this.pttIcon.className = 'fa-solid fa-microphone-slash ptt-icon mb-1';
                break;

            case 'idle':
            default:
                this.btnPtt.classList.add('ptt-idle');
                this.pttText.textContent = 'HOLD TO TALK';
                this.pttSubtext.textContent = '[ SPACEBAR ]';
                this.pttIcon.className = 'fa-solid fa-microphone-lines ptt-icon mb-1';
                break;
        }
    }
}
