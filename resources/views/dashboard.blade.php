@extends('layouts.app')

@section('title', 'Ringkasan')
@section('note', 'Keadaan absen solat hari ini')

@section('content')
    @php
        $sessionService = app(\App\Services\SolatSessionService::class);
        $activeSession = $sessionService->activeSession();
    @endphp
    <style>
        /* ========================================================
           Desain Modern Halaman Ringkasan (Dashboard)
           Sesuai Pedoman Rules.md (No dot indicators, clean UI)
           ======================================================== */
        .dash-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* 1. Header Card */
        .dash-header-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .dash-header-title-box h2 {
            font-size: 19px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .dash-date-badge {
            font-size: 13px;
            font-weight: 600;
            color: #0369a1;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 3px 10px;
            border-radius: 8px;
        }

        .dash-header-title-box p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .dash-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .dash-btn-action {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .dash-btn-primary {
            background: #0284c7;
            color: #ffffff;
            border: 1px solid #0284c7;
        }

        .dash-btn-primary:hover {
            background: #0369a1;
            color: #ffffff;
        }

        .dash-btn-ghost {
            color: #334155;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
        }

        .dash-btn-ghost:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* 2. Top Metric Stats */
        .dash-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        @media (max-width: 992px) {
            .dash-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .dash-stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .dash-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 1px 4px rgba(0, 30, 64, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .dash-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 30, 64, 0.06);
        }

        .dash-stat-card.active-open {
            border-color: #86efac;
            background: #f0fdf4;
        }

        .dash-stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .dash-stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .dash-stat-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
        }

        .dash-stat-pill.pill-green {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .dash-stat-pill.pill-neutral {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .dash-stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 4px;
        }

        .dash-stat-value.value-active {
            color: #166534;
        }

        .dash-stat-subtext {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }

        /* 3. Layout Grid Tengah (Sesi Hari Ini & Tren 7 Hari) */
        .dash-grid-2 {
            display: grid;
            grid-template-columns: 1.25fr 1fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 992px) {
            .dash-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .dash-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
            overflow: hidden;
        }

        .dash-card-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .dash-card-title h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2px 0;
        }

        .dash-card-title p {
            font-size: 12.5px;
            color: #64748b;
            margin: 0;
        }

        /* Tabel Sesi Hari Ini */
        .dash-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .dash-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .dash-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-align: left;
            padding: 12px 18px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .dash-table td {
            padding: 13px 18px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }

        .dash-table tr:hover td {
            background: #fafafa;
        }

        /* Status Badge */
        .dash-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .dash-badge.status-open {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .dash-badge.status-closed {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .dash-badge.status-wait {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .dash-badge.status-late {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Tren 7 Hari List */
        .trend-list {
            padding: 14px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .trend-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .trend-day {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            width: 70px;
        }

        .trend-bar-wrapper {
            flex: 1;
            background: #f1f5f9;
            height: 10px;
            border-radius: 999px;
            overflow: hidden;
            display: flex;
        }

        .trend-bar-fill {
            background: #0284c7;
            border-radius: 999px;
            height: 100%;
            transition: width 0.3s ease;
        }

        .trend-value {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            width: 45px;
            text-align: right;
        }

        /* Empty State */
        .dash-empty {
            padding: 32px 18px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }
    </style>

    <div class="dash-container" data-live="ringkasan">
        {{-- 1. HEADER CARD DENGAN AKSI CEPAT --}}
        <section class="dash-header-card">
            <div class="dash-header-title-box">
                <h2>
                    Ringkasan Presensi
                    <span class="dash-date-badge">{{ now()->translatedFormat('l, d F Y') }}</span>
                </h2>
                <p>Pantau status sesi solat aktif, total presensi harian, dan tren kehadiran jamaah.</p>
            </div>

            <div class="dash-header-actions">
                <a class="dash-btn-action dash-btn-primary" href="{{ route('attendance.wajah') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    Buka Absen Wajah
                </a>
                <a class="dash-btn-action dash-btn-ghost" href="{{ route('sessions.index') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    Sesi Absen
                </a>
                <a class="dash-btn-action dash-btn-ghost" href="{{ route('reports.index') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"></line>
                        <line x1="12" y1="20" x2="12" y2="4"></line>
                        <line x1="6" y1="20" x2="6" y2="14"></line>
                    </svg>
                    Rekap Kehadiran
                </a>
            </div>
        </section>

        {{-- 2. 4 TOP STAT CARDS --}}
        <section class="dash-stats-grid">
            {{-- Stat 1: Sesi Terbuka Sekarang --}}
            <div class="dash-stat-card {{ $activeSession ? 'active-open' : '' }}">
                <div class="dash-stat-top">
                    <span class="dash-stat-label">Sesi Aktif Sekarang</span>
                    @if ($activeSession)
                        <span class="dash-stat-pill pill-green">DIBUKA</span>
                    @else
                        <span class="dash-stat-pill pill-neutral">TIDAK ADA</span>
                    @endif
                </div>
                <div class="dash-stat-value {{ $activeSession ? 'value-active' : '' }}">
                    {{ $activeSession ? $activeSession->prayerLabel() : 'Menunggu' }}
                </div>
                <div class="dash-stat-subtext">
                    {{ $activeSession ? 'Jam ' . $activeSession->windowLabel() : 'Absen terbuka otomatis menjelang waktu solat' }}
                </div>
            </div>

            {{-- Stat 2: Absen Hari Ini --}}
            <div class="dash-stat-card">
                <div class="dash-stat-top">
                    <span class="dash-stat-label">Absen Hari Ini</span>
                    <span class="dash-stat-pill pill-neutral">PRESENSI</span>
                </div>
                <div class="dash-stat-value">
                    {{ $stats['absen'] }}
                </div>
                <div class="dash-stat-subtext">
                    Tercatat lewat kamera wajah & sidik jari
                </div>
            </div>

            {{-- Stat 3: Sesi Hari Ini --}}
            <div class="dash-stat-card">
                <div class="dash-stat-top">
                    <span class="dash-stat-label">Sesi Hari Ini</span>
                    <span class="dash-stat-pill pill-neutral">JADWAL</span>
                </div>
                <div class="dash-stat-value">
                    {{ $stats['sesi'] }}
                </div>
                <div class="dash-stat-subtext">
                    {{ $todaySessions->where('status', \App\Models\SolatSession::StatusSudahDitutup)->count() }} dari {{ $todaySessions->count() }} sesi telah ditutup
                </div>
            </div>

            {{-- Stat 4: Anggota Terdaftar --}}
            <div class="dash-stat-card">
                <div class="dash-stat-top">
                    <span class="dash-stat-label">Anggota Terdaftar</span>
                    <span class="dash-stat-pill pill-neutral">TOTAL</span>
                </div>
                <div class="dash-stat-value">
                    {{ $stats['anggota'] }}
                </div>
                <div class="dash-stat-subtext">
                    {{ $stats['berwajah'] }} anggota sudah memiliki data wajah
                </div>
            </div>
        </section>

        {{-- 3. GRID TENGAH: SESI HARI INI & TREN 7 HARI --}}
        <div class="dash-grid-2">
            {{-- Sesi Hari Ini --}}
            <section class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <h3>Sesi Sholat Hari Ini</h3>
                        <p>Status pembukaan sesi solat pada tanggal {{ now()->translatedFormat('d F Y') }}</p>
                    </div>
                    <a class="dash-btn-action dash-btn-ghost" style="padding: 5px 10px; font-size: 12px;" href="{{ route('sessions.index') }}">
                        Kelola Sesi
                    </a>
                </div>

                @if ($todaySessions->isEmpty())
                    <div class="dash-empty">
                        Belum ada sesi untuk hari ini. Buka menu Sesi Absen untuk menyiapkannya.
                    </div>
                @else
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Waktu Solat</th>
                                    <th>Jam</th>
                                    <th>Status</th>
                                    <th>Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($todaySessions as $session)
                                    <tr>
                                        <td>
                                            <strong style="color: #0f172a;">{{ $session->prayerLabel() }}</strong>
                                        </td>
                                        <td>{{ $session->windowLabel() }}</td>
                                        <td>
                                            @if ($session->isOpen())
                                                <span class="dash-badge status-open">Sedang Dibuka</span>
                                            @elseif ($session->isClosed())
                                                <span class="dash-badge status-closed">Sudah Ditutup</span>
                                            @else
                                                <span class="dash-badge status-wait">Belum Dibuka</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span style="font-weight: 700; color: #0284c7;">{{ $session->logs_count }}</span> Jamaah
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Tren Absen 7 Hari Terakhir --}}
            @php
                $maxWeek = max(collect($week)->pluck('total')->max() ?? 1, 1);
            @endphp
            <section class="dash-card">
                <div class="dash-card-header">
                    <div class="dash-card-title">
                        <h3>Tren Presensi 7 Hari</h3>
                        <p>Jumlah presensi yang tercatat setiap harinya</p>
                    </div>
                </div>

                <div class="trend-list">
                    @foreach ($week as $day)
                        @php
                            $percentage = min(100, max(6, round(($day['total'] / $maxWeek) * 100)));
                        @endphp
                        <div class="trend-item">
                            <span class="trend-day">{{ $day['label'] }}</span>
                            <div class="trend-bar-wrapper">
                                <div class="trend-bar-fill" style="width: {{ $day['total'] > 0 ? $percentage : 0 }}%;"></div>
                            </div>
                            <span class="trend-value">{{ $day['total'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- 4. PRESENSI TERAKHIR MASUK --}}
        <section class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <h3>Presensi Terakhir Masuk</h3>
                    <p>8 catatan kehadiran terbaru dari kamera wajah maupun sidik jari</p>
                </div>
            </div>

            @if ($recent->isEmpty())
                <div class="dash-empty">
                    Belum ada catatan presensi yang masuk hari ini.
                </div>
            @else
                <div class="dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Nama Jamaah</th>
                                <th>Kelas</th>
                                <th>Waktu Solat</th>
                                <th>Waktu Scan</th>
                                <th>Keadaan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $log)
                                <tr>
                                    <td>
                                        <strong style="color: #0f172a; display: block;">{{ $log->user?->name ?? 'Anggota terhapus' }}</strong>
                                        @if ($log->isFingerprint())
                                            <span style="font-size: 11.5px; color: #64748b;">{{ $log->summaryLine() }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->user?->class_name ?: '-' }}</td>
                                    <td>
                                        <span class="dash-badge status-closed">{{ $log->session?->prayerLabel() ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <strong style="color: #0f172a;">{{ $log->scanned_at?->format('d/m H:i:s') }}</strong>
                                    </td>
                                    <td>
                                        <span class="dash-badge {{ $log->status === \App\Models\SolatAttendanceLog::StatusTerlambat ? 'status-late' : 'status-open' }}">
                                            {{ $log->statusLabel() }}
                                        </span>
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
