@extends('layouts.app')

@section('title', 'Absen Wajah')
@section('note', 'Kamera absen solat untuk petugas')

@section('content')
    @if ($activeSession === null)
        <div class="alert alert-warning">
            <strong>Belum ada sesi yang dibuka</strong>
            <p>
                Buka sesi terlebih dahulu di halaman Sesi Absen. Selama belum ada sesi yang dibuka,
                absen dari kamera ini akan ditolak.
            </p>
        </div>
    @elseif (!$serviceReady)
        <div class="alert alert-warning">
            <strong>Layanan wajah tidak menyala</strong>
            <p>{{ $serviceMessage }} Nyalakan layanan wajah lebih dulu, lalu muat ulang halaman ini.</p>
        </div>
    @else
        <div class="alert alert-success">
            <strong>Sesi {{ $activeSession->prayerLabel() }} sedang dibuka</strong>
            <p>
                {{ $activeSession->date?->translatedFormat('d F Y') }} jam {{ $activeSession->windowLabel() }}.
                Jamaah cukup berdiri di depan kamera, absen tercatat sendiri.
            </p>
        </div>
    @endif

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

            <div class="form-actions">
                <button class="btn btn-primary" type="button" data-camera-start>Nyalakan kamera</button>
                <button class="btn btn-secondary" type="button" data-camera-stop disabled>Matikan kamera</button>
            </div>

            <p class="camera-status" data-camera-status>Kamera dimatikan.</p>
            <div class="camera-report" data-camera-report></div>
        </div>
    </section>

    <div data-live="absen-wajah">
        <div class="grid-2">
            <section class="card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Sesi hari ini</h2>
                        <p class="section-note">{{ now()->translatedFormat('l, d F Y') }}</p>
                    </div>

                    <div class="section-actions">
                        <a class="btn btn-outline btn-small" href="{{ route('sessions.index') }}">Kelola sesi</a>
                    </div>
                </div>

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
            </section>

            <section class="card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Sepuluh absen terakhir</h2>
                        <p class="section-note">Termasuk absen yang masuk dari perangkat lain.</p>
                    </div>
                </div>

                @if ($recent->isEmpty())
                    <p class="empty-state">Belum ada catatan kehadiran.</p>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Waktu solat</th>
                                    <th>Pukul</th>
                                    <th>Keadaan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recent as $log)
                                    <tr>
                                        <td>
                                            <span class="cell-strong">{{ $log->user?->name ?? 'Anggota terhapus' }}</span>
                                            <span
                                                class="cell-note">{{ $log->user?->class_name ?: $log->user?->roleLabel() }}</span>
                                        </td>
                                        <td>{{ $log->session?->prayerLabel() ?? '-' }}</td>
                                        <td>{{ $log->scanned_at?->format('H:i:s') }}</td>
                                        <td><span
                                                class="status {{ \App\Support\Status::log($log->status) }}">{{ $log->statusLabel() }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
