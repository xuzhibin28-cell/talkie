/**
 * WebRTC HTTP Polling Signaling Client
 * Handles room registration, delta event polling, floor control, and WebRTC signal routing.
 */
class SignalingClient {
    constructor(config) {
        this.config = config;
        this.status = 'CONNECTING'; // CONNECTING, CONNECTED, RECONNECTING, DISCONNECTED
        this.lastSeq = 0;
        this.isPolling = false;
        this.pollTimer = null;
        this.pollInterval = 800; // ms
        this.consecutiveErrors = 0;
        this.maxBackoffInterval = 4000; // ms

        // Event callbacks
        this.callbacks = {
            statusChange: [],
            peerJoined: [],
            peerLeft: [],
            pttGranted: [],
            pttReleased: [],
            pttDenied: [],
            floorTimeout: [],
            webrtcSignal: [],
            peersUpdate: []
        };

        this.initUnloadHandler();
    }

    on(event, cb) {
        if (this.callbacks[event]) {
            this.callbacks[event].push(cb);
        }
    }

    trigger(event, ...args) {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(cb => {
                try { cb(...args); } catch (e) { console.error(`[Signaling] Callback error on ${event}:`, e); }
            });
        }
    }

    setStatus(newStatus) {
        if (this.status !== newStatus) {
            this.status = newStatus;
            this.trigger('statusChange', newStatus);
        }
    }

    initUnloadHandler() {
        // Send fast leave signal when browser tab/window is closed
        const sendLeaveBeacon = () => {
            if (this.status === 'CONNECTED' || this.status === 'CONNECTING') {
                const payload = JSON.stringify({
                    type: 'leave',
                    token: this.config.token
                });
                const blob = new Blob([payload], { type: 'application/json' });
                navigator.sendBeacon(this.config.signalUrl, blob);
            }
        };

        window.addEventListener('beforeunload', sendLeaveBeacon);
        window.addEventListener('pagehide', sendLeaveBeacon);
    }

    async postSignal(data) {
        data.token = this.config.token;
        const response = await fetch(this.config.signalUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            throw new Error(errData.error || `HTTP ${response.status} ${response.statusText}`);
        }

        return await response.json();
    }

    async connect() {
        this.setStatus('CONNECTING');
        try {
            const res = await this.postSignal({ type: 'hello' });
            if (res.success) {
                this.lastSeq = res.seq || 0;
                this.setStatus('CONNECTED');
                this.consecutiveErrors = 0;

                this.trigger('peersUpdate', res.peers || [], res.speaker_id, res.speaker_nickname);

                if (res.speaker_id) {
                    this.trigger('pttGranted', res.speaker_id, res.speaker_nickname, res.speaker_started_at, res.floor_timeout_ms);
                }

                this.startPolling();
                return res;
            } else {
                throw new Error(res.error || 'Failed to join channel');
            }
        } catch (err) {
            console.error('[Signaling] Connection failed:', err);
            this.setStatus('DISCONNECTED');
            throw err;
        }
    }

    startPolling() {
        if (this.isPolling) return;
        this.isPolling = true;
        this.scheduleNextPoll(this.pollInterval);
    }

    stopPolling() {
        this.isPolling = false;
        if (this.pollTimer) {
            clearTimeout(this.pollTimer);
            this.pollTimer = null;
        }
    }

    scheduleNextPoll(delay) {
        if (!this.isPolling) return;
        this.pollTimer = setTimeout(() => this.executePoll(), delay);
    }

    async executePoll() {
        if (!this.isPolling) return;

        try {
            const res = await this.postSignal({
                type: 'poll',
                last_seq: this.lastSeq
            });

            if (res.success) {
                if (this.status !== 'CONNECTED') {
                    this.setStatus('CONNECTED');
                }
                this.consecutiveErrors = 0;
                this.lastSeq = res.seq || this.lastSeq;

                // Process delta events
                if (Array.isArray(res.events) && res.events.length > 0) {
                    this.processEvents(res.events);
                }

                // Update peer list & active speaker
                if (Array.isArray(res.peers)) {
                    this.trigger('peersUpdate', res.peers, res.speaker_id, res.speaker_nickname);
                }

                this.scheduleNextPoll(this.pollInterval);
            } else {
                throw new Error(res.error || 'Poll error');
            }
        } catch (err) {
            this.consecutiveErrors++;
            console.warn(`[Signaling] Poll error (${this.consecutiveErrors}):`, err.message);

            if (this.consecutiveErrors > 2) {
                this.setStatus('RECONNECTING');
            }
            if (this.consecutiveErrors > 8) {
                this.setStatus('DISCONNECTED');
            }

            // Exponential backoff
            const backoff = Math.min(this.pollInterval * Math.pow(1.5, this.consecutiveErrors), this.maxBackoffInterval);
            this.scheduleNextPoll(backoff);
        }
    }

    processEvents(events) {
        events.forEach(event => {
            const { type, data } = event;
            switch (type) {
                case 'peer_joined':
                    this.trigger('peerJoined', data);
                    break;
                case 'peer_left':
                    this.trigger('peerLeft', data.peer_id, data.nickname, data.reason);
                    break;
                case 'ptt_granted':
                    this.trigger('pttGranted', data.peer_id, data.nickname, data.started_at, data.floor_timeout_ms);
                    break;
                case 'ptt_released':
                    this.trigger('pttReleased', data.peer_id, data.nickname);
                    break;
                case 'floor_timeout':
                    this.trigger('floorTimeout', data.peer_id, data.nickname, data.message);
                    break;
                case 'webrtc_signal':
                    // Filter: deliver if addressed to this peer or broadcast (null)
                    if (!data.to_peer_id || data.to_peer_id === this.config.peerId) {
                        if (data.from_peer_id !== this.config.peerId) {
                            this.trigger('webrtcSignal', data.from_peer_id, data.from_nickname, data.signal);
                        }
                    }
                    break;
            }
        });
    }

    /**
     * Request transmission floor
     */
    async requestPtt() {
        try {
            const res = await this.postSignal({ type: 'ptt_request' });
            if (res.success && res.granted) {
                this.trigger('pttGranted', res.peer_id, res.nickname, res.started_at, res.floor_timeout_ms);
                return { granted: true };
            } else {
                this.trigger('pttDenied', res.speaker_id, res.speaker_nickname, res.message || 'Channel is busy');
                return { granted: false, speakerNickname: res.speaker_nickname, message: res.message };
            }
        } catch (err) {
            console.error('[Signaling] PTT request failed:', err);
            return { granted: false, error: err.message };
        }
    }

    /**
     * Release transmission floor
     */
    async releasePtt() {
        try {
            const res = await this.postSignal({ type: 'ptt_release' });
            if (res.success && res.released) {
                this.trigger('pttReleased', this.config.peerId, this.config.nickname);
            }
            return res;
        } catch (err) {
            console.error('[Signaling] PTT release failed:', err);
        }
    }

    /**
     * Send WebRTC signal (Offer, Answer, ICE Candidate)
     */
    async sendSignal(toPeerId, signalData) {
        try {
            return await this.postSignal({
                type: 'signal',
                to_peer_id: toPeerId,
                signal: signalData
            });
        } catch (err) {
            console.warn('[Signaling] Send signal failed:', err);
        }
    }

    /**
     * Leave channel explicitly
     */
    async leave() {
        this.stopPolling();
        try {
            await this.postSignal({ type: 'leave' });
        } catch (err) {}
        this.setStatus('DISCONNECTED');
    }
}
