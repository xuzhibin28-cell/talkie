/**
 * Real-time Audio Level Monitor & Oscilloscope Visualizer
 * Uses Web Audio API AnalyserNode and HTML5 Canvas (Zero external visualizer libraries)
 */
class AudioLevelMonitor {
    constructor(canvasId = 'audioVisualizerCanvas') {
        this.canvas = document.getElementById(canvasId);
        this.ctx = null;
        this.audioCtx = null;
        this.analyser = null;
        this.sourceNode = null;
        this.animationId = null;
        this.dataArray = null;
        this.peakLevel = 0;
        this.peakDecay = 0.95;
        this.currentSourceType = 'idle'; // 'tx', 'rx', 'idle'

        if (this.canvas) {
            this.ctx = this.canvas.getContext('2d');
            this.resizeCanvas();
            window.addEventListener('resize', () => this.resizeCanvas());
            this.drawIdle();
        }
    }

    resizeCanvas() {
        if (!this.canvas) return;
        const rect = this.canvas.getBoundingClientRect();
        this.canvas.width = rect.width * (window.devicePixelRatio || 1);
        this.canvas.height = 48 * (window.devicePixelRatio || 1);
    }

    getAudioContext() {
        if (!this.audioCtx) {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
                this.audioCtx = new AudioCtx();
            }
        }
        if (this.audioCtx && this.audioCtx.state === 'suspended') {
            this.audioCtx.resume();
        }
        return this.audioCtx;
    }

    /**
     * Connect a MediaStream (microphone or remote peer audio)
     */
    connectStream(mediaStream, type = 'tx') {
        try {
            const ctx = this.getAudioContext();
            if (!ctx || !mediaStream) return;

            this.disconnect();

            this.analyser = ctx.createAnalyser();
            this.analyser.fftSize = 256;
            this.analyser.smoothingTimeConstant = 0.7;

            this.sourceNode = ctx.createMediaStreamSource(mediaStream);
            this.sourceNode.connect(this.analyser);

            this.dataArray = new Uint8Array(this.analyser.frequencyBinCount);
            this.currentSourceType = type;

            const sourceLabel = document.getElementById('audioSourceLabel');
            if (sourceLabel) {
                if (type === 'tx') {
                    sourceLabel.textContent = 'MONITOR: MIC TX (LIVE)';
                    sourceLabel.className = 'badge bg-success text-dark font-mono';
                } else {
                    sourceLabel.textContent = 'MONITOR: RX VOICE';
                    sourceLabel.className = 'badge bg-info text-dark font-mono';
                }
            }

            this.startVisualizing();
        } catch (err) {
            console.warn('[AudioLevelMonitor] Connect stream error:', err);
        }
    }

    disconnect() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }

        if (this.sourceNode) {
            try { this.sourceNode.disconnect(); } catch (e) {}
            this.sourceNode = null;
        }

        this.analyser = null;
        this.currentSourceType = 'idle';

        const sourceLabel = document.getElementById('audioSourceLabel');
        if (sourceLabel) {
            sourceLabel.textContent = 'MONITOR: IDLE';
            sourceLabel.className = 'badge bg-dark border border-secondary text-muted font-mono';
        }

        this.drawIdle();
    }

    startVisualizing() {
        const render = () => {
            if (!this.analyser || !this.ctx || !this.canvas) return;

            this.analyser.getByteFrequencyData(this.dataArray);

            // Compute average RMS level
            let sum = 0;
            for (let i = 0; i < this.dataArray.length; i++) {
                sum += this.dataArray[i];
            }
            const average = sum / this.dataArray.length;
            const normalizedLevel = Math.min(1, average / 128);

            if (normalizedLevel > this.peakLevel) {
                this.peakLevel = normalizedLevel;
            } else {
                this.peakLevel *= this.peakDecay;
            }

            this.draw(normalizedLevel, this.peakLevel);
            this.animationId = requestAnimationFrame(render);
        };

        this.animationId = requestAnimationFrame(render);
    }

    draw(level, peak) {
        if (!this.ctx || !this.canvas) return;
        const width = this.canvas.width;
        const height = this.canvas.height;
        const ctx = this.ctx;

        ctx.clearRect(0, 0, width, height);

        // Background grid lines
        ctx.fillStyle = '#06080a';
        ctx.fillRect(0, 0, width, height);

        ctx.strokeStyle = 'rgba(255, 255, 255, 0.05)';
        ctx.lineWidth = 1;
        for (let x = 0; x < width; x += width / 12) {
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, height);
            ctx.stroke();
        }

        // Draw segmented VU bar
        const totalSegments = 32;
        const segmentWidth = (width - (totalSegments * 3)) / totalSegments;
        const activeSegments = Math.round(level * totalSegments);
        const peakSegment = Math.round(peak * totalSegments);

        for (let i = 0; i < totalSegments; i++) {
            const x = i * (segmentWidth + 3) + 2;
            const isGreen = i < totalSegments * 0.6;
            const isAmber = i >= totalSegments * 0.6 && i < totalSegments * 0.85;
            const isRed = i >= totalSegments * 0.85;

            let color = '#1a222d'; // Off state
            if (i <= activeSegments) {
                if (isGreen) color = '#00e676';
                else if (isAmber) color = '#ffab00';
                else color = '#ff334b';
            } else if (i === peakSegment && peakSegment > 0) {
                color = isRed ? '#ff334b' : (isAmber ? '#ffab00' : '#00e676');
            }

            ctx.fillStyle = color;
            ctx.fillRect(x, 6, segmentWidth, height - 12);
        }

        // Draw glowing level indicator
        if (level > 0.05) {
            ctx.shadowBlur = 10;
            ctx.shadowColor = this.currentSourceType === 'tx' ? 'rgba(0, 230, 118, 0.8)' : 'rgba(41, 182, 246, 0.8)';
            ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
            ctx.fillRect(2, height - 4, width * level, 2);
            ctx.shadowBlur = 0;
        }
    }

    drawIdle() {
        if (!this.ctx || !this.canvas) return;
        const width = this.canvas.width;
        const height = this.canvas.height;
        const ctx = this.ctx;

        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = '#06080a';
        ctx.fillRect(0, 0, width, height);

        // Draw dim idle segment bars
        const totalSegments = 32;
        const segmentWidth = (width - (totalSegments * 3)) / totalSegments;
        ctx.fillStyle = '#141a22';

        for (let i = 0; i < totalSegments; i++) {
            const x = i * (segmentWidth + 3) + 2;
            ctx.fillRect(x, 8, segmentWidth, height - 16);
        }
    }
}

// Global instance
window.audioLevelMonitor = new AudioLevelMonitor();
