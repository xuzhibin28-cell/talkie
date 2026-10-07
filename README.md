# Walkie-Talkie PWA - Web-Based Push-to-Talk Voice System

A modern, responsive, real-time **Push-to-Talk (PTT) Digital Walkie-Talkie Web Application** built with PHP 8.3+, Vanilla JavaScript, WebRTC Audio, and Progressive Web App (PWA) standards.

Suitable for student programming projects, team communication, tactical event coordination, and real-time audio experimentation.

---

## 📻 Key Features

- **Push-to-Talk (PTT) Voice Floor Control**:
  - Tactile circular PTT button supporting Mouse, Touch, Pointer, and Keyboard (`Spacebar`).
  - Strict single-speaker floor control: only one user can transmit at a time.
  - Automatic 30-second speaking floor timeout with live visual countdown progress bar.
  - "Channel Busy" prevention if another user attempts to talk while someone is speaking.
- **Audio-Only WebRTC Mesh Communication**:
  - Real-time peer-to-peer audio transmission with echo cancellation, noise suppression, and auto-gain control.
  - Zero camera/video overhead — optimized specifically for voice.
  - Centrally configurable STUN/ICE server traversal.
- **Dynamic VU Level Meter & Waveform Oscilloscope**:
  - Custom HTML5 Canvas visualizer powered by Web Audio API `AnalyserNode`.
  - Real-time stereo green/amber/red VU bars and oscilloscope wave reacting to both your mic and incoming peer audio.
- **Tactical Sound FX Engine**:
  - In-browser synthesized audio feedback (Radio key-up chirp, Roger beep, squelch static burst, busy warning tone, peer join/leave tones).
  - Works 100% offline without external audio files.
- **Pure PHP HTTP Polling Signaling**:
  - No database required: stores temporary room states with atomic file locking (`flock`) in `storage/rooms/`.
  - Delta sequence numbering for efficient real-time polling.
  - HMAC-SHA256 session token verification for tamper-proof peer identity.
  - Built-in rate limiting per IP to prevent signal flooding.
- **Progressive Web App (PWA)**:
  - Installable on Android, iPhone/iPad, and Windows/Mac Desktop.
  - Service Worker caching for application shell and offline UI loading.
- **Mobile QR Code Pairing**:
  - Pure PHP offline vector SVG QR code generator on the join page for instant smartphone pairing over local Wi-Fi.

---

## 📂 Project Architecture

```text
talkie/
├── .env                  # Active environment configuration
├── .env.example          # Template configuration
├── .htaccess             # Root Apache rewrite & directory protection
├── index.php             # Root fallback front controller
├── README.md             # Project documentation & deployment guide
│
├── config/
│   ├── app.php           # App settings & ICE servers configuration
│   └── routes.php        # HTTP router mappings
│
├── app/
│   ├── Controllers/
│   │   ├── HomeController.php    # Join / landing page controller
│   │   ├── ChannelController.php # Channel view & floor management
│   │   ├── SignalController.php  # WebRTC HTTP signaling endpoint
│   │   └── QrController.php      # Pure SVG QR code generator
│   ├── Core/
│   │   ├── Env.php               # Lightweight .env loader
│   │   ├── Logger.php            # File logger (storage/logs/app.log)
│   │   ├── Request.php           # HTTP request parser & JSON handler
│   │   ├── Response.php          # HTTP response helper (JSON / HTML / Redirect)
│   │   ├── Router.php            # Regex route matcher with middleware support
│   │   ├── Security.php          # HMAC-SHA256 token signer & CSRF validator
│   │   ├── Session.php           # Secure cookie session & peer ID generator
│   │   └── View.php              # PHP template renderer with layout engine
│   ├── Helpers/
│   │   ├── Sanitizer.php         # Nickname & Channel input filters
│   │   └── helpers.php           # Global helper functions (url, asset, e, csrf)
│   ├── Middleware/
│   │   ├── CsrfMiddleware.php    # CSRF protection on POST requests
│   │   └── RateLimiter.php       # File-based rate limiter per IP
│   ├── Models/
│   │   ├── Peer.php              # Connected peer entity
│   │   └── Room.php              # Channel room entity with sequence events
│   └── Services/
│       ├── QrService.php         # Pure-PHP vector QR code matrix renderer
│       └── RoomService.php       # Atomic room storage & WebRTC routing
│
├── views/
│   ├── layouts/
│   │   └── main.php              # Tactical dark HUD base layout
│   ├── join.php                  # Landing page & quick channel picker
│   ├── channel.php               # Main Walkie-Talkie dashboard & PTT deck
│   └── errors/
│       ├── 404.php               # Channel/URL not found
│       └── 500.php               # Server / transmission error
│
├── public/
│   ├── .htaccess                 # Apache rewrite for front controller
│   ├── index.php                 # Application Front Controller & autoloader
│   ├── manifest.webmanifest      # PWA Manifest configuration
│   ├── sw.js                     # PWA Service Worker caching
│   └── assets/
│       ├── css/
│       │   └── app.css           # Custom tactical radio styling & animations
│       ├── js/
│       │   ├── sound-fx.js       # Web Audio API radio tone synthesizer
│       │   ├── audio-level.js    # Canvas VU meter & oscilloscope visualizer
│       │   ├── signaling.js      # HTTP polling WebRTC signaling client
│       │   ├── webrtc.js         # RTCPeerConnection audio mesh manager
│       │   ├── ptt.js            # Push-To-Talk input & countdown controller
│       │   ├── pwa.js            # Service worker installer & install banner
│       │   └── app.js            # Coordinator wiring all modules
│       └── icons/
│           ├── icon.svg          # Tactical walkie-talkie SVG vector icon
│           ├── icon-192.png      # 192x192 PNG icon
│           └── icon-512.png      # 512x512 PNG maskable icon
│
├── storage/
│   ├── rooms/                    # Temporary JSON room states with file locking
│   ├── logs/                     # Application error & diagnostic logs
│   └── ratelimit/                # IP rate limiting buckets
│
└── tests/
    └── SystemTest.php            # Automated 10-point end-to-end test suite
```

---

## 🛠️ System Requirements

- **PHP 8.3+** (Standard CLI or Apache `mod_php`)
- **Web Server**: Apache with `mod_rewrite` (e.g. XAMPP, WampServer, cPanel) or PHP Built-in Server
- **Browser**: Google Chrome, Microsoft Edge, Mozilla Firefox, or Apple Safari
- **Microphone**: Working audio input device
- **Network / HTTPS Note**: Web browsers require a secure context (`https://` or `http://localhost`) to grant microphone access (`getUserMedia`). For LAN mobile testing over HTTP, see the [Mobile Testing Guide](#-mobile-testing-over-lan-wi-fi).

---

## 🚀 XAMPP Installation Guide (Windows)

### Step 1: Copy Project
Copy or extract the `talkie` directory into your XAMPP `htdocs` directory:
```text
C:\xampp\htdocs\talkie
```

### Step 2: Configure Environment
Copy `.env.example` to `.env` (already done by default):
```env
APP_ENV=local
APP_DEBUG=true
APP_URL="http://localhost/talkie"
APP_BASE_PATH="/talkie"

# Secret key for HMAC token signing
SIGNAL_SECRET=walkie_talkie_secret_key_change_in_production_998877

# Maximum peers per room (2 to 8)
SIGNAL_MAX_PEERS=8

# Floor timeout in milliseconds (30 seconds)
SIGNAL_FLOOR_TIMEOUT_MS=30000
```

### Step 3: Start Apache
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.

### Step 4: Open Browser
1. Open Chrome or Edge and navigate to:
   ```text
   http://localhost/talkie
   ```
2. Enter your nickname (e.g. `Maverick`) and channel name (e.g. `team-alpha`).
3. Click **JOIN CHANNEL**.
4. Allow microphone permissions when prompted by your browser.

---

## 🧪 Running Automated Tests

Run the included automated system test suite from terminal:
```bash
php C:\xampp\htdocs\talkie\tests\SystemTest.php
```

The test validates all 10 core behaviors:
1. Single peer join and room initialization.
2. Multi-peer discovery and peer join events.
3. Floor acquisition and speaker broadcast.
4. Floor contention and channel busy denial.
5. WebRTC SDP Offer / Answer forwarding.
6. PTT floor release and floor availability.
7. Consecutive speaker hand-off.
8. Peer disconnection and automatic floor recovery.
9. Cryptographic HMAC-SHA256 token verification.
10. Room capacity limit enforcement (Max 8 peers).

---

## 📱 Mobile Testing over LAN / Wi-Fi

To test between multiple phones and laptops connected to the same Wi-Fi router:

### 1. Find your Computer's Local IP
In PowerShell / Command Prompt:
```powershell
ipconfig
```
Look for `IPv4 Address` under your active Wi-Fi adapter (e.g. `192.168.1.50`).

### 2. Open on Mobile Browser
On your phone's browser, enter:
```text
http://192.168.1.50/talkie
```
*(Or simply scan the QR code displayed on your desktop join page)*

### 3. Enabling Microphone on Mobile (Bypassing HTTP SSL Restriction)

Modern mobile browsers block microphone access on plain `http://` unless it's `http://localhost`. To enable microphone testing on Android Chrome over local Wi-Fi:

1. On your phone, open Chrome and visit:
   ```text
   chrome://flags/#unsafely-treat-insecure-origin-as-secure
   ```
2. Set the flag to **Enabled**.
3. In the text box below, add your computer's IP address:
   ```text
   http://192.168.1.50/talkie
   ```
4. Tap **Relaunch** at the bottom.
5. Microphone access will now work smoothly on mobile!

---

## 📲 Installing as a Progressive Web App (PWA)

### On Android (Chrome)
1. Open the app in Chrome.
2. Tap the **Install App** button on the navbar or open the three-dots menu (⋮) -> **Install app** / **Add to Home screen**.
3. The Walkie-Talkie icon will appear on your home screen and launch in full-screen standalone mode.

### On iPhone / iPad (Safari)
1. Open the app in Safari.
2. Tap the **Share** button (box with arrow pointing up).
3. Scroll down and tap **Add to Home Screen**.
4. Tap **Add**.

### On Desktop (Chrome / Edge)
1. Click the **Install App** button in the top navbar or click the install icon in the URL address bar.
2. The app will launch as an independent desktop window.

---

## 🌐 cPanel / Linux Web Hosting Deployment

1. **Upload Files**:
   Upload the contents of `talkie` to your domain folder (e.g. `public_html/talkie` or your subdomain root).

2. **Set File Permissions**:
   Ensure the web server can write to the storage directories:
   ```bash
   chmod -R 775 storage/
   ```

3. **Configure `.env`**:
   - Set `APP_ENV=production` and `APP_DEBUG=false`.
   - Update `APP_URL=https://yourdomain.com/talkie`.
   - Update `APP_BASE_PATH=/talkie` (or `/` if on a root subdomain).
   - Generate a strong random string for `SIGNAL_SECRET`.

4. **Enable HTTPS**:
   Activate free Let's Encrypt SSL in cPanel to ensure secure microphone permissions.

---

## 📡 WebRTC NAT & STUN/TURN Considerations

- The application uses Google's public STUN servers (`stun.l.google.com:19302`) for NAT traversal and candidate discovery.
- **Local Wi-Fi / Standard Home Networks**: STUN provides 100% direct peer-to-peer audio connectivity.
- **Strict Corporate / Symmetric Cellular NAT**: If both peers are behind symmetric NAT firewalls (such as 4G/5G carrier-grade NAT to corporate VPN), direct P2P audio may require a TURN relay server (e.g., Coturn). You can add your TURN credentials directly to `.env`:
  ```env
  ICE_SERVERS='[{"urls":"stun:stun.l.google.com:19302"},{"urls":"turn:turn.yourdomain.com:3478","username":"user","credential":"password"}]'
  ```

---

## 🔧 Troubleshooting

| Issue | Cause | Solution |
|---|---|---|
| **Microphone permission denied** | Browser blocked mic or accessed via unsecure HTTP | Click the lock/settings icon in the URL bar and set Microphone to "Allow". For LAN testing, enable Chrome insecure origin flag or use HTTPS. |
| **"Channel is currently full"** | Room has reached maximum allowed peers (default 8) | Join a different channel name or increase `SIGNAL_MAX_PEERS` in `.env`. |
| **Channel busy warning** | Another peer is actively transmitting | Wait for the speaker to release PTT or for the 30-second floor timer to auto-expire. |
| **Audio not playing on mobile** | Mobile browser audio autoplay policy | Tap anywhere on the screen or press PTT once to allow the browser AudioContext to resume. |
| **Reconnecting / Offline** | Network connection dropped or Apache stopped | Check Apache status in XAMPP; the signaling client automatically attempts exponential reconnection. |

---

## 📄 License & Academic Note

This project is created for educational and practical web development learning. Clean, modern MVC code without heavy framework dependencies makes it easy to study WebRTC, Progressive Web Apps, Web Audio API, and real-time signaling architectures.
