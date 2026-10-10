@extends('layouts.app')

@section('title', 'Ringkasan')
@section('note', 'Keadaan absen solat hari ini')

@section('content')
    <div data-live="ringkasan">
        <section class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Anggota terdaftar</p>
                <p class="stat-value">{{ $stats['anggota'] }}</p>
                <p class="stat-note">{{ $stats['berwajah'] }} sudah punya data wajah</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Sesi hari ini</p>
                <p class="stat-value">{{ $stats['sesi'] }}</p>
                <p class="stat-note">
                    {{ $todaySessions->where('status', \App\Models\SolatSession::StatusSudahDitutup)->count() }} sudah
                    ditutup</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Absen hari ini</p>
                <p class="stat-value">{{ $stats['absen'] }}</p>
                <p class="stat-note">Dicatat dari wajah jamaah</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Sesi sedang dibuka</p>
                <p class="stat-value">{{ $activeSession ? $activeSession->prayerLabel() : 'Belum ada' }}</p>
                <p class="stat-note">
                    {{ $activeSession ? 'Jam ' . $activeSession->windowLabel() : 'Terbuka sendiri menjelang waktu solat' }}
                </p>
            </div>
        </section>

        <div class="grid-2">
            <section class="card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Sesi hari ini</h2>
                        <p class="section-note">{{ now()->translatedFormat('l, d F Y') }}</p>
                    </div>

                    <div class="section-actions">
                        <a class="btn btn-outline btn-small" href="{{ route('sessions.index') }}">Buka halaman sesi</a>
                    </div>
                </div>

                @if ($todaySessions->isEmpty())
                    <p class="empty-state">Belum ada sesi untuk hari ini. Sesi dibuat sendiri menjelang jam waktu
                        solat, dan bisa disiapkan lebih dulu lewat halaman Sesi Absen.</p>
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
                        <h2 class="section-title">Absen tujuh hari terakhir</h2>
                        <p class="section-note">Jumlah catatan kehadiran setiap hari</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jumlah absen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($week as $day)
                                <tr>
                                    <td>{{ $day['label'] }}</td>
                                    <td class="cell-strong">{{ $day['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Absen terakhir masuk</h2>
                    <p class="section-note">Delapan catatan paling baru</p>
                </div>
            </div>

            @if ($recent->isEmpty())
                <p class="empty-state">Belum ada catatan kehadiran. Buka sesi lalu lakukan absen wajah.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>Waktu solat</th>
                                <th>Pukul</th>
                                <th>Keadaan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $log)
                                <tr>
                                    <td class="cell-strong">{{ $log->user?->name ?? 'Anggota terhapus' }}</td>
                                    <td>{{ $log->user?->class_name ?: '-' }}</td>
                                    <td>{{ $log->session?->prayerLabel() ?? '-' }}</td>
                                    <td>{{ $log->scanned_at?->format('d/m H:i') }}</td>
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
@endsection
