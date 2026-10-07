/**
 * Main Application Coordinator
 * Wires together Signaling, WebRTC, PTT, Audio Visualizer, and Participant UI.
 */

// Toast notification helper
window.showToast = function (message, type = 'info') {
    const toastEl = document.getElementById('appToast');
    const toastMsg = document.getElementById('toastMessage');
    if (!toastEl || !toastMsg) return;

    let iconClass = 'fa-solid fa-circle-info text-info';
    if (type === 'success') iconClass = 'fa-solid fa-circle-check text-success';
    if (type === 'warning') iconClass = 'fa-solid fa-triangle-exclamation text-warning';
    if (type === 'danger' || type === 'error') iconClass = 'fa-solid fa-circle-xmark text-danger';

    toastMsg.innerHTML = `<i class="${iconClass}"></i><span>${message}</span>`;
    const toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 4000 });
    toast.show();
};

document.addEventListener('DOMContentLoaded', async () => {
    const config = window.TALKIE_CONFIG;
    if (!config) return;

    window.currentSpeakerId = null;
    window.currentPeersList = [];

    // UI Elements
    const connectionBadge = document.getElementById('connectionBadge');
    const connectionText = document.getElementById('connectionStatusText');
    const speakerBanner = document.getElementById('speakerBanner');
    const speakerLabel = document.getElementById('speakerLabel');
    const speakerSubtext = document.getElementById('speakerSubtext');
    const speakerLed = document.getElementById('speakerLed');
    const peerListEl = document.getElementById('peerList');
    const peerCountBadge = document.getElementById('peerCountBadge');

    // 1. Initialize Signaling Client
    const signaling = new SignalingClient(config);

    // 2. Initialize WebRTC Manager
    const webrtc = new WebRtcManager(config, signaling);

    // 3. Initialize Push-To-Talk Controller
    const ptt = new PttController(config, signaling, webrtc);

    // Connection Status Listener
    signaling.on('statusChange', (status) => {
        if (!connectionBadge || !connectionText) return;

        connectionBadge.className = 'connection-status-badge';
        switch (status) {
            case 'CONNECTED':
                connectionBadge.classList.add('status-connected');
                connectionText.textContent = 'CONNECTED';
                break;
            case 'RECONNECTING':
                connectionBadge.classList.add('status-reconnecting');
                connectionText.textContent = 'RECONNECTING';
                break;
            case 'DISCONNECTED':
                connectionBadge.classList.add('status-disconnected');
                connectionText.textContent = 'DISCONNECTED';
                break;
            case 'CONNECTING':
            default:
                connectionBadge.classList.add('status-connecting');
                connectionText.textContent = 'CONNECTING';
                break;
        }
    });

    // Floor Granted (Someone started speaking)
    signaling.on('pttGranted', (speakerId, speakerNickname) => {
        window.currentSpeakerId = speakerId;
        updateSpeakerUi(speakerId, speakerNickname);
        updatePeerListUi();
    });

    // Floor Released (Speaker stopped talking)
    signaling.on('pttReleased', () => {
        window.currentSpeakerId = null;
        updateSpeakerUi(null, null);
        updatePeerListUi();

        if (window.audioLevelMonitor) {
            window.audioLevelMonitor.disconnect();
        }
    });

    // Floor Timeout
    signaling.on('floorTimeout', (speakerId, speakerNickname, message) => {
        window.currentSpeakerId = null;
        updateSpeakerUi(null, null);
        updatePeerListUi();

        if (window.audioLevelMonitor) {
            window.audioLevelMonitor.disconnect();
        }

        if (window.showToast) {
            window.showToast(message || 'Speaker time limit reached (30s). Floor released.', 'info');
        }
    });

    // Peer update listener
    signaling.on('peersUpdate', (peers, speakerId, speakerNickname) => {
        window.currentPeersList = peers;
        if (speakerId !== undefined) {
            window.currentSpeakerId = speakerId;
            updateSpeakerUi(speakerId, speakerNickname);
        }
        updatePeerListUi();
    });

    // Peer joined notification
    signaling.on('peerJoined', (peer) => {
        if (peer.peer_id !== config.peerId) {
            if (window.soundFx) window.soundFx.playPeerJoin();
            if (window.showToast) window.showToast(`${peer.nickname} joined the channel`, 'info');
        }
    });

    // Peer left notification
    signaling.on('peerLeft', (peerId, nickname) => {
        if (peerId !== config.peerId && nickname) {
            if (window.showToast) window.showToast(`${nickname} left the channel`, 'secondary');
        }
    });

    function updateSpeakerUi(speakerId, nickname) {
        if (!speakerBanner || !speakerLabel || !speakerSubtext) return;

        speakerBanner.className = 'speaker-banner p-3 rounded mb-3 text-center';

        if (!speakerId) {
            // Nobody speaking (Channel is idle)
            speakerBanner.classList.add('speaker-idle');
            speakerLabel.textContent = 'WAITING FOR SPEAKER';
            speakerLabel.className = 'speaker-label font-orbitron fw-bold text-light';
            speakerSubtext.textContent = 'Channel is open. Hold PTT to transmit.';
        } else if (speakerId === config.peerId) {
            // Self is speaking
            speakerBanner.classList.add('speaker-active-self');
            speakerLabel.textContent = '🔴 LIVE: YOU ARE TRANSMITTING';
            speakerLabel.className = 'speaker-label font-orbitron fw-bold text-success';
            speakerSubtext.textContent = 'All channel members are listening to you.';
        } else {
            // Another peer is speaking
            speakerBanner.classList.add('speaker-active-other');
            speakerLabel.textContent = `🔴 LIVE: ${nickname || 'A peer'} IS TALKING`;
            speakerLabel.className = 'speaker-label font-orbitron fw-bold text-danger';
            speakerSubtext.textContent = 'Floor is occupied. Please wait for them to finish.';

            // Connect remote stream to visualizer if available
            const entry = webrtc.peers.get(speakerId);
            if (entry && entry.remoteStream && window.audioLevelMonitor) {
                window.audioLevelMonitor.connectStream(entry.remoteStream, 'rx');
            }
        }
    }

    function updatePeerListUi() {
        if (!peerListEl) return;

        const peers = window.currentPeersList || [];

        if (peerCountBadge) {
            peerCountBadge.textContent = `${peers.length} / ${config.maxPeers} USERS`;
        }

        let html = '';
        peers.forEach(p => {
            const isSelf = (p.id === config.peerId);
            const isSpeaker = (p.id === window.currentSpeakerId || p.is_speaking);

            let cardClass = 'peer-card-item p-2 mb-2 rounded bg-darker border border-secondary-subtle d-flex align-items-center justify-content-between';
            let dotClass = 'peer-dot peer-dot-online';
            let statusBadge = '<span class="badge bg-dark text-secondary border border-secondary font-mono small">LISTENING</span>';

            if (isSpeaker) {
                cardClass += ' peer-speaking-card';
                dotClass = 'peer-dot peer-dot-speaking';
                statusBadge = '<span class="badge bg-danger text-light font-mono small pulse-icon"><i class="fa-solid fa-microphone-lines me-1"></i>SPEAKING</span>';
            }

            html += `
                <div class="${cardClass}">
                    <div class="d-flex align-items-center gap-2">
                        <span class="${dotClass}"></span>
                        <span class="font-mono text-light fw-bold peer-name">${escapeHtml(p.nickname)}</span>
                        ${isSelf ? '<span class="badge bg-secondary text-dark" style="font-size: 0.65rem;">YOU</span>' : ''}
                    </div>
                    ${statusBadge}
                </div>
            `;
        });

        peerListEl.innerHTML = html;
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Connect signaling client
    try {
        await signaling.connect();
    } catch (err) {
        if (window.showToast) {
            window.showToast('Unable to connect to the communication server. ' + err.message, 'danger');
        }
    }
});
