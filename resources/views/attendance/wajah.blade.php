@extends('layouts.app')

@section('title', 'Absen Wajah')
@section('note', 'Kamera absen solat untuk petugas')

@section('content')
    @php
        $testOpen = $testSession?->isOpen() ?? false;
    @endphp

    @if ($activeSession === null && !$testOpen)
        <div class="alert alert-warning">
            <strong>Belum ada sesi yang dibuka</strong>
            <p>
                Sesi biasanya dibuka sendiri {{ $earlyOpenMinutes }} menit sebelum jam waktu solat. Bila belum
                terbuka, periksa penjadwal aplikasi atau buka sesinya di halaman Sesi Absen. Selama belum ada sesi
                yang dibuka, absen dari kamera ini akan ditolak.
            </p>
        </div>
    @elseif (!$serviceReady)
        <div class="alert alert-warning">
            <strong>Layanan wajah tidak menyala</strong>
            <p>{{ $serviceMessage }} Nyalakan layanan wajah lebih dulu, lalu muat ulang halaman ini.</p>
        </div>
    @elseif ($activeSession !== null)
        <div class="alert alert-success">
            <strong>Sesi {{ $activeSession->prayerLabel() }} sedang dibuka</strong>
            <p>
                {{ $activeSession->date?->translatedFormat('d F Y') }} jam {{ $activeSession->windowLabel() }}.
                Jamaah cukup berdiri di depan kamera, absen tercatat sendiri.
            </p>
        </div>
    @else
        <div class="alert alert-success">
            <strong>Kamera siap menerima percobaan</strong>
            <p>Mode uji coba sedang menyala, jadi absen boleh dicoba sekarang tanpa menunggu waktu solat.</p>
        </div>
    @endif

    @if ($testOpen)
        <div class="alert alert-info">
            <strong>Mode uji coba menyala</strong>
            <p>
                Absen yang masuk pada mode ini dicatat sebagai percobaan, tidak dihitung pada rekap, riwayat, maupun
                ringkasan. Petugas dan anggota yang ditugaskan memindai wajah boleh ikut mencoba, dan orang yang sama
                baru bisa dicoba lagi setelah {{ $testCooldownMinutes }} menit.
                @if ($activeSession !== null)
                    Sebuah sesi waktu solat juga sedang dibuka, jadi absen masuk ke sesi
                    {{ $activeSession->prayerLabel() }}, bukan sebagai percobaan.
                @endif
                Admin mematikan mode ini di halaman Sesi Absen.
            </p>
        </div>
    @endif

    {{-- Kamera dibiarkan di luar wilayah yang menyegarkan diri sendiri. Kalau ikut
         disegarkan, siaran kamera dan pilihan perangkatnya terputus berkala. --}}
    <div class="scan-layout">
        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Kamera absen</h2>
                    <p class="section-note">
                        Kamera menangkap gambar setiap dua detik. Satu wajah dicatat satu kali pada setiap sesi.
                    </p>
                </div>
            </div>

            <div data-camera data-endpoint="{{ route('attendance.wajah.scan') }}" data-interval="2000">
                <div class="camera-frame">
                    <video data-camera-video playsinline muted hidden></video>
                    <canvas data-camera-canvas></canvas>
                    <p class="camera-placeholder" data-camera-placeholder>
                        Kamera belum menyala. Tekan tombol Nyalakan kamera di bawah.
                    </p>
                </div>

                <div class="camera-controls">
                    <div class="field">
                        <label class="field-label" for="kamera-pilihan">Kamera yang dipakai</label>
                        <select class="select-field" id="kamera-pilihan" data-camera-facing>
                            <option value="facing:user">Kamera depan</option>
                            <option value="facing:environment">Kamera belakang</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="button" data-camera-start>Nyalakan kamera</button>
                        <button class="btn btn-secondary" type="button" data-camera-stop disabled>Matikan kamera</button>
                    </div>
                </div>

                <p class="camera-status" data-camera-status>Kamera dimatikan.</p>
                <div class="camera-report" data-camera-report></div>
            </div>
        </section>

        <div class="scan-side" data-live="absen-wajah">
            <section class="card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Log absen</h2>
                        <p class="section-note">Sepuluh catatan terakhir, termasuk yang masuk dari perangkat lain.</p>
                    </div>
                </div>

                @if ($recent->isEmpty())
                    <p class="empty-state">Belum ada catatan kehadiran.</p>
                @else
                    <div class="log-list">
                        @foreach ($recent as $log)
                            @php
                                $identity = $log->user?->class_name ?: $log->user?->roleLabel();
                            @endphp

                            <div class="log-row">
                                @include('partials.proof-photo', ['log' => $log])

                                <div class="log-body">
                                    <div class="log-line">
                                        <span class="log-name">{{ $log->user?->name ?? 'Anggota terhapus' }}</span>
                                        <span class="log-time">{{ $log->scanned_at?->format('H:i:s') }}</span>
                                    </div>

                                    <div class="log-line log-line-meta">
                                        <span class="log-sub">
                                            <span>{{ $log->session?->prayerLabel() ?? 'Tanpa sesi' }}</span>
                                            @if ($identity)
                                                <span>{{ $identity }}</span>
                                            @endif
                                        </span>
                                        <span
                                            class="status {{ \App\Support\Status::log($log->status) }}">{{ $log->statusLabel() }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <details class="tool-panel" data-record="sesi-hari-ini">
                <summary>
                    <span class="tool-name">Sesi hari ini</span>
                    <span class="tool-note">{{ now()->translatedFormat('l, d F Y') }}</span>
                    <span class="tool-hint">
                        <span class="tool-hint-open">Lihat daftar</span>
                        <span class="tool-hint-close">Sembunyikan daftar</span>
                    </span>
                </summary>

                <div class="tool-body">
                    @if ($todaySessions->isEmpty())
                        <p class="empty-state">Belum ada sesi untuk hari ini.</p>
                    @else
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Waktu solat</th>
                                        <th>Jam</th>
                                        <th>Keadaan</th>
                                        <th>Absen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($todaySessions as $session)
                                        <tr>
                                            <td class="cell-strong">{{ $session->prayerLabel() }}</td>
                                            <td>{{ $session->windowLabel() }}</td>
                                            <td><span
                                                    class="status {{ \App\Support\Status::session($session) }}">{{ $session->statusLabel() }}</span>
                                            </td>
                                            <td>{{ $session->logs_count }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="form-actions">
                        <a class="btn btn-outline btn-small" href="{{ route('sessions.index') }}">Kelola sesi</a>
                    </div>
                </div>
            </details>
        </div>
    </div>
@endsection
