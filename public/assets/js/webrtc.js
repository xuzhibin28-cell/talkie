/**
 * WebRTC Audio Mesh Manager for Digital Walkie-Talkie
 * Handles audio-only peer connections, ICE negotiation, microphone permission, and floor audio routing.
 */
class WebRtcManager {
    constructor(config, signaling) {
        this.config = config;
        this.signaling = signaling;
        this.peers = new Map(); // peerId -> { pc: RTCPeerConnection, audioEl: HTMLAudioElement }
        this.localStream = null;
        this.micPermissionGranted = false;
        this.iceServers = config.iceServers || [{ urls: 'stun:stun.l.google.com:19302' }];
        this.remoteAudioContainer = document.getElementById('remoteAudioContainer');

        this.initSignalingListeners();
    }

    initSignalingListeners() {
        this.signaling.on('webrtcSignal', async (fromPeerId, fromNickname, signal) => {
            await this.handleIncomingSignal(fromPeerId, signal);
        });

        this.signaling.on('peerLeft', (peerId) => {
            this.closePeer(peerId);
        });
    }

    /**
     * Request microphone access with echo cancellation & noise suppression
     */
    async initMicrophone() {
        if (this.localStream) return true;

        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Microphone API not supported in this browser or insecure context (HTTPS required).');
            }

            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                },
                video: false
            });

            // Keep tracks disabled until PTT floor is acquired
            this.setMicEnabled(false);
            this.micPermissionGranted = true;

            const micStatus = document.getElementById('micStatusText');
            if (micStatus) {
                micStatus.textContent = 'READY (STANDBY)';
                micStatus.className = 'text-success fw-bold';
            }

            return true;
        } catch (err) {
            console.warn('[WebRTC] Microphone access denied or unavailable:', err);
            this.micPermissionGranted = false;

            const micStatus = document.getElementById('micStatusText');
            if (micStatus) {
                micStatus.textContent = 'PERMISSION DENIED / LISTENER ONLY';
                micStatus.className = 'text-danger fw-bold';
            }

            if (window.showToast) {
                window.showToast('Microphone access is required to speak. You can still listen to others.', 'warning');
            }
            return false;
        }
    }

    setMicEnabled(enabled) {
        if (!this.localStream) return;
        this.localStream.getAudioTracks().forEach(track => {
            track.enabled = enabled;
        });

        const micStatus = document.getElementById('micStatusText');
        if (micStatus) {
            if (enabled) {
                micStatus.textContent = 'TRANSMITTING (LIVE)';
                micStatus.className = 'text-danger fw-bold pulse-icon';
            } else {
                micStatus.textContent = this.micPermissionGranted ? 'READY (STANDBY)' : 'MUTED';
                micStatus.className = 'text-success fw-bold';
            }
        }
    }

    /**
     * Create or retrieve an RTCPeerConnection for a remote peer
     */
    getOrCreatePeerConnection(peerId) {
        if (this.peers.has(peerId)) {
            return this.peers.get(peerId).pc;
        }

        const pc = new RTCPeerConnection({
            iceServers: this.iceServers,
            iceCandidatePoolSize: 2
        });

        // Add local tracks if available
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                pc.addTrack(track, this.localStream);
            });
        }

        // ICE candidate handler
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.signaling.sendSignal(peerId, {
                    type: 'candidate',
                    candidate: event.candidate
                });
            }
        };

        // Handle incoming remote audio stream
        pc.ontrack = (event) => {
            console.log(`[WebRTC] Received audio track from peer: ${peerId}`);
            const remoteStream = event.streams[0] || new MediaStream([event.track]);
            this.attachRemoteAudio(peerId, remoteStream);
        };

        pc.oniceconnectionstatechange = () => {
            console.log(`[WebRTC] ICE state [${peerId}]: ${pc.iceConnectionState}`);
            if (pc.iceConnectionState === 'disconnected' || pc.iceConnectionState === 'failed') {
                // Connection recovery or cleanup
            }
        };

        this.peers.set(peerId, { pc, audioEl: null, remoteStream: null });
        return pc;
    }

    attachRemoteAudio(peerId, stream) {
        let entry = this.peers.get(peerId);
        if (!entry) return;

        let audioEl = entry.audioEl;
        if (!audioEl) {
            audioEl = document.createElement('audio');
            audioEl.id = `audio_${peerId}`;
            audioEl.autoplay = true;
            audioEl.playsInline = true;
            if (this.remoteAudioContainer) {
                this.remoteAudioContainer.appendChild(audioEl);
            }
            entry.audioEl = audioEl;
        }

        audioEl.srcObject = stream;
        entry.remoteStream = stream;

        // Auto-play remote stream
        audioEl.play().catch(e => {
            console.log('[WebRTC] Autoplay waiting for user interaction:', e);
        });

        // Connect remote stream to visualizer if this peer is speaking
        if (window.audioLevelMonitor && window.currentSpeakerId === peerId) {
            window.audioLevelMonitor.connectStream(stream, 'rx');
        }
    }

    /**
     * Start transmitting: enable mic & renegotiate offers to all channel peers
     */
    async startTransmitting(connectedPeers) {
        await this.initMicrophone();
        this.setMicEnabled(true);

        if (this.localStream && window.audioLevelMonitor) {
            window.audioLevelMonitor.connectStream(this.localStream, 'tx');
        }

        // Send WebRTC Offer to all other peers in the channel
        for (const peer of connectedPeers) {
            if (peer.id === this.config.peerId) continue;

            const pc = this.getOrCreatePeerConnection(peer.id);

            // Ensure our local tracks are added to the connection
            if (this.localStream) {
                const senders = pc.getSenders();
                this.localStream.getTracks().forEach(track => {
                    const exists = senders.some(s => s.track === track);
                    if (!exists) {
                        try { pc.addTrack(track, this.localStream); } catch (e) {}
                    }
                });
            }

            try {
                const offer = await pc.createOffer({
                    offerToReceiveAudio: true,
                    offerToReceiveVideo: false
                });
                await pc.setLocalDescription(offer);

                this.signaling.sendSignal(peer.id, {
                    type: 'offer',
                    sdp: pc.localDescription
                });
            } catch (err) {
                console.error(`[WebRTC] Create offer failed for ${peer.id}:`, err);
            }
        }
    }

    /**
     * Stop transmitting: mute mic & reset visualizer
     */
    stopTransmitting() {
        this.setMicEnabled(false);
        if (window.audioLevelMonitor) {
            window.audioLevelMonitor.disconnect();
        }
    }

    /**
     * Process incoming WebRTC signaling data (Offer, Answer, ICE candidate)
     */
    async handleIncomingSignal(fromPeerId, signal) {
        try {
            const pc = this.getOrCreatePeerConnection(fromPeerId);

            if (signal.type === 'offer') {
                await pc.setRemoteDescription(new RTCSessionDescription(signal.sdp));

                // Ensure local track is in pc (muted if we're not speaker)
                if (this.localStream) {
                    const senders = pc.getSenders();
                    this.localStream.getTracks().forEach(track => {
                        const exists = senders.some(s => s.track === track);
                        if (!exists) {
                            try { pc.addTrack(track, this.localStream); } catch (e) {}
                        }
                    });
                }

                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);

                this.signaling.sendSignal(fromPeerId, {
                    type: 'answer',
                    sdp: pc.localDescription
                });
            } else if (signal.type === 'answer') {
                if (pc.signalingState === 'have-local-offer') {
                    await pc.setRemoteDescription(new RTCSessionDescription(signal.sdp));
                }
            } else if (signal.type === 'candidate') {
                if (signal.candidate) {
                    await pc.addIceCandidate(new RTCIceCandidate(signal.candidate));
                }
            }
        } catch (err) {
            console.warn(`[WebRTC] Handle signal error from ${fromPeerId}:`, err);
        }
    }

    closePeer(peerId) {
        if (this.peers.has(peerId)) {
            const { pc, audioEl } = this.peers.get(peerId);
            try { pc.close(); } catch (e) {}
            if (audioEl && audioEl.parentNode) {
                audioEl.parentNode.removeChild(audioEl);
            }
            this.peers.delete(peerId);
        }
    }

    closeAll() {
        this.peers.forEach((val, id) => this.closePeer(id));
        if (this.localStream) {
            this.localStream.getTracks().forEach(t => t.stop());
            this.localStream = null;
        }
    }
}
