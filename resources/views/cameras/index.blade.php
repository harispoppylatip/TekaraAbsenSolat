@extends('layouts.app')

@section('title', 'Kamera Absen')
@section('note', 'Pengaturan kamera siaran CCTV (RTSP) dan Web Stream untuk pemindai absen')

@section('content')
    <div data-live="kamera">
        <section class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Total kamera siaran</p>
                <p class="stat-value">{{ $stats['total'] }}</p>
                <p class="stat-note">{{ $stats['aktif'] }} sedang dinyalakan</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Web Stream (HTTP/fMP4)</p>
                <p class="stat-value">{{ $stats['web_stream'] }}</p>
                <p class="stat-note">Siaran web atau VMS media</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">RTSP CCTV</p>
                <p class="stat-value">{{ $stats['rtsp'] }}</p>
                <p class="stat-note">Kamera IP atau NVR lokal</p>
            </div>
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Tambah kamera siaran</h2>
                    <p class="section-note">
                        Hubungkan kamera CCTV atau tautan siaran video untuk dipakai memindai wajah jamaah pada halaman Absen Wajah.
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('cameras.store') }}" id="form-tambah-kamera">
                @csrf

                <div class="form-grid">
                    <div class="field">
                        <label class="field-label" for="cam-name">Nama kamera</label>
                        <input class="input-field" type="text" id="cam-name" name="name"
                            value="{{ old('name') }}" placeholder="CCTV Pintu Masuk / Kamera VMS" required>
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
                            placeholder="https://vmssamarinda.iconpln.co.id:...&format=fmp4... atau rtsp://admin:pass@192.168.1.x:554/ch1"
                            required>
                        <p class="field-hint" id="stream-url-hint">
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
                            value="{{ old('notes') }}" placeholder="Kamera utama berhadapan dengan pintu masuk">
                    </div>
                </div>

                <div class="form-check" style="margin-top: 14px;">
                    <label class="check-label">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        <span>Aktifkan kamera ini agar muncul di pilihan kamera halaman Absen Wajah</span>
                    </label>
                </div>

                <div id="test-result-box" class="alert" style="display: none; margin-top: 14px;">
                    <span id="test-result-text"></span>
                </div>

                <div class="form-actions" style="margin-top: 18px;">
                    <button class="btn btn-primary" type="submit">Simpan kamera</button>
                    <button class="btn btn-outline" type="button" id="btn-uji-koneksi">Uji koneksi siaran</button>
                </div>
            </form>
        </section>

        <section class="card" style="margin-top: 24px;">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Daftar kamera siaran</h2>
                    <p class="section-note">
                        Kamera yang aktif akan langsung muncul di daftar pilihan kamera pada pemindai wajah.
                    </p>
                </div>
            </div>

            @if ($cameras->isEmpty())
                <p class="empty-state">Belum ada kamera siaran atau CCTV yang didaftarkan.</p>
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
                                        <code style="font-size: 11px; word-break: break-all; max-width: 280px; display: inline-block;">
                                            {{ Str::limit($cam->stream_url, 45) }}
                                        </code>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="table-actions" style="display: inline-flex; gap: 8px; justify-content: flex-end;">
                                            <button type="button" class="btn btn-small btn-outline btn-preview-cam"
                                                data-name="{{ $cam->name }}"
                                                data-type="{{ $cam->stream_type }}"
                                                data-stream="{{ route('cameras.stream', $cam) }}"
                                                data-snapshot="{{ route('cameras.snapshot', $cam) }}">
                                                Lihat siaran
                                            </button>

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

        {{-- Dialog / Panel Cuplikan Siaran Langsung Kamera --}}
        <div id="cam-preview-modal" class="modal-backdrop" style="display: none;">
            <div class="modal-box card" style="max-width: 720px; width: 100%;">
                <div class="section-head">
                    <div>
                        <h2 class="section-title" id="preview-modal-title">Pratinjau siaran</h2>
                        <p class="section-note" id="preview-modal-note">Memuat siaran langsung kamera...</p>
                    </div>
                    <button type="button" class="btn btn-small btn-outline" id="btn-close-preview">Tutup</button>
                </div>

                <div class="camera-frame" style="margin-top: 14px; background: #000; min-height: 360px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; border-radius: 8px;">
                    <video id="preview-video" playsinline muted autoplay style="width: 100%; height: auto; max-height: 480px; object-fit: contain; display: none;"></video>
                    <img id="preview-img" alt="Cuplikan kamera" style="width: 100%; height: auto; max-height: 480px; object-fit: contain; display: none;">
                    <div id="preview-loading" style="color: #fff; padding: 20px; text-align: center;">Menghubungkan ke siaran kamera...</div>
                </div>

                <div class="form-actions" style="margin-top: 16px; justify-content: space-between;">
                    <span id="preview-fps-text" style="font-size: 13px; color: var(--color-muted, #666);">Siaran aktif</span>
                    <button type="button" class="btn btn-secondary" id="btn-modal-done">Selesai</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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

            // Pratinjau Modal
            const previewModal = document.getElementById('cam-preview-modal');
            const previewVideo = document.getElementById('preview-video');
            const previewImg = document.getElementById('preview-img');
            const previewLoading = document.getElementById('preview-loading');
            const previewTitle = document.getElementById('preview-modal-title');
            const previewNote = document.getElementById('preview-modal-note');
            const closeBtn = document.getElementById('btn-close-preview');
            const doneBtn = document.getElementById('btn-modal-done');
            let previewPollTimer = null;

            function closePreview() {
                if (previewPollTimer) {
                    clearInterval(previewPollTimer);
                    previewPollTimer = null;
                }
                previewVideo.pause();
                previewVideo.removeAttribute('src');
                previewVideo.load();
                previewVideo.style.display = 'none';
                previewImg.removeAttribute('src');
                previewImg.style.display = 'none';
                previewModal.style.display = 'none';
            }

            if (closeBtn) closeBtn.addEventListener('click', closePreview);
            if (doneBtn) doneBtn.addEventListener('click', closePreview);

            document.querySelectorAll('.btn-preview-cam').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const name = btn.dataset.name;
                    const type = btn.dataset.type;
                    const streamUrl = btn.dataset.stream;
                    const snapshotUrl = btn.dataset.snapshot;

                    previewTitle.textContent = 'Pratinjau ' + name;
                    previewNote.textContent = 'Menghubungkan ke siaran (' + (type === 'rtsp' ? 'RTSP CCTV' : 'Web Stream') + ')...';
                    previewModal.style.display = 'flex';
                    previewLoading.style.display = 'block';
                    previewVideo.style.display = 'none';
                    previewImg.style.display = 'none';

                    if (type === 'web_stream') {
                        previewVideo.src = streamUrl;
                        previewVideo.onloadedmetadata = function () {
                            previewLoading.style.display = 'none';
                            previewVideo.style.display = 'block';
                            previewNote.textContent = 'Siaran video Web Stream berhasil dimuat.';
                        };
                        previewVideo.onerror = function () {
                            // Fallback snapshot polling bila peramban tidak dapat memutar stream secara langsung
                            previewVideo.style.display = 'none';
                            previewNote.textContent = 'Menggunakan cuplikan potret berkala (snapshot)...';
                            startSnapshotPolling(snapshotUrl);
                        };
                        previewVideo.play().catch(function () {
                            startSnapshotPolling(snapshotUrl);
                        });
                    } else {
                        // RTSP: Putar via snapshot polling atau stream MJPEG
                        previewNote.textContent = 'Memuat siaran RTSP CCTV...';
                        startSnapshotPolling(snapshotUrl);
                    }
                });
            });

            function startSnapshotPolling(url) {
                previewLoading.style.display = 'none';
                previewImg.style.display = 'block';

                function fetchFrame() {
                    const cacheBuster = url + (url.includes('?') ? '&' : '?') + '_t=' + Date.now();
                    previewImg.src = cacheBuster;
                }

                fetchFrame();
                if (previewPollTimer) clearInterval(previewPollTimer);
                previewPollTimer = setInterval(fetchFrame, 1500);
            }
        });
    </script>
@endsection
