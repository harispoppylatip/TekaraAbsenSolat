<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Layar Monitor Absen Sholat · {{ config('app.name') }}</title>
    <link rel="icon" type="image/webp" href="{{ asset('images/logo-smkim4.webp') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800&display=swap">

    <style>
        :root {
            --bg-page: #f1f5f9;
            --surface: #ffffff;
            --surface-muted: #f8fafc;
            --border: #e2e8f0;
            --border-strong: #cbd5e1;
            --text-main: #0f172a;
            --text-sub: #475569;
            --text-muted: #64748b;
            --primary: #0f3d75;
            --primary-soft: #eff6ff;
            --success: #16a34a;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --warning: #d97706;
            --warning-bg: #fef3c7;
            --warning-text: #92400e;
            --danger-bg: #fee2e2;
            --danger-text: #991b1b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100vh;
            max-height: 100vh;
            margin: 0;
            padding: 0;
            background: var(--bg-page);
            color: var(--text-main);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            overflow: hidden;
        }

        [hidden],
        .kiosk-video-stage video[hidden],
        .kiosk-video-stage img[hidden] {
            display: none !important;
        }

        .kiosk-shell {
            height: 100vh;
            max-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 12px 16px;
            gap: 12px;
            box-sizing: border-box;
            overflow: hidden;
        }

        /* ================= HEADER BAR ================= */
        .kiosk-header {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        }

        .kiosk-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .kiosk-logo {
            width: 44px;
            height: 44px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .kiosk-brand-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.25;
        }

        .kiosk-brand-sub {
            font-size: 13px;
            color: var(--text-sub);
            margin-top: 2px;
        }

        .kiosk-session-box {
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--surface-muted);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 16px;
        }

        .kiosk-session-info {
            display: flex;
            flex-direction: column;
        }

        .kiosk-session-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
        }

        .kiosk-session-window {
            font-size: 12.5px;
            color: var(--text-sub);
        }

        .kiosk-session-count {
            background: var(--primary-soft);
            color: var(--primary);
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .kiosk-controls-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .kiosk-select-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .kiosk-select {
            padding: 8px 12px;
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text-main);
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            outline: none;
        }

        .kiosk-select:focus {
            border-color: var(--primary);
        }

        .kiosk-clock-box {
            text-align: right;
            min-width: 110px;
            padding-left: 8px;
            border-left: 1px solid var(--border);
        }

        .kiosk-clock-time {
            font-family: 'Montserrat', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.15;
            font-variant-numeric: tabular-nums;
        }

        .kiosk-clock-date {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .kiosk-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            border: 1px solid var(--border-strong);
            border-radius: 8px;
            background: var(--surface);
            color: var(--text-sub);
            font-size: 12.5px;
            font-weight: 600;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .kiosk-btn:hover {
            background: var(--surface-muted);
            color: var(--text-main);
        }

        /* ================= MAIN GRID (CAMERA LEFT, LOGS RIGHT) ================= */
        .kiosk-main {
            flex: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(320px, 1fr);
            gap: 12px;
            min-height: 0;
            height: 100%;
            overflow: hidden;
        }

        /* ---------- LEFT: CAMERA PANEL ---------- */
        .kiosk-camera-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            height: 100%;
            min-height: 0;
            overflow: hidden;
        }

        .kiosk-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-shrink: 0;
        }

        .kiosk-card-title {
            font-size: 15.5px;
            font-weight: 700;
            color: var(--text-main);
        }

        .kiosk-card-sub {
            font-size: 12.5px;
            color: var(--text-muted);
            margin-top: 1px;
        }

        .kiosk-video-stage {
            position: relative;
            width: 100%;
            flex: 1;
            min-height: 0;
            height: 100%;
            background: #020617;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #1e293b;
        }

        .kiosk-video-stage video,
        .kiosk-video-stage img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: inherit;
        }

        .kiosk-video-stage.is-contain video,
        .kiosk-video-stage.is-contain img {
            object-fit: contain;
        }

        .kiosk-overlay-canvas {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 4;
            border-radius: inherit;
        }

        .kiosk-stage-message {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 24px;
            text-align: center;
            color: #cbd5e1;
            background: rgba(15, 23, 42, 0.82);
            z-index: 5;
        }

        .kiosk-stage-message[hidden] {
            display: none !important;
        }

        /* Banner Sambutan Saat Jamaah Berhasil Absen */
        .kiosk-welcome-banner {
            background: var(--success-bg);
            border: 1px solid #86efac;
            color: var(--success-text);
            border-radius: 10px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 14.5px;
            font-weight: 700;
            flex-shrink: 0;
            transition: opacity 0.2s ease;
        }

        .kiosk-welcome-banner[hidden] {
            display: none !important;
        }

        .kiosk-footer-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 12.5px;
            color: var(--text-sub);
            flex-shrink: 0;
        }

        /* ---------- RIGHT: LOG PANEL ---------- */
        .kiosk-log-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
            height: 100%;
            min-height: 0;
            max-height: none;
            overflow: hidden;
        }

        .kiosk-log-list {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-right: 2px;
        }

        .kiosk-log-item {
            background: var(--surface-muted);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 11px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .kiosk-log-item.is-new {
            background: #f0fdf4;
            border-color: #86efac;
        }

        .kiosk-log-left {
            min-width: 0;
            flex: 1;
        }

        .kiosk-log-name {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .kiosk-log-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 3px;
            font-size: 13px;
            color: var(--text-sub);
        }

        .kiosk-log-prayer {
            font-weight: 600;
            color: var(--primary);
        }

        .kiosk-log-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            flex-shrink: 0;
        }

        .kiosk-log-time {
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            font-variant-numeric: tabular-nums;
        }

        .kiosk-status-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
        }

        .kiosk-status-tag.hadir {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .kiosk-status-tag.terlambat {
            background: var(--danger-bg);
            color: var(--danger-text);
        }

        .kiosk-empty-log {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
            padding: 32px 16px;
            border: 1px dashed var(--border-strong);
            border-radius: 10px;
        }

        @media (max-width: 1024px) {
            .kiosk-main {
                grid-template-columns: 1fr;
            }

            .kiosk-log-card {
                max-height: none;
            }

            .kiosk-video-stage {
                min-height: 320px;
            }
        }
    </style>
</head>

<body>
    @php
        $camerasConfig = $cameras->map(fn ($cam) => [
            'id' => $cam->id,
            'name' => $cam->name,
            'location' => $cam->location,
            'stream_type' => $cam->stream_type,
            'has_snapshot' => !empty($cam->snapshot_url),
            'stream_url' => route('kiosk.camera.stream', $cam),
            'snapshot_url' => route('kiosk.camera.snapshot', $cam),
            'scan_url' => route('kiosk.scan.camera', $cam),
            'webrtc_src' => 'camera_' . $cam->id,
        ])->values();
    @endphp

    <div class="kiosk-shell"
        id="kiosk-app"
        data-default-source="{{ $defaultSource }}"
        data-data-url="{{ route('kiosk.data') }}"
        data-webcam-scan-url="{{ route('kiosk.scan.webcam') }}"
        data-cameras='@json($camerasConfig)'
        data-initial-logs='@json($recentLogs)'>

        {{-- ================= HEADER ATAS ================= --}}
        <header class="kiosk-header">
            <div class="kiosk-brand">
                <img class="kiosk-logo" src="{{ asset('images/logo-smkim4.webp') }}" alt="Logo SMKIM4">
                <div>
                    <h1 class="kiosk-brand-title">Layar Presensi Sholat Jamaah</h1>
                    <p class="kiosk-brand-sub">SMK Istiqomah Muhammadiyah 4 Samarinda</p>
                </div>
            </div>

            <div class="kiosk-session-box" id="kiosk-session-box">
                <div class="kiosk-session-info">
                    <span class="kiosk-session-name" id="kiosk-session-label">{{ $sessionPayload['label'] }}</span>
                    <span class="kiosk-session-window" id="kiosk-session-window">{{ $sessionPayload['window'] }}</span>
                </div>
                <div class="kiosk-session-count" id="kiosk-session-count">
                    {{ $sessionPayload['total'] }} Hadir
                </div>
            </div>

            <div class="kiosk-controls-right">
                <div class="kiosk-select-group">
                    <label for="kiosk-source-select" style="font-size: 12.5px; font-weight: 600; color: var(--text-sub);">Kamera:</label>
                    <select id="kiosk-source-select" class="kiosk-select" aria-label="Pilih Sumber Kamera">
                        <option value="webcam">Webcam PC / Monitor Ini</option>
                        @foreach ($cameras as $cam)
                            <option value="cctv:{{ $cam->id }}">
                                CCTV: {{ $cam->name }}{{ $cam->location ? ' (' . $cam->location . ')' : '' }}
                            </option>
                        @endforeach
                    </select>

                    <select id="kiosk-webcam-device" class="kiosk-select" aria-label="Pilih Perangkat Webcam" hidden>
                        <option value="facing:user">Kamera Depan (Default)</option>
                        <option value="facing:environment">Kamera Belakang</option>
                    </select>
                </div>

                <button type="button" class="kiosk-btn" id="btn-kiosk-fit" title="Ubah Ukuran Tampilan Kamera (Isi Penuh / Proporsional)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"></path>
                    </svg>
                    <span id="btn-kiosk-fit-label">Isi Penuh</span>
                </button>

                <button type="button" class="kiosk-btn" id="btn-kiosk-fullscreen" title="Tampilan Layar Penuh">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                    </svg>
                    <span>Layar Penuh</span>
                </button>

                @if (auth()->user()->isAdmin() || auth()->user()->isPetugas())
                    <a href="{{ route('dashboard') }}" class="kiosk-btn" title="Kembali ke Panel Admin">
                        Panel Admin
                    </a>
                @else
                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="kiosk-btn" title="Keluar dari akun layar">Keluar</button>
                    </form>
                @endif

                <div class="kiosk-clock-box">
                    <div class="kiosk-clock-time" id="kiosk-clock-time">{{ now()->format('H:i:s') }}</div>
                    <div class="kiosk-clock-date" id="kiosk-clock-date">{{ now()->translatedFormat('l, d F Y') }}</div>
                </div>
            </div>
        </header>

        {{-- ================= AREA UTAMA (KIRI KAMERA, KANAN LOG) ================= --}}
        <main class="kiosk-main">
            {{-- KIRI: KAMERA & BORDER WAJAH --}}
            <section class="kiosk-camera-card" aria-label="Layar Kamera Pemindai Wajah">
                <div class="kiosk-card-head">
                    <div>
                        <h2 class="kiosk-card-title" id="kiosk-camera-heading">Pemindai Wajah Langsung</h2>
                        <p class="kiosk-card-sub" id="kiosk-scan-status">Menyiapkan kamera pemindai...</p>
                    </div>
                </div>

                <div class="kiosk-video-stage" id="kiosk-video-stage">
                    <video id="kiosk-video" playsinline autoplay muted></video>
                    <img id="kiosk-snapshot-img" alt="Siaran Kamera CCTV" hidden style="display: none;">
                    <canvas id="kiosk-capture-canvas" hidden></canvas>
                    <canvas id="kiosk-overlay-canvas" class="kiosk-overlay-canvas" aria-hidden="true"></canvas>

                    <div class="kiosk-stage-message" id="kiosk-stage-message" hidden>
                        <p id="kiosk-stage-message-text" style="font-size: 15px; font-weight: 600;">Menghubungkan ke kamera...</p>
                    </div>
                </div>

                <div class="kiosk-welcome-banner" id="kiosk-welcome-banner" hidden>
                    <span id="kiosk-welcome-text">Berhasil Absen</span>
                    <span id="kiosk-welcome-time" style="font-size: 13px; font-weight: 600;"></span>
                </div>

                <div class="kiosk-footer-bar">
                    <span>Berdiri menghadap kamera. Kotak hijau dan nama akan muncul otomatis saat wajah dikenali.</span>
                    <span id="kiosk-face-counter" style="font-weight: 600;">0 wajah di layar</span>
                </div>
            </section>

            {{-- KANAN: LOG KEHADIRAN TERBARU (NAMA, JAM, WAKTU SHOLAT) --}}
            <aside class="kiosk-log-card" aria-label="Daftar Kehadiran Terbaru">
                <div class="kiosk-card-head">
                    <div>
                        <h2 class="kiosk-card-title">Daftar Kehadiran Terbaru</h2>
                        <p class="kiosk-card-sub">Menampilkan nama jamaah, waktu sholat, dan jam presensi</p>
                    </div>
                </div>

                <div class="kiosk-log-list" id="kiosk-log-list">
                    {{-- Diisi secara dinamis oleh JavaScript --}}
                </div>
            </aside>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const appEl = document.getElementById('kiosk-app');
            if (!appEl) return;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const defaultSource = appEl.dataset.defaultSource || 'webcam';
            const dataUrl = appEl.dataset.dataUrl;
            const webcamScanUrl = appEl.dataset.webcamScanUrl;
            const cameras = JSON.parse(appEl.dataset.cameras || '[]');
            const initialLogs = JSON.parse(appEl.dataset.initialLogs || '[]');

            const sourceSelect = document.getElementById('kiosk-source-select');
            const webcamDeviceSelect = document.getElementById('kiosk-webcam-device');
            const videoEl = document.getElementById('kiosk-video');
            const snapshotImg = document.getElementById('kiosk-snapshot-img');
            const captureCanvas = document.getElementById('kiosk-capture-canvas');
            const overlayCanvas = document.getElementById('kiosk-overlay-canvas');
            const stageMessage = document.getElementById('kiosk-stage-message');
            const stageMessageText = document.getElementById('kiosk-stage-message-text');
            const cameraHeading = document.getElementById('kiosk-camera-heading');
            const scanStatusEl = document.getElementById('kiosk-scan-status');
            const faceCounterEl = document.getElementById('kiosk-face-counter');
            const logListEl = document.getElementById('kiosk-log-list');
            const welcomeBanner = document.getElementById('kiosk-welcome-banner');
            const welcomeText = document.getElementById('kiosk-welcome-text');
            const welcomeTime = document.getElementById('kiosk-welcome-time');

            const sessionLabelEl = document.getElementById('kiosk-session-label');
            const sessionWindowEl = document.getElementById('kiosk-session-window');
            const sessionCountEl = document.getElementById('kiosk-session-count');
            const clockTimeEl = document.getElementById('kiosk-clock-time');
            const clockDateEl = document.getElementById('kiosk-clock-date');
            const fullscreenBtn = document.getElementById('btn-kiosk-fullscreen');

            const STORAGE_SOURCE_KEY = 'absen_solat_kiosk_source';
            const STORAGE_WEBCAM_KEY = 'absen_solat_kiosk_webcam_device';
            const STORAGE_FIT_KEY = 'absen_solat_kiosk_fit_mode';

            const videoStageEl = document.getElementById('kiosk-video-stage');
            const fitBtn = document.getElementById('btn-kiosk-fit');
            const fitBtnLabel = document.getElementById('btn-kiosk-fit-label');

            let mediaStream = null;
            let rtcConnection = null;
            let mseSocket = null;
            let snapshotTimer = null;
            let scanTimer = null;
            let busyScanning = false;
            let isSnapshotMode = false;
            let currentFaces = [];
            let currentFrame = { width: 1280, height: 720 };
            let knownLogIds = new Set(initialLogs.map(l => Number(l.id)));
            let welcomeTimeout = null;
            let currentFitMode = localStorage.getItem(STORAGE_FIT_KEY) || 'cover';

            let activeSource = localStorage.getItem(STORAGE_SOURCE_KEY) || defaultSource;
            // Pastikan pilihan tersimpan masih valid
            if (activeSource.startsWith('cctv:')) {
                const camId = Number(activeSource.split(':')[1]);
                if (!cameras.some(c => c.id === camId)) {
                    activeSource = defaultSource;
                }
            }
            sourceSelect.value = activeSource;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            // ================= 1. JAM DIGITAL & WAKE LOCK =================
            setInterval(function () {
                const now = new Date();
                if (clockTimeEl) {
                    clockTimeEl.textContent =
                        String(now.getHours()).padStart(2, '0') + ':' +
                        String(now.getMinutes()).padStart(2, '0') + ':' +
                        String(now.getSeconds()).padStart(2, '0');
                }
            }, 1000);

            async function requestWakeLock() {
                if ('wakeLock' in navigator) {
                    try {
                        await navigator.wakeLock.request('screen');
                    } catch (e) {
                        // Abaikan bila peramban menolak
                    }
                }
            }
            requestWakeLock();
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') {
                    requestWakeLock();
                }
            });

            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', function () {
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen?.();
                    } else {
                        document.exitFullscreen?.();
                    }
                });
            }

            // ================= 2. DAFTAR LOG KEHADIRAN (NAMA, JAM, WAKTU SHOLAT) =================
            function renderLogs(logs, highlightNew = false) {
                if (!logListEl) return;

                if (!Array.isArray(logs) || logs.length === 0) {
                    logListEl.innerHTML = '<div class="kiosk-empty-log">Belum ada catatan kehadiran pada sesi ini.</div>';
                    return;
                }

                let newestLog = null;
                const html = logs.map(function (log) {
                    const id = Number(log.id || 0);
                    const isNew = highlightNew && id > 0 && !knownLogIds.has(id);
                    if (id > 0) {
                        knownLogIds.add(id);
                    }
                    if (isNew && !newestLog) {
                        newestLog = log;
                    }

                    const safeName = escapeHtml(log.name || 'Jamaah');
                    const safePrayer = escapeHtml(log.prayer || 'Sholat');
                    const safeTime = escapeHtml(log.time || '--:--:--');
                    const safeStatus = escapeHtml(log.status || 'Hadir');
                    const statusClass = (log.status === 'Terlambat') ? 'terlambat' : 'hadir';

                    return '<div class="kiosk-log-item' + (isNew ? ' is-new' : '') + '">' +
                        '<div class="kiosk-log-left">' +
                            '<div class="kiosk-log-name">' + safeName + '</div>' +
                            '<div class="kiosk-log-meta">' +
                                '<span class="kiosk-log-prayer">Sholat ' + safePrayer + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="kiosk-log-right">' +
                            '<span class="kiosk-log-time">' + safeTime + '</span>' +
                            '<span class="kiosk-status-tag ' + statusClass + '">' + safeStatus + '</span>' +
                        '</div>' +
                    '</div>';
                }).join('');

                logListEl.innerHTML = html;

                if (newestLog) {
                    showWelcomeBanner(newestLog.name, newestLog.prayer, newestLog.time, newestLog.status);
                }
            }

            function showWelcomeBanner(name, prayer, time, status) {
                if (!welcomeBanner || !welcomeText || !welcomeTime) return;
                welcomeText.textContent = 'Berhasil Absen: ' + (name || 'Jamaah') + ' — Sholat ' + (prayer || '');
                welcomeTime.textContent = (time || '') + ' · ' + (status || 'Hadir');
                welcomeBanner.hidden = false;

                if (welcomeTimeout) clearTimeout(welcomeTimeout);
                welcomeTimeout = setTimeout(function () {
                    welcomeBanner.hidden = true;
                }, 4500);
            }

            renderLogs(initialLogs, false);

            // ================= 3. GAMBAR BORDER WAJAH & NAMA DI KANVAS =================
            function clearOverlay() {
                if (!overlayCanvas) return;
                const ctx = overlayCanvas.getContext('2d');
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.clearRect(0, 0, overlayCanvas.width, overlayCanvas.height);
                if (faceCounterEl) faceCounterEl.textContent = '0 wajah di layar';
            }

            function getMediaMapping(mediaEl, boxW, boxH, frame, fitMode = 'cover') {
                const srcW = (frame && frame.width > 0) ? frame.width : ((mediaEl && (mediaEl.videoWidth || mediaEl.naturalWidth)) || 1280);
                const srcH = (frame && frame.height > 0) ? frame.height : ((mediaEl && (mediaEl.videoHeight || mediaEl.naturalHeight)) || 720);
                const contentW = (mediaEl && (mediaEl.videoWidth || mediaEl.naturalWidth)) || srcW;
                const contentH = (mediaEl && (mediaEl.videoHeight || mediaEl.naturalHeight)) || srcH;

                const scale = (fitMode === 'contain')
                    ? Math.min(boxW / contentW, boxH / contentH)
                    : Math.max(boxW / contentW, boxH / contentH);

                return {
                    offsetX: (boxW - contentW * scale) / 2,
                    offsetY: (boxH - contentH * scale) / 2,
                    scaleX: (contentW / srcW) * scale,
                    scaleY: (contentH / srcH) * scale,
                };
            }

            function drawOverlayFaces(faces, frame) {
                if (!overlayCanvas || !frame) return;

                const rect = overlayCanvas.getBoundingClientRect();
                const displayW = rect.width || overlayCanvas.clientWidth || 640;
                const displayH = rect.height || overlayCanvas.clientHeight || 360;
                const ratio = window.devicePixelRatio || 1;

                if (displayW === 0 || displayH === 0) return;

                overlayCanvas.width = Math.round(displayW * ratio);
                overlayCanvas.height = Math.round(displayH * ratio);

                const ctx = overlayCanvas.getContext('2d');
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.clearRect(0, 0, displayW, displayH);

                const count = Array.isArray(faces) ? faces.length : 0;
                if (faceCounterEl) {
                    faceCounterEl.textContent = count + ' wajah di layar';
                }

                if (!faces || count === 0) return;

                const activeMedia = (!isSnapshotMode && !videoEl.hidden) ? videoEl : snapshotImg;
                const map = getMediaMapping(activeMedia, displayW, displayH, frame, currentFitMode);

                const colorSaved = '#16a34a';
                const colorUnknown = '#d97706';

                faces.forEach(function (face) {
                    if (!face.box) return;

                    const bx = map.offsetX + Number(face.box.left || 0) * map.scaleX;
                    const by = map.offsetY + Number(face.box.top || 0) * map.scaleY;
                    const bw = Math.max(2, (Number(face.box.right || 0) - Number(face.box.left || 0)) * map.scaleX);
                    const bh = Math.max(2, (Number(face.box.bottom || 0) - Number(face.box.top || 0)) * map.scaleY);

                    const status = String((face.attendance && face.attendance.status) || face.status || '');
                    const isRecognized = Boolean(
                        (face.attendance && face.attendance.recorded) ||
                        status === 'recorded' ||
                        status === 'late' ||
                        status === 'duplicate' ||
                        status === 'cooldown' ||
                        face.member
                    );
                    const color = isRecognized ? colorSaved : colorUnknown;

                    // Gambar border wajah
                    ctx.lineWidth = 3;
                    ctx.strokeStyle = color;
                    ctx.strokeRect(bx, by, bw, bh);

                    // Nama orang & status di atas border
                    const nama = (typeof face.label === 'string' && face.label !== '')
                        ? face.label
                        : ((face.member && face.member.name) ? face.member.name : 'Tidak dikenal');
                    const keadaan = (face.attendance && typeof face.attendance.display_message === 'string' && face.attendance.display_message !== '')
                        ? face.attendance.display_message
                        : (typeof face.display_message === 'string' && face.display_message !== '' ? face.display_message : 'Belum terdaftar');

                    ctx.font = '700 14px Inter, system-ui, sans-serif';
                    const nameW = ctx.measureText(nama).width;

                    ctx.font = '600 12px Inter, system-ui, sans-serif';
                    const statusW = ctx.measureText(keadaan).width;

                    const labelW = Math.min(displayW, Math.max(nameW, statusW) + 18);
                    const labelH = 36;

                    const labelX = Math.max(0, Math.min(displayW - labelW, bx));
                    const labelY = (by - labelH < 0) ? by : (by - labelH);

                    ctx.fillStyle = color;
                    ctx.fillRect(labelX, labelY, labelW, labelH);

                    ctx.fillStyle = '#ffffff';
                    ctx.font = '700 14px Inter, system-ui, sans-serif';
                    ctx.fillText(nama, labelX + 9, labelY + 15, labelW - 18);

                    ctx.font = '600 12px Inter, system-ui, sans-serif';
                    ctx.fillText(keadaan, labelX + 9, labelY + 30, labelW - 18);
                });
            }

            window.addEventListener('resize', function () {
                if (currentFaces.length > 0 && currentFrame) {
                    drawOverlayFaces(currentFaces, currentFrame);
                }
            });

            function applyFitMode(mode) {
                currentFitMode = (mode === 'contain') ? 'contain' : 'cover';
                localStorage.setItem(STORAGE_FIT_KEY, currentFitMode);
                if (videoStageEl) {
                    if (currentFitMode === 'contain') {
                        videoStageEl.classList.add('is-contain');
                    } else {
                        videoStageEl.classList.remove('is-contain');
                    }
                }
                if (fitBtnLabel) {
                    fitBtnLabel.textContent = (currentFitMode === 'cover') ? 'Isi Penuh' : 'Proporsional';
                }
                if (currentFaces.length > 0 && currentFrame) {
                    drawOverlayFaces(currentFaces, currentFrame);
                }
            }

            if (fitBtn) {
                fitBtn.addEventListener('click', function () {
                    applyFitMode(currentFitMode === 'cover' ? 'contain' : 'cover');
                });
            }
            applyFitMode(currentFitMode);

            // ================= 4. MANAJEMEN SUMBER KAMERA (WEBCAM PC ATAU CCTV) =================
            function stopAllStreams() {
                if (scanTimer) {
                    clearInterval(scanTimer);
                    scanTimer = null;
                }
                if (snapshotTimer) {
                    clearInterval(snapshotTimer);
                    snapshotTimer = null;
                }
                if (rtcConnection) {
                    try { rtcConnection.close(); } catch (e) {}
                    rtcConnection = null;
                }
                if (mseSocket) {
                    try { mseSocket.close(); } catch (e) {}
                    mseSocket = null;
                }
                if (mediaStream) {
                    mediaStream.getTracks().forEach(t => t.stop());
                    mediaStream = null;
                }
                videoEl.pause();
                videoEl.srcObject = null;
                videoEl.removeAttribute('src');
                snapshotImg.hidden = true;
                snapshotImg.style.display = 'none';
                snapshotImg.removeAttribute('src');
                currentFaces = [];
                clearOverlay();
            }

            async function populateWebcamDevices() {
                if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const cams = devices.filter(d => d.kind === 'videoinput' && d.deviceId);
                    if (cams.length === 0) return;

                    const savedDevice = localStorage.getItem(STORAGE_WEBCAM_KEY) || '';
                    webcamDeviceSelect.innerHTML = '';

                    cams.forEach(function (cam, idx) {
                        const opt = document.createElement('option');
                        opt.value = 'device:' + cam.deviceId;
                        opt.textContent = cam.label || ('Webcam ' + (idx + 1));
                        webcamDeviceSelect.appendChild(opt);
                    });

                    if (savedDevice && Array.from(webcamDeviceSelect.options).some(o => o.value === savedDevice)) {
                        webcamDeviceSelect.value = savedDevice;
                    }
                } catch (e) {
                    // Abaikan bila gagal membaca daftar perangkat
                }
            }

            async function startWebcamMode() {
                stopAllStreams();
                isSnapshotMode = false;
                webcamDeviceSelect.hidden = false;
                snapshotImg.hidden = true;
                snapshotImg.style.display = 'none';
                snapshotImg.removeAttribute('src');
                videoEl.hidden = false;
                videoEl.style.display = 'block';
                cameraHeading.textContent = 'Pemindai Wajah — Webcam PC / Monitor';
                scanStatusEl.textContent = 'Menyalakan webcam PC...';
                stageMessage.hidden = false;
                stageMessageText.textContent = 'Menyalakan webcam PC...';

                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    stageMessageText.textContent = 'Browser ini tidak mendukung akses webcam langsung.';
                    return;
                }

                const choice = localStorage.getItem(STORAGE_WEBCAM_KEY) || webcamDeviceSelect.value || 'facing:user';
                const videoConstraint = {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                };

                if (choice.startsWith('device:')) {
                    videoConstraint.deviceId = { exact: choice.slice(7) };
                } else {
                    videoConstraint.facingMode = { ideal: choice.slice(7) || 'user' };
                }

                try {
                    try {
                        mediaStream = await navigator.mediaDevices.getUserMedia({ video: videoConstraint, audio: false });
                    } catch (firstErr) {
                        mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                    }

                    await populateWebcamDevices();
                    videoEl.srcObject = mediaStream;
                    await videoEl.play();

                    stageMessage.hidden = true;
                    scanStatusEl.textContent = 'Pemindai aktif — memeriksa wajah otomatis setiap 2 detik';

                    scanTimer = setInterval(function () {
                        captureAndScan(webcamScanUrl);
                    }, 2000);
                } catch (err) {
                    stageMessage.hidden = false;
                    stageMessageText.textContent = 'Gagal mengakses webcam PC. Pastikan izin kamera diizinkan di browser.';
                    scanStatusEl.textContent = 'Webcam belum dapat diakses.';
                }
            }

            function startCctvMode(cameraId) {
                stopAllStreams();
                webcamDeviceSelect.hidden = true;

                const cam = cameras.find(c => Number(c.id) === Number(cameraId));
                if (!cam) {
                    startWebcamMode();
                    return;
                }

                cameraHeading.textContent = 'Pemindai Wajah — CCTV ' + cam.name;
                scanStatusEl.textContent = 'Menghubungkan ke siaran CCTV ' + cam.name + '...';
                stageMessage.hidden = false;
                stageMessageText.textContent = 'Menghubungkan ke CCTV ' + cam.name + '...';

                if (cam.stream_type === 'rtsp') {
                    startGo2RtcStream(cam);
                } else if (cam.has_snapshot) {
                    startSnapshotPolling(cam);
                } else {
                    isSnapshotMode = true;
                    videoEl.hidden = true;
                    videoEl.style.display = 'none';
                    snapshotImg.hidden = false;
                    snapshotImg.style.display = 'block';
                    snapshotImg.onload = function () {
                        stageMessage.hidden = true;
                    };
                    snapshotImg.src = cam.stream_url;
                }

                scanStatusEl.textContent = 'Pemindai CCTV aktif — memeriksa wajah setiap 2 detik';
                scanTimer = setInterval(function () {
                    captureAndScan(cam.scan_url, cam.snapshot_url);
                }, 2000);
            }

            function startSnapshotPolling(cam) {
                isSnapshotMode = true;
                videoEl.hidden = true;
                videoEl.style.display = 'none';
                snapshotImg.hidden = false;
                snapshotImg.style.display = 'block';

                function refreshSnap() {
                    const sep = cam.snapshot_url.includes('?') ? '&' : '?';
                    snapshotImg.onload = function () {
                        stageMessage.hidden = true;
                    };
                    snapshotImg.src = cam.snapshot_url + sep + 't=' + Date.now();
                }

                refreshSnap();
                snapshotTimer = setInterval(refreshSnap, 1800);
            }

            function buildGo2RtcWsUrl(srcName) {
                const wsProto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                return wsProto + '//' + window.location.host + '/go2rtc/ws?src=' + encodeURIComponent(srcName);
            }

            function startGo2RtcStream(cam) {
                isSnapshotMode = false;
                snapshotImg.hidden = true;
                snapshotImg.style.display = 'none';
                snapshotImg.removeAttribute('src');
                videoEl.hidden = false;
                videoEl.style.display = 'block';

                const wsUrl = buildGo2RtcWsUrl(cam.webrtc_src);

                if ('MediaSource' in window) {
                    startMseStream(wsUrl, cam);
                } else if ('RTCPeerConnection' in window) {
                    startWebRtcStream(wsUrl, cam);
                } else {
                    startSnapshotPolling(cam);
                }
            }

            function startMseStream(wsUrl, cam) {
                let connected = false;
                const mediaSource = new MediaSource();
                videoEl.src = URL.createObjectURL(mediaSource);

                const fallbackTimer = setTimeout(function () {
                    if (!connected) {
                        if (mseSocket) {
                            try { mseSocket.close(); } catch (e) {}
                        }
                        startSnapshotPolling(cam);
                    }
                }, 8000);

                mediaSource.addEventListener('sourceopen', function () {
                    mseSocket = new WebSocket(wsUrl);
                    mseSocket.binaryType = 'arraybuffer';
                    let sourceBuffer = null;
                    const queue = [];

                    mseSocket.onopen = function () {
                        mseSocket.send(JSON.stringify({
                            type: 'mse',
                            value: 'avc1.640029,avc1.64002A,avc1.640033,hvc1.1.6.L153.B0,mp4a.40.2,mp4a.40.5,flac,opus'
                        }));
                    };

                    mseSocket.onmessage = function (ev) {
                        if (typeof ev.data === 'string') {
                            try {
                                const msg = JSON.parse(ev.data);
                                if (msg.type === 'mse' && msg.value && !sourceBuffer) {
                                    sourceBuffer = mediaSource.addSourceBuffer(msg.value);
                                    sourceBuffer.mode = 'segments';
                                    sourceBuffer.addEventListener('updateend', function () {
                                        if (queue.length > 0 && !sourceBuffer.updating) {
                                            try {
                                                sourceBuffer.appendBuffer(queue.shift());
                                            } catch (e) {}
                                        }
                                    });
                                }
                            } catch (e) {}
                        } else if (ev.data instanceof ArrayBuffer) {
                            if (!connected) {
                                connected = true;
                                clearTimeout(fallbackTimer);
                                stageMessage.hidden = true;
                                videoEl.play().catch(function () {});
                            }
                            if (sourceBuffer) {
                                if (sourceBuffer.updating || queue.length > 0) {
                                    queue.push(ev.data);
                                } else {
                                    try {
                                        sourceBuffer.appendBuffer(ev.data);
                                    } catch (e) {}
                                }
                            }
                        }
                    };

                    mseSocket.onerror = function () {
                        if (!connected) {
                            clearTimeout(fallbackTimer);
                            startSnapshotPolling(cam);
                        }
                    };
                });
            }

            function startWebRtcStream(wsUrl, cam) {
                let connected = false;
                rtcConnection = new RTCPeerConnection({
                    iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
                });

                const ws = new WebSocket(wsUrl);

                rtcConnection.addTransceiver('video', { direction: 'recvonly' });
                rtcConnection.ontrack = function (event) {
                    if (event.streams && event.streams[0]) {
                        connected = true;
                        stageMessage.hidden = true;
                        videoEl.srcObject = event.streams[0];
                        videoEl.play().catch(function () {});
                    }
                };

                ws.onopen = async function () {
                    const offer = await rtcConnection.createOffer();
                    await rtcConnection.setLocalDescription(offer);
                    ws.send(JSON.stringify({ type: 'webrtc/offer', value: offer.sdp }));
                };

                ws.onmessage = async function (ev) {
                    try {
                        const msg = JSON.parse(ev.data);
                        if (msg.type === 'webrtc/answer' && msg.value) {
                            await rtcConnection.setRemoteDescription({ type: 'answer', sdp: msg.value });
                        } else if (msg.type === 'webrtc/candidate' && msg.value) {
                            await rtcConnection.addIceCandidate({ candidate: msg.value, sdpMid: '0' });
                        }
                    } catch (e) {}
                };

                setTimeout(function () {
                    if (!connected) {
                        startSnapshotPolling(cam);
                    }
                }, 8000);
            }

            // ================= 5. PEMINDAIAN WAJAH OTOMATIS =================
            async function captureAndScan(endpointUrl, fallbackSnapUrl = null) {
                if (busyScanning || !endpointUrl) return;
                busyScanning = true;

                try {
                    const ctx = captureCanvas.getContext('2d');
                    let targetW = 0;
                    let targetH = 0;
                    let blob = null;

                    if (!isSnapshotMode && !videoEl.hidden && videoEl.videoWidth > 0 && videoEl.videoHeight > 0) {
                        const scale = Math.min(1, 1280 / Math.max(videoEl.videoWidth, videoEl.videoHeight));
                        targetW = Math.round(videoEl.videoWidth * scale);
                        targetH = Math.round(videoEl.videoHeight * scale);
                        captureCanvas.width = targetW;
                        captureCanvas.height = targetH;
                        ctx.drawImage(videoEl, 0, 0, targetW, targetH);
                        blob = await new Promise(resolve => captureCanvas.toBlob(resolve, 'image/jpeg', 0.85));
                    } else if (!snapshotImg.hidden && snapshotImg.complete && snapshotImg.naturalWidth > 0) {
                        const scale = Math.min(1, 1280 / Math.max(snapshotImg.naturalWidth, snapshotImg.naturalHeight));
                        targetW = Math.round(snapshotImg.naturalWidth * scale);
                        targetH = Math.round(snapshotImg.naturalHeight * scale);
                        captureCanvas.width = targetW;
                        captureCanvas.height = targetH;
                        ctx.drawImage(snapshotImg, 0, 0, targetW, targetH);
                        blob = await new Promise(resolve => captureCanvas.toBlob(resolve, 'image/jpeg', 0.85));
                    } else if (fallbackSnapUrl) {
                        const snapRes = await fetch(fallbackSnapUrl, { credentials: 'same-origin' }).catch(() => null);
                        if (snapRes && snapRes.ok) {
                            blob = await snapRes.blob();
                        }
                    }

                    if (!blob) {
                        busyScanning = false;
                        return;
                    }

                    const formData = new FormData();
                    formData.append('photo', blob, 'kiosk_frame.jpg');

                    const res = await fetch(endpointUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: formData,
                    });

                    const data = await res.json().catch(() => null);
                    if (data && Array.isArray(data.faces)) {
                        currentFaces = data.faces;
                        currentFrame = {
                            width: Number(data.width || targetW || 1280),
                            height: Number(data.height || targetH || 720),
                        };
                        drawOverlayFaces(currentFaces, currentFrame);

                        if (data.session_summary) {
                            updateSessionHeader(data.session_summary);
                        }

                        // Cek bila ada wajah yang baru tercatat pada putaran ini
                        let anyRecorded = false;
                        data.faces.forEach(function (face) {
                            if (face.attendance && face.attendance.recorded && face.member) {
                                anyRecorded = true;
                                showWelcomeBanner(
                                    face.member.name,
                                    face.attendance.prayer || 'Sholat',
                                    new Date().toTimeString().slice(0, 8),
                                    face.attendance.status === 'late' ? 'Terlambat' : 'Hadir'
                                );
                            }
                        });

                        if (anyRecorded) {
                            pollLiveStatus();
                        }
                    } else {
                        clearOverlay();
                    }
                } catch (e) {
                    // Abaikan gangguan jaringan satu putaran
                } finally {
                    busyScanning = false;
                }
            }

            // ================= 6. POLLING LOG & STATUS SESI =================
            function updateSessionHeader(session) {
                if (!session) return;
                if (sessionLabelEl) sessionLabelEl.textContent = session.label || 'Belum Ada Sesi Dibuka';
                if (sessionWindowEl) sessionWindowEl.textContent = session.window || '';
                if (sessionCountEl) sessionCountEl.textContent = Number(session.total || 0) + ' Hadir';
            }

            async function pollLiveStatus() {
                if (!dataUrl) return;
                try {
                    let url = dataUrl;
                    if (activeSource.startsWith('cctv:')) {
                        const camId = activeSource.split(':')[1];
                        url += '?camera_id=' + encodeURIComponent(camId);
                    }

                    const res = await fetch(url, { credentials: 'same-origin' });
                    if (!res.ok) return;
                    const data = await res.json();

                    if (data.server_date && clockDateEl) {
                        clockDateEl.textContent = data.server_date;
                    }
                    if (data.session) {
                        updateSessionHeader(data.session);
                    }
                    if (Array.isArray(data.logs)) {
                        renderLogs(data.logs, true);
                    }
                    if (activeSource.startsWith('cctv:') && data.last_scan && Array.isArray(data.last_scan.faces) && !busyScanning) {
                        currentFaces = data.last_scan.faces;
                        currentFrame = {
                            width: Number(data.last_scan.width || 1920),
                            height: Number(data.last_scan.height || 1080),
                        };
                        drawOverlayFaces(currentFaces, currentFrame);
                    }
                } catch (e) {
                    // Abaikan bila gagal polling sementara
                }
            }

            setInterval(pollLiveStatus, 2500);

            // ================= 7. EVENT GANTI SUMBER KAMERA =================
            function activateSelectedSource(sourceVal) {
                activeSource = sourceVal || 'webcam';
                localStorage.setItem(STORAGE_SOURCE_KEY, activeSource);

                if (activeSource.startsWith('cctv:')) {
                    const camId = Number(activeSource.split(':')[1]);
                    startCctvMode(camId);
                } else {
                    startWebcamMode();
                }
            }

            sourceSelect.addEventListener('change', function () {
                activateSelectedSource(sourceSelect.value);
            });

            webcamDeviceSelect.addEventListener('change', function () {
                localStorage.setItem(STORAGE_WEBCAM_KEY, webcamDeviceSelect.value);
                if (activeSource === 'webcam') {
                    startWebcamMode();
                }
            });

            // Jalankan otomatis saat halaman dibuka
            activateSelectedSource(activeSource);
        });
    </script>
</body>

</html>

