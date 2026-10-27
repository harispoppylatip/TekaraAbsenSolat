@extends('layouts.app')

@section('title', 'Kamera CCTV')
@section('note', 'Pusat pemantauan aktivitas CCTV dan pemindai absensi mandiri')

@section('content')
    <div>
        <section class="stats-grid cctv-stats-grid">
            <div class="stat-card">
                <p class="stat-label">Total CCTV</p>
                <p class="stat-value">{{ $stats['total'] }}</p>
                <p class="stat-note">{{ $stats['aktif'] }} sedang aktif</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Web Stream</p>
                <p class="stat-value">{{ $stats['web_stream'] }}</p>
                <p class="stat-note">Siaran web / media</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">RTSP CCTV</p>
                <p class="stat-value">{{ $stats['rtsp'] }}</p>
                <p class="stat-note">Kamera IP / NVR</p>
            </div>
        </section>

        {{-- Layar Pemantau CCTV & Pemindai Absen --}}
        @if ($selectedCamera)
            <div class="scan-layout" style="margin-top: 8px;">
                <section class="card">
                    <div class="section-head cctv-section-head">
                        <div>
                            <h2 class="section-title">Pemantau CCTV: {{ $selectedCamera->name }}</h2>
                            <p class="section-note">
                                {{ $selectedCamera->typeLabel() }} &mdash; {{ $selectedCamera->location ?: 'Lokasi belum diatur' }}
                            </p>
                        </div>

                        @if ($cameras->count() > 1)
                            <div class="field" style="margin-bottom: 0;">
                                <label class="field-label" for="select-pantau-kamera" style="display: none;">Pilih CCTV</label>
                                <select class="select-field" id="select-pantau-kamera" onchange="window.location.href = this.value">
                                    @foreach ($cameras as $cam)
                                        <option value="{{ route('cameras.index', ['kamera' => $cam->id]) }}" @selected($selectedCamera->id === $cam->id)>
                                            {{ $cam->name }} ({{ $cam->isRtsp() ? 'RTSP' : 'Web Stream' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    @if ($isWorkerActive)
                        <div class="alert alert-success" style="margin-bottom: 12px; padding: 10px 14px;">
                            <strong style="display: block;">Pemindai server latar belakang sedang berjalan</strong>
                        </div>
                    @endif

                    <div id="cctv-monitor-box"
                        data-camera-id="{{ $selectedCamera->id }}"
                        data-camera-type="{{ $selectedCamera->stream_type }}"
                        data-stream-url="{{ route('cameras.stream', $selectedCamera) }}"
                        data-snapshot-url="{{ route('cameras.snapshot', $selectedCamera) }}"
                        data-scan-endpoint="{{ route('cameras.scan', $selectedCamera) }}"
                        data-logs-url="{{ route('cameras.logs', $selectedCamera) }}">

                        <div class="camera-frame cctv-frame" style="position: relative; background: #0b0f19; aspect-ratio: 16 / 9; width: 100%; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 8px;">
                            <video id="cctv-video" playsinline muted autoplay style="width: 100%; height: 100%; object-fit: contain; display: none;"></video>
                            <img id="cctv-img" alt="Siaran CCTV" style="width: 100%; height: 100%; object-fit: contain; display: none;">
                            <canvas id="cctv-canvas" style="display: none;"></canvas>
                            <canvas id="cctv-overlay" class="camera-overlay" style="display: block; position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 2;" aria-hidden="true"></canvas>

                            <div id="cctv-placeholder" style="color: #94a3b8; text-align: center; padding: 24px;">
                                <p style="font-size: 15px; font-weight: 500;">Menghubungkan ke siaran CCTV...</p>
                                <p style="font-size: 13px; margin-top: 4px; color: #64748b;">Pastikan URL siaran dan jaringan kamera aktif.</p>
                            </div>
                        </div>

                        <div class="camera-controls" style="margin-top: 14px;">
                            <div class="form-actions cctv-controls-actions" style="margin-top: 0; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                <button class="btn btn-primary" type="button" id="btn-toggle-scanner">
                                    Aktifkan pemindai wajah
                                </button>
                                <button class="btn btn-outline" type="button" id="btn-refresh-stream">
                                    Muat ulang siaran
                                </button>
                                <a href="{{ route('cameras.edit', $selectedCamera) }}" class="btn btn-secondary">
                                    Ubah pengaturan CCTV
                                </a>
                            </div>
                        </div>

                        <p class="camera-status" id="cctv-status-text" style="margin-top: 12px; font-size: 13px; color: var(--color-muted, #64748b);">
                            Siaran aktif untuk pemantauan. Pemindai wajah sedang nonaktif.
                        </p>
                    </div>
                </section>

                <div class="scan-side">
                    <section class="card">
                        <div class="section-head">
                            <div>
                                <h2 class="section-title">Log pemindaian CCTV</h2>
                                <p class="section-note">Catatan wajah jamaah yang berhasil diidentifikasi dari CCTV ini.</p>
                            </div>
                        </div>

                        <div id="cctv-log-list" class="log-list">
                            @if ($recent->isEmpty())
                                <p class="empty-state" id="cctv-empty-log">Belum ada catatan wajah terdeteksi.</p>
                            @else
                                @foreach ($recent as $log)
                                    @php
                                        $identity = $log->user?->class_name ?: $log->user?->roleLabel();
                                    @endphp
                                    <div class="log-row">
                                        @if ($log->isFingerprint())
                                            <div class="proof-thumb proof-thumb-fingerprint" title="Absen via sidik jari{{ $log->device_id ? ' (' . $log->device_id . ')' : '' }}" aria-label="Absen via sidik jari" style="width: 44px; height: 44px; min-width: 44px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; flex-shrink: 0;">
                                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 3"></path>
                                                    <path d="M14 13.12c0 2.38 0 6.38-1 8.88"></path>
                                                    <path d="M2 16h.01"></path>
                                                    <path d="M21.8 16c.2-2 .131-5.354 0-6"></path>
                                                    <path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2"></path>
                                                    <path d="M8.65 22c.21-.66.45-1.32.57-2"></path>
                                                    <path d="M9 6.8a6 6 0 0 1 9 5.2v2"></path>
                                                </svg>
                                            </div>
                                        @else
                                            @include('partials.proof-photo', ['log' => $log])
                                        @endif

                                        <div class="log-body">
                                            <div class="log-line">
                                                <span class="log-name">{{ $log->user?->name ?? 'Anggota' }}</span>
                                                <span class="log-time">{{ $log->scanned_at?->format('H:i:s') }}</span>
                                            </div>

                                            <div class="log-line log-line-meta">
                                                <span class="log-sub">
                                                    <span>{{ $log->session?->prayerLabel() ?? 'Uji Coba' }}</span>
                                                    @if ($identity)
                                                        <span>{{ $identity }}</span>
                                                    @endif
                                                    @if ($log->isFingerprint())
                                                        <span style="color: #0284c7; font-weight: 600;">(Sidik Jari)</span>
                                                    @endif
                                                </span>
                                                <span class="status {{ \App\Support\Status::log($log->status) }}">{{ $log->statusLabel() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </section>
                </div>
            </div>
        @else
            <div class="alert alert-info" style="margin-top: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <strong>Belum ada kamera CCTV terdaftar</strong>
                    <p style="margin: 4px 0 0;">Tambahkan siaran kamera CCTV baru untuk mulai memantau dan memindai kehadiran jamaah.</p>
                </div>
                <button class="btn btn-primary btn-trigger-tambah-cctv" type="button">
                    + Tambah Kamera CCTV
                </button>
            </div>
        @endif

        {{-- Tabel Daftar Kamera CCTV --}}
        <section class="card" style="margin-top: 24px;">
            <div class="section-head" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 class="section-title">Daftar kamera CCTV terdaftar</h2>
                    <p class="section-note">
                        Pilih kamera untuk dipantau siarannya atau mengaktifkan pemindai wajah.
                    </p>
                </div>
                <button class="btn btn-primary btn-trigger-tambah-cctv" type="button">
                    + Tambah Kamera CCTV
                </button>
            </div>

            @if ($cameras->isEmpty())
                <p class="empty-state">Belum ada kamera CCTV yang didaftarkan.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nama kamera</th>
                                <th>Jenis</th>
                                <th>Lokasi</th>
                                <th>Status</th>
                                <th>URL Siaran</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cameras as $cam)
                                <tr>
                                    <td class="cell-strong">{{ $cam->name }}</td>
                                    <td>{{ $cam->typeLabel() }}</td>
                                    <td>{{ $cam->location ?: '—' }}</td>
                                    <td>
                                        <span class="status {{ $cam->is_active ? 'status-hadir' : 'status-terlambat' }}">
                                            {{ $cam->statusLabel() }}
                                        </span>
                                    </td>
                                    <td>
                                        <code style="font-size: 11px; word-break: break-all; max-width: 260px; display: inline-block;">
                                            {{ Str::limit($cam->stream_url, 40) }}
                                        </code>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="table-actions" style="display: inline-flex; gap: 8px; justify-content: flex-end;">
                                            <a class="btn btn-small {{ $selectedCamera && $selectedCamera->id === $cam->id ? 'btn-primary' : 'btn-outline' }}"
                                                href="{{ route('cameras.index', ['kamera' => $cam->id]) }}">
                                                Pantau kamera
                                            </a>

                                            <form method="POST" action="{{ route('cameras.status', $cam) }}">
                                                @csrf
                                                @method('PUT')
                                                <button class="btn btn-small btn-outline" type="submit">
                                                    {{ $cam->is_active ? 'Matikan' : 'Nyalakan' }}
                                                </button>
                                            </form>

                                            <a class="btn btn-small btn-outline" href="{{ route('cameras.edit', $cam) }}">Ubah</a>

                                            <form method="POST" action="{{ route('cameras.destroy', $cam) }}"
                                                data-confirm="Hapus kamera {{ $cam->name }} dari daftar?">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-small btn-danger" type="submit">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Modal Dialog: Tambah Kamera CCTV Baru --}}
        <div id="modal-tambah-cctv" class="cctv-modal-backdrop" style="display: none;" aria-hidden="true">
            <div class="cctv-modal-card" role="dialog" aria-modal="true" aria-labelledby="modal-cctv-title">
                <div class="cctv-modal-header">
                    <div>
                        <h2 id="modal-cctv-title" class="section-title" style="margin-bottom: 2px;">Tambah kamera CCTV baru</h2>
                        <p class="section-note">
                            Tambahkan kamera siaran CCTV (RTSP atau Web Stream) untuk dipantau dan dijadikan pemindai wajah.
                        </p>
                    </div>
                    <button type="button" class="cctv-modal-close" id="btn-tutup-modal" aria-label="Tutup dialog">&times;</button>
                </div>

                <form method="POST" action="{{ route('cameras.store') }}" id="form-tambah-kamera">
                    @csrf

                    <div class="form-grid" style="margin-top: 16px;">
                        <div class="field">
                            <label class="field-label" for="cam-name">Nama kamera</label>
                            <input class="input-field" type="text" id="cam-name" name="name"
                                value="{{ old('name') }}" placeholder="CCTV Pintu Masuk / CCTV Musala" required>
                        </div>

                        <div class="field">
                            <label class="field-label" for="cam-type">Jenis siaran</label>
                            <select class="select-field" id="cam-type" name="stream_type" required>
                                @foreach ($types as $val => $label)
                                    <option value="{{ $val }}" @selected(old('stream_type', \App\Models\Camera::TypeWebStream) === $val)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field field-full">
                            <label class="field-label" for="cam-stream-url">Alamat URL siaran (Stream URL)</label>
                            <input class="input-field" type="text" id="cam-stream-url" name="stream_url"
                                value="{{ old('stream_url') }}"
                                placeholder="rtsp://admin:pass@192.168.1.x:554/ch1 atau URL HTTP/HTTPS (fMP4/HLS)"
                                required>
                            <p class="field-hint">
                                Untuk Web Stream gunakan URL HTTP/HTTPS (fMP4, MJPEG, atau HLS). Untuk RTSP gunakan format rtsp://pengguna:sandi@ip:port/jalur.
                            </p>
                        </div>

                        <div class="field field-full">
                            <label class="field-label" for="cam-snapshot-url">Alamat URL cuplikan potret (Opsional)</label>
                            <input class="input-field" type="text" id="cam-snapshot-url" name="snapshot_url"
                                value="{{ old('snapshot_url') }}"
                                placeholder="http://192.168.1.x/ISAPI/Streaming/channels/101/picture (Bila CCTV memiliki URL potret HTTP)">
                        </div>

                        <div class="field">
                            <label class="field-label" for="cam-location">Letak / Lokasi kamera</label>
                            <input class="input-field" type="text" id="cam-location" name="location"
                                value="{{ old('location') }}" placeholder="Gerbang Masuk / Musala Lantai 1">
                        </div>

                        <div class="field">
                            <label class="field-label" for="cam-notes">Catatan tambahan</label>
                            <input class="input-field" type="text" id="cam-notes" name="notes"
                                value="{{ old('notes') }}" placeholder="Kamera menghadap ke pintu masuk">
                        </div>
                    </div>

                    <div class="form-check" style="margin-top: 14px;">
                        <label class="check-label">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                            <span>Aktifkan kamera ini di sistem</span>
                        </label>
                    </div>

                    <div id="test-result-box" class="alert" style="display: none; margin-top: 14px;">
                        <span id="test-result-text"></span>
                    </div>

                    <div class="form-actions" style="margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap;">
                        <button class="btn btn-outline" type="button" id="btn-tutup-modal-batal">Batal</button>
                        <button class="btn btn-outline" type="button" id="btn-uji-koneksi">Uji koneksi siaran</button>
                        <button class="btn btn-primary" type="submit">Simpan kamera</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // 1. Logika Pengujian Koneksi Kamera
            const btnUji = document.getElementById('btn-uji-koneksi');
            const typeInput = document.getElementById('cam-type');
            const streamInput = document.getElementById('cam-stream-url');
            const snapshotInput = document.getElementById('cam-snapshot-url');
            const resultBox = document.getElementById('test-result-box');
            const resultText = document.getElementById('test-result-text');

            if (btnUji) {
                btnUji.addEventListener('click', async function () {
                    const type = typeInput.value;
                    const streamUrl = streamInput.value.trim();
                    const snapshotUrl = snapshotInput.value.trim();

                    if (!streamUrl) {
                        alert('Silakan isi URL siaran terlebih dahulu.');
                        streamInput.focus();
                        return;
                    }

                    btnUji.disabled = true;
                    btnUji.textContent = 'Menguji koneksi...';
                    resultBox.style.display = 'block';
                    resultBox.className = 'alert alert-info';
                    resultText.textContent = 'Sedang menghubungi server siaran kamera, mohon tunggu beberapa detik...';

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const token = tokenEl ? tokenEl.content : '';

                        const res = await fetch("{{ route('cameras.test') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                stream_type: type,
                                stream_url: streamUrl,
                                snapshot_url: snapshotUrl || null,
                            }),
                        });

                        const data = await res.json();
                        if (data.success) {
                            resultBox.className = 'alert alert-success';
                            resultText.textContent = data.message;
                        } else {
                            resultBox.className = 'alert alert-warning';
                            resultText.textContent = data.message;
                        }
                    } catch (e) {
                        resultBox.className = 'alert alert-warning';
                        resultText.textContent = 'Gagal menguji koneksi: periksa jaringan atau koneksi lokal.';
                    } finally {
                        btnUji.disabled = false;
                        btnUji.textContent = 'Uji koneksi siaran';
                    }
                });
            }

            // 2. Pemantau Siaran CCTV & Pemindai Wajah
            const monitorBox = document.getElementById('cctv-monitor-box');
            if (!monitorBox) return;

            const video = document.getElementById('cctv-video');
            const img = document.getElementById('cctv-img');
            const canvas = document.getElementById('cctv-canvas');
            const overlay = document.getElementById('cctv-overlay');
            const placeholder = document.getElementById('cctv-placeholder');
            const statusText = document.getElementById('cctv-status-text');
            const btnToggleScanner = document.getElementById('btn-toggle-scanner');
            const btnRefresh = document.getElementById('btn-refresh-stream');
            const logList = document.getElementById('cctv-log-list');

            const camId = monitorBox.dataset.cameraId;
            const camType = monitorBox.dataset.cameraType;
            const streamUrl = monitorBox.dataset.streamUrl;
            const snapshotUrl = monitorBox.dataset.snapshotUrl;
            const scanEndpoint = monitorBox.dataset.scanEndpoint;

            let isScanning = false;
            let scanTimer = null;
            let isSnapshotMode = false;
            let snapshotPollTimer = null;
            let activeLoading = false;
            let busyScanning = false;
            let currentFaces = [];
            let currentFrame = null;
            let rtcWs = null;
            let rtcFallbackTimer = null;
            let rtcReconnectTimer = null;

            function updateStatus(text) {
                if (statusText) statusText.textContent = text;
            }

            function cleanupGo2Rtc() {
                if (rtcFallbackTimer) {
                    clearTimeout(rtcFallbackTimer);
                    rtcFallbackTimer = null;
                }
                if (rtcReconnectTimer) {
                    clearTimeout(rtcReconnectTimer);
                    rtcReconnectTimer = null;
                }
                if (rtcWs) {
                    rtcWs.onclose = null;
                    rtcWs.onerror = null;
                    rtcWs.onmessage = null;
                    try { rtcWs.close(); } catch (e) {}
                    rtcWs = null;
                }
            }

            function startStream() {
                cleanupGo2Rtc();
                if (snapshotPollTimer) {
                    clearTimeout(snapshotPollTimer);
                    snapshotPollTimer = null;
                }

                if (camType === 'web_stream') {
                    placeholder.style.display = 'block';
                    video.style.display = 'none';
                    img.style.display = 'none';

                    video.src = streamUrl;
                    video.onloadedmetadata = function () {
                        placeholder.style.display = 'none';
                        video.style.display = 'block';
                        isSnapshotMode = false;
                        updateStatus('Siaran Web Stream terhubung. Siap untuk pemantauan.');
                    };
                    video.onerror = function () {
                        startSnapshotStream();
                    };
                    video.play().catch(function () {
                        startSnapshotStream();
                    });
                } else {
                    // RTSP CCTV: gunakan go2rtc (MSE / MP4 via WebSocket) untuk video mulus 0% CPU
                    startGo2RtcStream();
                }
            }

            function startGo2RtcStream(useProxyPath) {
                cleanupGo2Rtc();

                if (!('MediaSource' in window) || !('WebSocket' in window)) {
                    startSnapshotStream();
                    return;
                }

                const wsProto = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const wsHost = window.location.hostname || '127.0.0.1';
                const streamKey = 'cam_' + camId;
                const preferProxy = useProxyPath !== false;
                const wsUrl = preferProxy
                    ? (wsProto + '//' + window.location.host + '/go2rtc/ws?src=' + encodeURIComponent(streamKey))
                    : (wsProto + '//' + wsHost + ':1984/api/ws?src=' + encodeURIComponent(streamKey));

                let ms = new MediaSource();
                let sourceBuffer = null;
                let mseQueue = [];
                let hasStartedVideo = false;

                video.srcObject = null;
                video.src = URL.createObjectURL(ms);

                // Fallback ke jalur alternatif (:1984 lokal atau snapshot) bila dalam 6 detik belum mengalirkan paket video
                rtcFallbackTimer = setTimeout(function () {
                    if (!hasStartedVideo) {
                        cleanupGo2Rtc();
                        if (preferProxy && window.location.protocol === 'http:') {
                            startGo2RtcStream(false);
                        } else {
                            startSnapshotStream();
                        }
                    }
                }, 6000);

                function flushMseQueue() {
                    if (!sourceBuffer || sourceBuffer.updating || mseQueue.length === 0) {
                        return;
                    }
                    try {
                        const chunk = mseQueue.shift();
                        sourceBuffer.appendBuffer(chunk);
                    } catch (e) {
                        mseQueue = [];
                    }
                }

                ms.addEventListener('sourceopen', function () {
                    try {
                        rtcWs = new WebSocket(wsUrl);
                        rtcWs.binaryType = 'arraybuffer';
                    } catch (e) {
                        cleanupGo2Rtc();
                        startSnapshotStream();
                        return;
                    }

                    rtcWs.onopen = function () {
                        const codecs = [
                            'avc1.640029', 'avc1.64002A', 'avc1.640033',
                            'hvc1.1.6.L153.B0', 'mp4a.40.2', 'mp4a.40.5', 'flac', 'opus'
                        ].filter(function (c) {
                            return MediaSource.isTypeSupported('video/mp4; codecs="' + c + '"');
                        }).join(',');

                        rtcWs.send(JSON.stringify({ type: 'mse', value: codecs }));
                    };

                    rtcWs.onmessage = function (ev) {
                        if (typeof ev.data === 'string') {
                            try {
                                const msg = JSON.parse(ev.data);
                                if (msg.type === 'mse' && ms.readyState === 'open' && !sourceBuffer) {
                                    sourceBuffer = ms.addSourceBuffer(msg.value);
                                    sourceBuffer.mode = 'segments';
                                    sourceBuffer.addEventListener('updateend', function () {
                                        if (video.buffered.length > 0) {
                                            if (!hasStartedVideo) {
                                                hasStartedVideo = true;
                                                if (rtcFallbackTimer) {
                                                    clearTimeout(rtcFallbackTimer);
                                                    rtcFallbackTimer = null;
                                                }
                                                isSnapshotMode = false;
                                                placeholder.style.display = 'none';
                                                img.style.display = 'none';
                                                video.style.display = 'block';
                                                video.play().catch(function () {});
                                                if (currentFaces && currentFaces.length > 0 && currentFrame) {
                                                    drawOverlayFaces(currentFaces, currentFrame);
                                                }
                                                if (!isScanning) {
                                                    updateStatus('Siaran langsung RTSP aktif via go2rtc (MSE WebSocket — mulus & ringan).');
                                                }
                                            }

                                            // Jaga agar waktu putar selalu berada di ujung live (latensi < 0.3 detik)
                                            const end = video.buffered.end(video.buffered.length - 1);
                                            if (end - video.currentTime > 1.5) {
                                                video.currentTime = end - 0.15;
                                            }

                                            // Bersihkan buffer lama agar memori browser tetap ringan
                                            const start = video.buffered.start(0);
                                            if (!sourceBuffer.updating && (end - start > 15)) {
                                                try {
                                                    sourceBuffer.remove(start, end - 8);
                                                    return;
                                                } catch (e) {}
                                            }
                                        }
                                        flushMseQueue();
                                    });
                                }
                            } catch (e) {}
                        } else if (ev.data instanceof ArrayBuffer) {
                            mseQueue.push(new Uint8Array(ev.data));
                            if (mseQueue.length > 60) {
                                mseQueue = mseQueue.slice(-30);
                            }
                            flushMseQueue();
                        }
                    };

                    rtcWs.onerror = function () {
                        if (!hasStartedVideo) {
                            cleanupGo2Rtc();
                            if (preferProxy && window.location.protocol === 'http:') {
                                startGo2RtcStream(false);
                            } else {
                                startSnapshotStream();
                            }
                        }
                    };

                    rtcWs.onclose = function () {
                        if (hasStartedVideo) {
                            rtcReconnectTimer = setTimeout(function () {
                                startGo2RtcStream(preferProxy);
                            }, 2000);
                        }
                    };
                }, { once: true });
            }

            function startSnapshotStream() {
                isSnapshotMode = true;
                video.style.display = 'none';

                function loadFrame() {
                    if (activeLoading) return;
                    activeLoading = true;

                    const buster = snapshotUrl + (snapshotUrl.includes('?') ? '&' : '?') + '_t=' + Date.now();
                    const preloader = new Image();

                    preloader.onload = function () {
                        activeLoading = false;
                        img.src = preloader.src;
                        img.style.display = 'block';
                        placeholder.style.display = 'none';
                        if (!isScanning) {
                            updateStatus('Siaran video RTSP CCTV terhubung (siaran langsung aktif).');
                        }
                        snapshotPollTimer = setTimeout(loadFrame, 120);
                    };

                    preloader.onerror = function () {
                        activeLoading = false;
                        if (img.style.display === 'none') {
                            updateStatus('Menghubungkan ke siaran RTSP CCTV...');
                        }
                        snapshotPollTimer = setTimeout(loadFrame, 800);
                    };

                    preloader.src = buster;
                }

                if (snapshotPollTimer) clearTimeout(snapshotPollTimer);
                loadFrame();
            }

            // Jalankan stream pertama kali dan nyalakan pemindai wajah otomatis
            startStream();

            if (btnRefresh) {
                btnRefresh.addEventListener('click', function () {
                    if (snapshotPollTimer) clearTimeout(snapshotPollTimer);
                    activeLoading = false;
                    startStream();
                });
            }

            // 3. Tombol Aktifkan / Hentikan Pemindai Wajah
            if (btnToggleScanner) {
                btnToggleScanner.addEventListener('click', function () {
                    if (isScanning) {
                        stopFaceScanner();
                    } else {
                        startFaceScanner();
                    }
                });
            }

            function startFaceScanner() {
                if (isScanning) return;
                isScanning = true;
                if (btnToggleScanner) {
                    btnToggleScanner.textContent = 'Hentikan pemindai wajah';
                    btnToggleScanner.className = 'btn btn-danger';
                }
                updateStatus('Pemindai wajah CCTV AKTIF. Memeriksa wajah setiap 2 detik...');

                if (scanTimer) clearInterval(scanTimer);
                scanTimer = setInterval(captureAndScanFace, 2000);
                setTimeout(captureAndScanFace, 400);
            }

            // Otomatis nyalakan pemindai wajah saat halaman dibuka
            startFaceScanner();

            function stopFaceScanner() {
                isScanning = false;
                btnToggleScanner.textContent = 'Aktifkan pemindai wajah';
                btnToggleScanner.className = 'btn btn-primary';
                updateStatus('Siaran aktif untuk pemantauan. Pemindai wajah dihentikan.');

                if (scanTimer) {
                    clearInterval(scanTimer);
                    scanTimer = null;
                }

                currentFaces = [];
                currentFrame = null;
                clearFaceOverlay();
            }

            async function captureAndScanFace() {
                if (busyScanning) return;
                busyScanning = true;

                try {
                    let blob = null;
                    let targetW = 640;
                    let targetH = 480;

                    if (!isSnapshotMode && video && video.videoWidth > 0) {
                        targetW = video.videoWidth;
                        targetH = video.videoHeight;
                        canvas.width = targetW;
                        canvas.height = targetH;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(video, 0, 0, targetW, targetH);
                        blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.85));
                    } else if (img && img.naturalWidth > 0) {
                        targetW = img.naturalWidth;
                        targetH = img.naturalHeight;
                        canvas.width = targetW;
                        canvas.height = targetH;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, targetW, targetH);
                        blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.85));
                    } else {
                        const snapUrl = snapshotUrl + (snapshotUrl.includes('?') ? '&' : '?') + '_t=' + Date.now();
                        const res = await fetch(snapUrl, { credentials: 'same-origin' }).catch(() => null);
                        if (res && res.ok) {
                            blob = await res.blob();
                        }
                    }

                    if (!blob) {
                        busyScanning = false;
                        return;
                    }

                    const tokenEl = document.querySelector('meta[name="csrf-token"]');
                    const token = tokenEl ? tokenEl.content : '';

                    const formData = new FormData();
                    formData.append('photo', blob, 'cctv_frame.jpg');

                    const scanRes = await fetch(scanEndpoint, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: formData,
                    });

                    const data = await scanRes.json().catch(() => null);
                    if (data && Array.isArray(data.faces)) {
                        currentFaces = data.faces;
                        currentFrame = {
                            width: Number(data.width || targetW),
                            height: Number(data.height || targetH),
                        };
                        drawOverlayFaces(currentFaces, currentFrame);

                        if (data.status === 'service_unavailable' && data.message) {
                            updateStatus(data.message);
                        } else if (data.faces.length > 0) {
                            updateStatus('Pemindai aktif — ' + data.faces.length + ' wajah terdeteksi di layar CCTV.');
                        } else {
                            updateStatus('Pemindai wajah CCTV AKTIF. Memeriksa wajah setiap 2 detik...');
                        }

                        // Periksa apakah ada wajah yang berhasil tercatat
                        data.faces.forEach(function (face) {
                            if (face.attendance && face.attendance.recorded && face.member) {
                                addCctvLogRow(face.member.name, face.member.identity || face.member.class_name, face.attendance.display_message || face.attendance.status, face.attendance.proof_photo_url || data.proof_url);
                            }
                        });
                    } else {
                        clearFaceOverlay();
                    }
                } catch (e) {
                    // Abaikan kesalahan putaran scan tunggal
                } finally {
                    busyScanning = false;
                }
            }

            function clearFaceOverlay() {
                if (!overlay) return;
                const ctx = overlay.getContext('2d');
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.clearRect(0, 0, overlay.width, overlay.height);
            }

            function pickCssColor(varName, fallback) {
                const val = getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
                return val === '' ? fallback : val;
            }

            function containMapping(mediaEl, boxW, boxH, frame) {
                const srcW = (frame && frame.width > 0) ? frame.width : ((mediaEl && (mediaEl.videoWidth || mediaEl.naturalWidth)) || 1920);
                const srcH = (frame && frame.height > 0) ? frame.height : ((mediaEl && (mediaEl.videoHeight || mediaEl.naturalHeight)) || 1080);
                const contentW = (mediaEl && (mediaEl.videoWidth || mediaEl.naturalWidth)) || srcW;
                const contentH = (mediaEl && (mediaEl.videoHeight || mediaEl.naturalHeight)) || srcH;

                const scale = Math.min(boxW / contentW, boxH / contentH);

                return {
                    offsetX: (boxW - contentW * scale) / 2,
                    offsetY: (boxH - contentH * scale) / 2,
                    scaleX: (contentW / srcW) * scale,
                    scaleY: (contentH / srcH) * scale,
                };
            }

            function drawOverlayFaces(faces, frame) {
                if (!overlay || !frame) return;

                const rect = overlay.getBoundingClientRect();
                const displayW = rect.width || overlay.clientWidth || 640;
                const displayH = rect.height || overlay.clientHeight || 360;
                const ratio = window.devicePixelRatio || 1;

                if (displayW === 0 || displayH === 0) return;

                overlay.width = Math.round(displayW * ratio);
                overlay.height = Math.round(displayH * ratio);

                const ctx = overlay.getContext('2d');
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.clearRect(0, 0, displayW, displayH);

                if (!faces || faces.length === 0) return;

                const activeMedia = (!isSnapshotMode && video && video.style.display !== 'none') ? video : img;
                const map = containMapping(activeMedia, displayW, displayH, frame);

                const colorSaved = pickCssColor('--success', '#1b6b3a');
                const colorUnknown = pickCssColor('--warning', '#8a5300');

                faces.forEach(function (face) {
                    if (!face.box) return;

                    const bx = map.offsetX + Number(face.box.left || 0) * map.scaleX;
                    const by = map.offsetY + Number(face.box.top || 0) * map.scaleY;
                    const bw = Math.max(2, (Number(face.box.right || 0) - Number(face.box.left || 0)) * map.scaleX);
                    const bh = Math.max(2, (Number(face.box.bottom || 0) - Number(face.box.top || 0)) * map.scaleY);

                    const status = String((face.attendance && face.attendance.status) || face.status || '');
                    const isSaved = Boolean(
                        (face.attendance && face.attendance.recorded) ||
                        status === 'recorded' ||
                        status === 'late' ||
                        status === 'duplicate' ||
                        status === 'cooldown' ||
                        face.member
                    );
                    const color = isSaved ? colorSaved : colorUnknown;

                    ctx.lineWidth = 2.5;
                    ctx.strokeStyle = color;
                    ctx.strokeRect(bx, by, bw, bh);

                    // Label 2 baris persis seperti halaman Absen Wajah
                    const nama = (typeof face.label === 'string' && face.label !== '')
                        ? face.label
                        : ((face.member && face.member.name) ? face.member.name : 'Tidak dikenal');
                    const keadaan = (face.attendance && typeof face.attendance.display_message === 'string' && face.attendance.display_message !== '')
                        ? face.attendance.display_message
                        : (typeof face.display_message === 'string' && face.display_message !== '' ? face.display_message : 'Belum terdaftar');

                    ctx.font = '600 14px Inter, system-ui, sans-serif';
                    const nameW = ctx.measureText(nama).width;

                    ctx.font = '500 12px Inter, system-ui, sans-serif';
                    const statusW = ctx.measureText(keadaan).width;

                    const labelW = Math.min(displayW, Math.max(nameW, statusW) + 16);
                    const labelH = 34;

                    const labelX = Math.max(0, Math.min(displayW - labelW, bx));
                    const labelY = (by - labelH < 0) ? by : (by - labelH);

                    ctx.fillStyle = color;
                    ctx.fillRect(labelX, labelY, labelW, labelH);

                    ctx.fillStyle = '#ffffff';
                    ctx.font = '600 14px Inter, system-ui, sans-serif';
                    ctx.fillText(nama, labelX + 8, labelY + 15, labelW - 16);

                    ctx.font = '500 12px Inter, system-ui, sans-serif';
                    ctx.fillText(keadaan, labelX + 8, labelY + 29, labelW - 16);
                });
            }

            window.addEventListener('resize', function () {
                if (currentFaces && currentFaces.length > 0 && currentFrame) {
                    drawOverlayFaces(currentFaces, currentFrame);
                }
            });

            const logsUrl = monitorBox.dataset.logsUrl;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function renderCctvLogs(logs) {
                if (!logList || !Array.isArray(logs) || logs.length === 0) return;

                const emptyEl = document.getElementById('cctv-empty-log');
                if (emptyEl) emptyEl.style.display = 'none';

                logList.innerHTML = '';
                logs.forEach(function (log) {
                    const row = document.createElement('div');
                    row.className = 'log-row';

                    const safeName = escapeHtml(log.name || 'Anggota');
                    const safeTime = escapeHtml(log.time || '');
                    const safeSession = escapeHtml(log.session || 'Uji Coba');
                    const safeIdentity = log.identity ? escapeHtml(log.identity) : '';
                    const safeDeviceId = log.device_id ? escapeHtml(log.device_id) : '';
                    const safeStatus = escapeHtml(log.status || 'Hadir');
                    const safeProofUrl = log.proof_photo_url ? escapeHtml(log.proof_photo_url) : '';
                    const safeInitial = escapeHtml((log.name && String(log.name).charAt(0)) || 'A');

                    let photoHtml = '';
                    if (log.is_fingerprint) {
                        photoHtml = '<div class="proof-thumb proof-thumb-fingerprint" title="Absen via sidik jari' + (safeDeviceId ? ' (' + safeDeviceId + ')' : '') + '" aria-label="Absen via sidik jari" style="width: 44px; height: 44px; min-width: 44px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; flex-shrink: 0;">' +
                            '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
                                '<path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 3"></path>' +
                                '<path d="M14 13.12c0 2.38 0 6.38-1 8.88"></path>' +
                                '<path d="M2 16h.01"></path>' +
                                '<path d="M21.8 16c.2-2 .131-5.354 0-6"></path>' +
                                '<path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2"></path>' +
                                '<path d="M8.65 22c.21-.66.45-1.32.57-2"></path>' +
                                '<path d="M9 6.8a6 6 0 0 1 9 5.2v2"></path>' +
                            '</svg>' +
                        '</div>';
                    } else if (safeProofUrl) {
                        photoHtml = '<a class="proof-link" href="' + safeProofUrl + '" target="_blank" rel="noopener">' +
                                    '<img class="proof-thumb" src="' + safeProofUrl + '" alt="Bukti CCTV" width="44" height="44" style="border-radius: 8px; object-fit: cover;">' +
                                    '</a>';
                    } else {
                        photoHtml = '<span class="avatar-wrap avatar-none" style="width: 44px; height: 44px; font-size: 15px; border-radius: 8px;">' + safeInitial + '</span>';
                    }

                    row.innerHTML = photoHtml +
                        '<div class="log-body">' +
                            '<div class="log-line">' +
                                '<span class="log-name">' + safeName + '</span>' +
                                '<span class="log-time">' + safeTime + '</span>' +
                            '</div>' +
                            '<div class="log-line log-line-meta">' +
                                '<span class="log-sub">' +
                                    '<span>' + safeSession + '</span>' +
                                    (safeIdentity ? '<span>' + safeIdentity + '</span>' : '') +
                                    (log.is_fingerprint ? '<span style="color: #0284c7; font-weight: 600;">(Sidik Jari)</span>' : '') +
                                '</span>' +
                                '<span class="status ' + (log.status === 'Terlambat' ? 'status-terlambat' : 'status-hadir') + '">' + safeStatus + '</span>' +
                            '</div>' +
                        '</div>';

                    logList.appendChild(row);
                });
            }

            // Polling log otomatis setiap 2.5 detik untuk menyelaraskan dengan Background Worker di server
            if (logsUrl) {
                setInterval(async function () {
                    try {
                        const res = await fetch(logsUrl, { credentials: 'same-origin' });
                        if (!res.ok) return;
                        const data = await res.json();
                        if (data && Array.isArray(data.logs) && data.logs.length > 0) {
                            renderCctvLogs(data.logs);
                        }
                        if (data && data.last_scan && Array.isArray(data.last_scan.faces) && !isScanning) {
                            currentFaces = data.last_scan.faces;
                            currentFrame = {
                                width: Number(data.last_scan.width || 1920),
                                height: Number(data.last_scan.height || 1080),
                            };
                            drawOverlayFaces(currentFaces, currentFrame);
                        }
                    } catch (e) {
                        // Abaikan kesalahan polling
                    }
                }, 2500);
            }

            function addCctvLogRow(name, identity, status, proofUrl) {
                const emptyEl = document.getElementById('cctv-empty-log');
                if (emptyEl) emptyEl.style.display = 'none';

                const now = new Date();
                const timeStr = String(now.getHours()).padStart(2, '0') + ':' +
                                String(now.getMinutes()).padStart(2, '0') + ':' +
                                String(now.getSeconds()).padStart(2, '0');

                const row = document.createElement('div');
                row.className = 'log-row';

                const safeName = escapeHtml(name || 'Anggota');
                const safeIdentity = identity ? escapeHtml(identity) : '';
                const safeProofUrl = proofUrl ? escapeHtml(proofUrl) : '';
                const safeInitial = escapeHtml((name && String(name).charAt(0)) || 'A');

                let photoHtml = '<span class="avatar-wrap avatar-none" style="width: 44px; height: 44px; font-size: 15px;">' + safeInitial + '</span>';
                if (safeProofUrl) {
                    photoHtml = '<a class="proof-link" href="' + safeProofUrl + '" target="_blank" rel="noopener">' +
                                '<img class="proof-thumb" src="' + safeProofUrl + '" alt="Bukti CCTV" width="44" height="44" style="border-radius: 6px; object-fit: cover;">' +
                                '</a>';
                }

                row.innerHTML = photoHtml +
                    '<div class="log-body">' +
                        '<div class="log-line">' +
                            '<span class="log-name">' + safeName + '</span>' +
                            '<span class="log-time">' + timeStr + '</span>' +
                        '</div>' +
                        '<div class="log-line log-line-meta">' +
                            '<span class="log-sub">' +
                                '<span>Dari CCTV</span>' +
                                (safeIdentity ? '<span>' + safeIdentity + '</span>' : '') +
                            '</span>' +
                            '<span class="status status-hadir">Hadir</span>' +
                        '</div>' +
                    '</div>';

                logList.prepend(row);

                // Batasi 10 baris
                while (logList.children.length > 10) {
                    logList.removeChild(logList.lastElementChild);
                }
            }

            // Logika Modal Pop-up Tambah Kamera CCTV
            const modalCctv = document.getElementById('modal-tambah-cctv');
            const btnsBukaModal = document.querySelectorAll('.btn-trigger-tambah-cctv');
            const btnTutupModal = document.getElementById('btn-tutup-modal');
            const btnTutupModalBatal = document.getElementById('btn-tutup-modal-batal');

            function bukaModalCctv() {
                if (modalCctv) {
                    modalCctv.style.display = 'flex';
                    modalCctv.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                    const nameInput = document.getElementById('cam-name');
                    if (nameInput) setTimeout(() => nameInput.focus(), 50);
                }
            }

            function tutupModalCctv() {
                if (modalCctv) {
                    modalCctv.style.display = 'none';
                    modalCctv.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                }
            }

            btnsBukaModal.forEach(btn => btn.addEventListener('click', bukaModalCctv));
            if (btnTutupModal) btnTutupModal.addEventListener('click', tutupModalCctv);
            if (btnTutupModalBatal) btnTutupModalBatal.addEventListener('click', tutupModalCctv);

            if (modalCctv) {
                modalCctv.addEventListener('click', function (e) {
                    if (e.target === modalCctv) tutupModalCctv();
                });
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modalCctv && modalCctv.style.display === 'flex') {
                    tutupModalCctv();
                }
            });

            @if ($errors->any())
                bukaModalCctv();
            @endif
        });
    </script>

    <style>
        .cctv-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 1050;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
            overflow-y: auto;
        }
        .cctv-modal-card {
            background: var(--surface, #ffffff);
            color: var(--on-surface, #1e293b);
            border-radius: var(--radius-lg, 14px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px;
            box-sizing: border-box;
            position: relative;
            margin: auto;
        }
        .cctv-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            border-bottom: 1px solid var(--outline-variant, #e2e8f0);
            padding-bottom: 14px;
        }
        .cctv-modal-close {
            background: transparent;
            border: none;
            font-size: 26px;
            line-height: 1;
            color: var(--on-surface-variant, #64748b);
            cursor: pointer;
            padding: 2px 8px;
            border-radius: 6px;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .cctv-modal-close:hover {
            background: var(--surface-container-high, #f1f5f9);
            color: var(--on-surface, #0f172a);
        }

        @media (max-width: 680px) {
            .cctv-stats-grid {
                display: grid !important;
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 6px !important;
                margin-bottom: 12px !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            .cctv-stats-grid .stat-card {
                min-width: 0 !important;
                padding: 10px 6px !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
                text-align: center !important;
            }
            .cctv-stats-grid .stat-label {
                font-size: 11px !important;
                white-space: normal !important;
                word-break: break-word !important;
                overflow: hidden !important;
                line-height: 1.2 !important;
                height: 26px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            .cctv-stats-grid .stat-value {
                font-size: 20px !important;
                margin-top: 2px !important;
                white-space: nowrap !important;
            }
            .cctv-stats-grid .stat-note {
                display: none !important;
            }
            .cctv-section-head {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 10px !important;
            }
            .cctv-section-head .field {
                width: 100% !important;
            }
            .cctv-section-head .select-field {
                width: 100% !important;
            }
            .cctv-controls-actions {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 8px !important;
            }
            .cctv-controls-actions #btn-toggle-scanner {
                grid-column: 1 / -1 !important;
            }
            .cctv-controls-actions .btn {
                width: 100% !important;
                justify-content: center !important;
                text-align: center !important;
                font-size: 13px !important;
                padding: 10px 8px !important;
                white-space: nowrap !important;
            }
            .cctv-modal-backdrop {
                padding: 8px !important;
                align-items: flex-end !important;
            }
            .cctv-modal-card {
                padding: 20px 16px !important;
                max-height: 90vh !important;
                border-radius: 16px 16px 8px 8px !important;
            }
            .cctv-modal-card .form-actions {
                flex-direction: column-reverse !important;
                gap: 8px !important;
            }
            .cctv-modal-card .form-actions .btn {
                width: 100% !important;
                justify-content: center !important;
                text-align: center !important;
            }
        }
    </style>
@endsection
