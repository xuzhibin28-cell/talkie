/**
 * Sound FX Synthesizer for Digital Walkie-Talkie
 * Uses Web Audio API to synthesize tactical radio sounds (Key-up, Roger beep, Squelch, Busy tone)
 */
class SoundFxEngine {
    constructor() {
        this.ctx = null;
        this.enabled = true;
        this.initStorage();
    }

    initStorage() {
        const saved = localStorage.getItem('talkie_sound_fx');
        if (saved !== null) {
            this.enabled = saved === 'true';
        }
        this.updateUi();
    }

    getContext() {
        if (!this.ctx) {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
                this.ctx = new AudioCtx();
            }
        }
        if (this.ctx && this.ctx.state === 'suspended') {
            this.ctx.resume();
        }
        return this.ctx;
    }

    toggle() {
        this.enabled = !this.enabled;
        localStorage.setItem('talkie_sound_fx', this.enabled ? 'true' : 'false');
        this.updateUi();
        if (this.enabled) {
            this.playTxStart();
        }
        return this.enabled;
    }

    updateUi() {
        const icon = document.getElementById('soundFxIcon');
        const btn = document.getElementById('btnToggleSoundFx');
        if (icon) {
            if (this.enabled) {
                icon.className = 'fa-solid fa-volume-high text-success';
                if (btn) btn.title = 'Sound FX Enabled (Click to Mute)';
            } else {
                icon.className = 'fa-solid fa-volume-xmark text-secondary';
                if (btn) btn.title = 'Sound FX Muted (Click to Enable)';
            }
        }
    }

    /**
     * Tactical key-up chirp when PTT is pressed
     */
    playTxStart() {
        if (!this.enabled) return;
        const ctx = this.getContext();
        if (!ctx) return;

        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(800, now);
        osc.frequency.exponentialRampToValueAtTime(1400, now + 0.06);

        gain.gain.setValueAtTime(0.15, now);
        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.08);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now);
        osc.stop(now + 0.08);
    }

    /**
     * Roger beep + Squelch static burst when PTT is released
     */
    playTxEnd() {
        if (!this.enabled) return;
        const ctx = this.getContext();
        if (!ctx) return;

        const now = ctx.currentTime;

        // 1. Classic Roger Beep tone (1200Hz)
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(1150, now);

        gain.gain.setValueAtTime(0.12, now);
        gain.gain.setValueAtTime(0.12, now + 0.09);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.12);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now);
        osc.stop(now + 0.12);

        // 2. Short squelch noise burst
        const bufferSize = ctx.sampleRate * 0.08;
        const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        const data = buffer.getChannelData(0);
        for (let i = 0; i < bufferSize; i++) {
            data[i] = Math.random() * 2 - 1;
        }

        const noise = ctx.createBufferSource();
        noise.buffer = buffer;

        const noiseGain = ctx.createGain();
        noiseGain.gain.setValueAtTime(0.04, now + 0.1);
        noiseGain.gain.exponentialRampToValueAtTime(0.001, now + 0.18);

        // Filter to make static sound like analog radio squelch
        const filter = ctx.createBiquadFilter();
        filter.type = 'bandpass';
        filter.frequency.setValueAtTime(1800, now);
        filter.Q.setValueAtTime(3, now);

        noise.connect(filter);
        filter.connect(noiseGain);
        noiseGain.connect(ctx.destination);

        noise.start(now + 0.1);
        noise.stop(now + 0.18);
    }

    /**
     * Warning tone when floor is busy / denied
     */
    playChannelBusy() {
        if (!this.enabled) return;
        const ctx = this.getContext();
        if (!ctx) return;

        const now = ctx.currentTime;

        [0, 0.12].forEach(offset => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(220, now + offset);

            gain.gain.setValueAtTime(0.12, now + offset);
            gain.gain.exponentialRampToValueAtTime(0.01, now + offset + 0.08);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(now + offset);
            osc.stop(now + offset + 0.08);
        });
    }

    /**
     * Notification tone when a peer joins
     */
    playPeerJoin() {
        if (!this.enabled) return;
        const ctx = this.getContext();
        if (!ctx) return;

        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(520, now);
        osc.frequency.setValueAtTime(780, now + 0.08);

        gain.gain.setValueAtTime(0.08, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.16);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(now);
        osc.stop(now + 0.16);
    }
}

// Global instance
window.soundFx = new SoundFxEngine();

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('btnToggleSoundFx');
    if (btn) {
        btn.addEventListener('click', () => {
            window.soundFx.toggle();
        });
    }
});
