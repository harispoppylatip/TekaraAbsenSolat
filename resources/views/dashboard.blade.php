@extends('layouts.app')

@section('title', 'Ringkasan')
@section('note', 'Keadaan absen solat hari ini')

@section('content')
    @php
        $sessionService = app(\App\Services\SolatSessionService::class);
        $activeSession = $activeSession ?? $sessionService->activeSession();

        $totalAnggota = max((int) ($stats['anggota'] ?? 0), 1);
        $berwajahCount = (int) ($stats['berwajah'] ?? 0);
        $sidikJariCount = (int) ($stats['bersidik_jari'] ?? 0);
        $faceCoveragePct = min(100, (int) round(($berwajahCount / $totalAnggota) * 100));

        $todayTotal = (int) ($stats['absen'] ?? 0);
        $todayHadir = (int) ($stats['hadir_hari_ini'] ?? $todayTotal);
        $todayTerlambat = (int) ($stats['terlambat_hari_ini'] ?? 0);

        $closedSessionsCount = $todaySessions->where('status', \App\Models\SolatSession::StatusSudahDitutup)->count();
        $openSessionsCount = $todaySessions->where('status', \App\Models\SolatSession::StatusSedangDibuka)->count();

        $weekCollection = collect($week ?? []);
        $weekTotal = (int) $weekCollection->sum('total');
        $weekHadir = (int) $weekCollection->sum(fn ($d) => (int) ($d['hadir'] ?? $d['total'] ?? 0));
        $weekTerlambat = (int) $weekCollection->sum(fn ($d) => (int) ($d['terlambat'] ?? 0));
        $weekAvg = $weekCollection->count() > 0 ? round($weekTotal / $weekCollection->count(), 1) : 0;
        $maxWeekVal = max((int) $weekCollection->max('total'), 10);
        // Bulatkan batas atas sumbu Y ke kelipatan 5 terdekat agar garis grid rapi
        $yAxisMax = (int) (ceil($maxWeekVal / 5) * 5);
        if ($yAxisMax < 10) {
            $yAxisMax = 10;
        }

        $prayerStatsCollection = collect($prayerStats ?? []);
        $maxPrayerTotal = max((int) $prayerStatsCollection->max('total'), 1);

        $methodTotal = max((int) ($methodStats['total'] ?? 0), 0);
        $methodFace = (int) ($methodStats['face'] ?? 0);
        $methodFp = (int) ($methodStats['fingerprint'] ?? 0);
        $methodFacePct = $methodTotal > 0 ? (int) round(($methodFace / $methodTotal) * 100) : 50;
        $methodFpPct = $methodTotal > 0 ? max(0, 100 - $methodFacePct) : 50;
    @endphp

    <style>
        /* ========================================================
           Halaman Ringkasan (Dashboard) — Craft Interface Design
           Sesuai Rules.md & UI-Skills Baseline (Tabular-nums,
           layered elevation, zero dot-indicators, zero gradients)
           ======================================================== */
        .dash-shell {
            display: flex;
            flex-direction: column;
            gap: 20px;
            font-variant-numeric: tabular-nums;
            -webkit-font-smoothing: antialiased;
        }

        .dash-surface {
            background: #ffffff;
            border-radius: 16px;
            box-shadow:
                0 0 0 1px rgba(15, 23, 42, 0.07),
                0 1px 2px -1px rgba(15, 23, 42, 0.05),
                0 2px 6px 0 rgba(15, 23, 42, 0.03);
        }

        /* 1. Command Header */
        .dash-command-bar {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .dash-command-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .dash-command-headline {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .dash-command-headline h2 {
            font-size: 19px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.015em;
            text-wrap: balance;
        }

        .dash-date-chip {
            font-size: 12px;
            font-weight: 600;
            color: #0369a1;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 3px 10px;
            border-radius: 7px;
        }

        .dash-command-sub {
            font-size: 13px;
            color: #64748b;
            margin: 0;
            text-wrap: pretty;
        }

        .dash-command-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .dash-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.14s cubic-bezier(0.23, 1, 0.32, 1), background-color 0.14s ease, border-color 0.14s ease;
        }

        .dash-action-btn:active {
            transform: scale(0.97);
        }

        .dash-action-btn.primary {
            background: #0284c7;
            color: #ffffff;
            border: 1px solid #0284c7;
        }

        .dash-action-btn.primary:hover {
            background: #0369a1;
            border-color: #0369a1;
            color: #ffffff;
        }

        .dash-action-btn.secondary {
            background: #f8fafc;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .dash-action-btn.secondary:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* 2. KPI Grid (4 Cards) */
        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        @media (max-width: 1024px) {
            .dash-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 560px) {
            .dash-kpi-grid {
                grid-template-columns: 1fr;
            }
        }

        .dash-kpi-card {
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 12px;
        }

        .dash-kpi-card.is-live {
            background: #f0fdf4;
            box-shadow:
                0 0 0 1px #86efac,
                0 2px 6px 0 rgba(22, 101, 52, 0.06);
        }

        .dash-kpi-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .dash-kpi-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }

        .dash-kpi-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .dash-kpi-icon.sky {
            background: #f0f9ff;
            color: #0284c7;
        }

        .dash-kpi-icon.emerald {
            background: #dcfce7;
            color: #15803d;
        }

        .dash-kpi-icon.slate {
            background: #f1f5f9;
            color: #475569;
        }

        .dash-kpi-icon.amber {
            background: #fef3c7;
            color: #b45309;
        }

        .dash-kpi-main {
            display: flex;
            align-items: baseline;
            gap: 8px;
            flex-wrap: wrap;
        }

        .dash-kpi-number {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        .dash-kpi-card.is-live .dash-kpi-number {
            color: #166534;
        }

        .dash-kpi-unit {
            font-size: 12.5px;
            font-weight: 600;
            color: #64748b;
        }

        .dash-kpi-foot {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dash-kpi-meta {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
        }

        .dash-mini-track {
            width: 100%;
            height: 6px;
            background: #f1f5f9;
            border-radius: 999px;
            overflow: hidden;
            display: flex;
        }

        .dash-mini-fill {
            height: 100%;
            background: #0284c7;
            border-radius: 999px;
        }

        .dash-mini-segments {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 4px;
        }

        .dash-mini-seg {
            height: 6px;
            border-radius: 4px;
            background: #e2e8f0;
        }

        .dash-mini-seg.open {
            background: #16a34a;
        }

        .dash-mini-seg.closed {
            background: #0284c7;
        }

        /* 3. Charts Section (2 Columns) */
        .dash-charts-grid {
            display: grid;
            grid-template-columns: 1.35fr 1fr;
            gap: 20px;
            align-items: stretch;
        }

        @media (max-width: 1024px) {
            .dash-charts-grid {
                grid-template-columns: 1fr;
            }
        }

        .dash-panel {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .dash-panel-head {
            padding: 18px 22px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .dash-panel-title h3 {
            font-size: 15.5px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2px 0;
            letter-spacing: -0.01em;
        }

        .dash-panel-title p {
            font-size: 12.5px;
            color: #64748b;
            margin: 0;
        }

        .dash-chart-legend {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .dash-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }

        .dash-legend-swatch {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            flex-shrink: 0;
        }

        .dash-legend-swatch.hadir {
            background: #0284c7;
        }

        .dash-legend-swatch.terlambat {
            background: #f59e0b;
        }

        /* Summary Strip inside 7-Day Chart */
        .dash-chart-summary-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            padding: 14px 22px;
            background: #f8fafc;
            border-bottom: 1px solid #f1f5f9;
        }

        .dash-strip-metric {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .dash-strip-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

        .dash-strip-val {
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
        }

        /* Vertical Column Chart Canvas */
        .dash-vchart-body {
            padding: 20px 22px 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .dash-vchart-stage {
            position: relative;
            height: 210px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding-left: 34px;
        }

        .dash-vchart-gridline {
            position: relative;
            width: 100%;
            border-bottom: 1px dashed #e2e8f0;
            height: 0;
        }

        .dash-vchart-gridline:last-of-type {
            border-bottom: 1px solid #cbd5e1;
        }

        .dash-vchart-ylabel {
            position: absolute;
            left: -34px;
            top: -8px;
            width: 28px;
            text-align: right;
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
        }

        .dash-vchart-columns {
            position: absolute;
            inset: 0 0 0 34px;
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 10px;
            align-items: end;
            padding: 0 6px;
        }

        .dash-vcol-group {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            position: relative;
        }

        .dash-vcol-count {
            font-size: 11.5px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 5px;
            line-height: 1;
        }

        .dash-vcol-count.is-zero {
            color: #94a3b8;
            font-weight: 600;
        }

        .dash-vcol-stack {
            width: 100%;
            max-width: 38px;
            border-radius: 7px 7px 3px 3px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            background: #f1f5f9;
            min-height: 6px;
            transition: transform 0.15s ease;
        }

        .dash-vcol-group:hover .dash-vcol-stack {
            transform: scaleY(1.02);
        }

        .dash-vcol-seg-terlambat {
            width: 100%;
            background: #f59e0b;
        }

        .dash-vcol-seg-hadir {
            width: 100%;
            background: #0284c7;
        }

        .dash-vcol-group.is-today .dash-vcol-seg-hadir {
            background: #0369a1;
        }

        .dash-vchart-xlabels {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 10px;
            padding-left: 34px;
            padding-right: 6px;
            margin-top: 10px;
        }

        .dash-vchart-xlabel {
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .dash-vchart-xdate {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }

        .dash-vchart-xlabel.is-today .dash-vchart-xdate {
            color: #0284c7;
        }

        .dash-vchart-xday {
            font-size: 11px;
            color: #94a3b8;
        }

        /* Right Chart: Distribusi Waktu Solat & Metode */
        .dash-dist-body {
            padding: 18px 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 18px;
            flex: 1;
        }

        .dash-prayer-bars {
            display: flex;
            flex-direction: column;
            gap: 13px;
        }

        .dash-pbar-row {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .dash-pbar-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
        }

        .dash-pbar-name {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            display: inline-flex;
            align-items: baseline;
            gap: 6px;
        }

        .dash-pbar-window {
            font-size: 11.5px;
            font-weight: 500;
            color: #94a3b8;
        }

        .dash-pbar-numbers {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }

        .dash-pbar-track {
            width: 100%;
            height: 9px;
            background: #f1f5f9;
            border-radius: 999px;
            overflow: hidden;
            display: flex;
        }

        .dash-pbar-fill-hadir {
            height: 100%;
            background: #0284c7;
        }

        .dash-pbar-fill-late {
            height: 100%;
            background: #f59e0b;
        }

        .dash-method-box {
            padding: 14px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .dash-method-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }

        .dash-method-bar {
            width: 100%;
            height: 10px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            display: flex;
            gap: 2px;
        }

        .dash-method-seg-face {
            height: 100%;
            background: #0284c7;
        }

        .dash-method-seg-fp {
            height: 100%;
            background: #0d9488;
        }

        .dash-method-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 12px;
            color: #475569;
        }

        /* 4. Bottom Operational Grid (Sesi Hari Ini & Presensi Terbaru) */
        .dash-ops-grid {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .dash-ops-grid {
                grid-template-columns: 1fr;
            }
        }

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
            padding: 11px 18px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11.5px;
            text-transform: uppercase;
        }

        .dash-table td {
            padding: 13px 18px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }

        .dash-table tr:last-child td {
            border-bottom: none;
        }

        .dash-table tr:hover td {
            background: #f8fafc;
        }

        .dash-status-tag {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .dash-status-tag.open {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .dash-status-tag.closed {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .dash-status-tag.wait {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .dash-status-tag.late {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Recent Feed List */
        .dash-feed-list {
            display: flex;
            flex-direction: column;
        }

        .dash-feed-item {
            padding: 13px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .dash-feed-item:last-child {
            border-bottom: none;
        }

        .dash-feed-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .dash-feed-avatar {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: #f0f9ff;
            color: #0284c7;
            border: 1px solid #bae6fd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .dash-feed-name {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dash-feed-sub {
            font-size: 12px;
            color: #64748b;
            display: block;
        }

        .dash-feed-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 3px;
            flex-shrink: 0;
        }

        .dash-feed-time {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }

        .dash-empty-box {
            padding: 36px 20px;
            text-align: center;
            color: #64748b;
            font-size: 13px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
    </style>

    <div class="dash-shell" data-live="ringkasan">
        {{-- 1. COMMAND HEADER --}}
        <section class="dash-surface dash-command-bar">
            <div class="dash-command-left">
                <div class="dash-command-headline">
                    <h2>Ringkasan Presensi Sholat</h2>
                    <span class="dash-date-chip">{{ now()->translatedFormat('l, d F Y') }}</span>
                </div>
                <p class="dash-command-sub">
                    Pantau sesi aktif, grafik kehadiran 7 hari terakhir, dan distribusi presensi kamera wajah maupun sidik jari.
                </p>
            </div>

            <div class="dash-command-actions">
                <a class="dash-action-btn primary" href="{{ route('attendance.wajah') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    Buka Absen Wajah
                </a>
                <a class="dash-action-btn secondary" href="{{ route('kiosk.index') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    Layar Monitor
                </a>
                <a class="dash-action-btn secondary" href="{{ route('sessions.index') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    Sesi Absen
                </a>
                <a class="dash-action-btn secondary" href="{{ route('history.index') }}">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    History Bukti
                </a>
            </div>
        </section>

        {{-- 2. EMPAT KARTU METRIK UTAMA --}}
        <section class="dash-kpi-grid" aria-label="Metrik Utama Hari Ini">
            {{-- KPI 1: Sesi Aktif Sekarang --}}
            <div class="dash-surface dash-kpi-card {{ $activeSession ? 'is-live' : '' }}">
                <div class="dash-kpi-top">
                    <span class="dash-kpi-label">Sesi Aktif Sekarang</span>
                    <div class="dash-kpi-icon {{ $activeSession ? 'emerald' : 'slate' }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                </div>
                <div class="dash-kpi-main">
                    <span class="dash-kpi-number">{{ $activeSession ? $activeSession->prayerLabel() : 'Menunggu' }}</span>
                </div>
                <div class="dash-kpi-foot">
                    <div class="dash-kpi-meta">
                        <span>{{ $activeSession ? 'Jendela jam ' . $activeSession->windowLabel() : 'Terbuka otomatis sesuai jadwal' }}</span>
                    </div>
                </div>
            </div>

            {{-- KPI 2: Presensi Hari Ini --}}
            <div class="dash-surface dash-kpi-card">
                <div class="dash-kpi-top">
                    <span class="dash-kpi-label">Presensi Hari Ini</span>
                    <div class="dash-kpi-icon sky">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <polyline points="17 11 19 13 23 9"></polyline>
                        </svg>
                    </div>
                </div>
                <div class="dash-kpi-main">
                    <span class="dash-kpi-number">{{ $todayTotal }}</span>
                    <span class="dash-kpi-unit">kehadiran</span>
                </div>
                <div class="dash-kpi-foot">
                    <div class="dash-kpi-meta">
                        <span style="color: #15803d; font-weight: 600;">{{ $todayHadir }} tepat waktu</span>
                        <span>{{ $todayTerlambat }} terlambat</span>
                    </div>
                </div>
            </div>

            {{-- KPI 3: Progres Sesi Hari Ini --}}
            <div class="dash-surface dash-kpi-card">
                <div class="dash-kpi-top">
                    <span class="dash-kpi-label">Sesi Sholat Hari Ini</span>
                    <div class="dash-kpi-icon amber">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </div>
                </div>
                <div class="dash-kpi-main">
                    <span class="dash-kpi-number">{{ $closedSessionsCount + $openSessionsCount }} / {{ max($stats['sesi'], 5) }}</span>
                    <span class="dash-kpi-unit">sesi berjalan</span>
                </div>
                <div class="dash-kpi-foot">
                    <div class="dash-mini-segments" title="Status 5 Sesi Sholat Hari Ini">
                        @foreach ($todaySessions->take(5) as $s)
                            <span class="dash-mini-seg {{ $s->isOpen() ? 'open' : ($s->isClosed() ? 'closed' : '') }}"></span>
                        @endforeach
                        @for ($i = $todaySessions->count(); $i < 5; $i++)
                            <span class="dash-mini-seg"></span>
                        @endfor
                    </div>
                    <div class="dash-kpi-meta">
                        <span>{{ $closedSessionsCount }} sesi selesai ditutup</span>
                    </div>
                </div>
            </div>

            {{-- KPI 4: Cakupan Biometrik Anggota --}}
            <div class="dash-surface dash-kpi-card">
                <div class="dash-kpi-top">
                    <span class="dash-kpi-label">Anggota & Biometrik</span>
                    <div class="dash-kpi-icon sky">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                </div>
                <div class="dash-kpi-main">
                    <span class="dash-kpi-number">{{ $stats['anggota'] }}</span>
                    <span class="dash-kpi-unit">terdaftar</span>
                </div>
                <div class="dash-kpi-foot">
                    <div class="dash-mini-track">
                        <div class="dash-mini-fill" style="width: {{ $faceCoveragePct }}%;"></div>
                    </div>
                    <div class="dash-kpi-meta">
                        <span>{{ $berwajahCount }} wajah AI</span>
                        <span>{{ $sidikJariCount }} sidik jari</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- 3. GRAFIK ANALITIK (TREN 7 HARI & DISTRIBUSI WAKTU SOLAT) --}}
        <div class="dash-charts-grid">
            {{-- Grafik Kiri: Column Chart Tren Kehadiran 7 Hari --}}
            <section class="dash-surface dash-panel">
                <div class="dash-panel-head">
                    <div class="dash-panel-title">
                        <h3>Grafik Tren Kehadiran 7 Hari Terakhir</h3>
                        <p>Perbandingan presensi hadir tepat waktu dan terlambat per hari</p>
                    </div>
                    <div class="dash-chart-legend">
                        <span class="dash-legend-item">
                            <span class="dash-legend-swatch hadir"></span>
                            Tepat Waktu
                        </span>
                        <span class="dash-legend-item">
                            <span class="dash-legend-swatch terlambat"></span>
                            Terlambat
                        </span>
                    </div>
                </div>

                <div class="dash-chart-summary-strip">
                    <div class="dash-strip-metric">
                        <span class="dash-strip-label">Total 7 Hari</span>
                        <span class="dash-strip-val">{{ $weekTotal }} Presensi</span>
                    </div>
                    <div class="dash-strip-metric">
                        <span class="dash-strip-label">Tepat Waktu / Terlambat</span>
                        <span class="dash-strip-val" style="color: #0284c7;">{{ $weekHadir }} <span style="color: #94a3b8; font-weight: 600;">/</span> <span style="color: #d97706;">{{ $weekTerlambat }}</span></span>
                    </div>
                    <div class="dash-strip-metric">
                        <span class="dash-strip-label">Rata-rata Harian</span>
                        <span class="dash-strip-val">{{ $weekAvg }} / hari</span>
                    </div>
                </div>

                <div class="dash-vchart-body">
                    <div class="dash-vchart-stage">
                        @foreach ([1.0, 0.75, 0.5, 0.25, 0.0] as $ratio)
                            <div class="dash-vchart-gridline">
                                <span class="dash-vchart-ylabel">{{ (int) round($yAxisMax * $ratio) }}</span>
                            </div>
                        @endforeach

                        <div class="dash-vchart-columns">
                            @foreach ($week as $day)
                                @php
                                    $dayTotal = (int) ($day['total'] ?? 0);
                                    $dayHadir = (int) ($day['hadir'] ?? $dayTotal);
                                    $dayLate = (int) ($day['terlambat'] ?? 0);
                                    $heightPct = $dayTotal > 0 ? min(100, max(10, round(($dayTotal / $yAxisMax) * 100))) : 4;
                                    $hadirShare = $dayTotal > 0 ? round(($dayHadir / $dayTotal) * 100) : 0;
                                    $lateShare = $dayTotal > 0 ? max(0, 100 - $hadirShare) : 0;
                                    $isToday = ! empty($day['is_today']);
                                @endphp
                                <div class="dash-vcol-group {{ $isToday ? 'is-today' : '' }}"
                                    title="{{ $day['full_date'] ?? $day['label'] }}: {{ $dayTotal }} presensi ({{ $dayHadir }} tepat waktu, {{ $dayLate }} terlambat)">
                                    <span class="dash-vcol-count {{ $dayTotal === 0 ? 'is-zero' : '' }}">{{ $dayTotal }}</span>
                                    <div class="dash-vcol-stack" style="height: {{ $heightPct }}%;">
                                        @if ($dayLate > 0)
                                            <div class="dash-vcol-seg-terlambat" style="height: {{ $lateShare }}%;"></div>
                                        @endif
                                        @if ($dayHadir > 0)
                                            <div class="dash-vcol-seg-hadir" style="height: {{ $hadirShare }}%;"></div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="dash-vchart-xlabels">
                        @foreach ($week as $day)
                            <div class="dash-vchart-xlabel {{ ! empty($day['is_today']) ? 'is-today' : '' }}">
                                <span class="dash-vchart-xdate">{{ $day['label'] }}</span>
                                <span class="dash-vchart-xday">{{ ! empty($day['is_today']) ? 'Hari Ini' : ($day['day_short'] ?? '') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Grafik Kanan: Distribusi per Waktu Sholat & Metode Presensi --}}
            <section class="dash-surface dash-panel">
                <div class="dash-panel-head">
                    <div class="dash-panel-title">
                        <h3>Distribusi per Waktu Sholat</h3>
                        <p>Akumulasi kehadiran di setiap jadwal sholat & metode alat</p>
                    </div>
                </div>

                <div class="dash-dist-body">
                    <div class="dash-prayer-bars">
                        @foreach ($prayerStatsCollection as $pStat)
                            @php
                                $pTotal = (int) $pStat['total'];
                                $pHadir = (int) $pStat['hadir'];
                                $pLate = (int) $pStat['terlambat'];
                                $pHadirWidth = $pTotal > 0 ? round(($pHadir / $maxPrayerTotal) * 100) : 0;
                                $pLateWidth = $pTotal > 0 ? round(($pLate / $maxPrayerTotal) * 100) : 0;
                            @endphp
                            <div class="dash-pbar-row">
                                <div class="dash-pbar-head">
                                    <span class="dash-pbar-name">
                                        {{ $pStat['label'] }}
                                        <span class="dash-pbar-window">{{ $pStat['window'] }}</span>
                                    </span>
                                    <span class="dash-pbar-numbers">
                                        <strong>{{ $pTotal }}</strong> hadir
                                        @if ($pLate > 0)
                                            <span style="color: #d97706;">({{ $pLate }} terlambat)</span>
                                        @endif
                                    </span>
                                </div>
                                <div class="dash-pbar-track">
                                    @if ($pHadir > 0)
                                        <div class="dash-pbar-fill-hadir" style="width: {{ max(4, $pHadirWidth) }}%;"></div>
                                    @endif
                                    @if ($pLate > 0)
                                        <div class="dash-pbar-fill-late" style="width: {{ max(4, $pLateWidth) }}%;"></div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="dash-method-box">
                        <div class="dash-method-head">
                            <span>Komposisi Metode Presensi</span>
                            <span>{{ $methodTotal }} Total Log</span>
                        </div>
                        <div class="dash-method-bar">
                            <div class="dash-method-seg-face" style="width: {{ $methodFacePct }}%;" title="Kamera Wajah AI: {{ $methodFace }}"></div>
                            <div class="dash-method-seg-fp" style="width: {{ $methodFpPct }}%;" title="Sensor Sidik Jari: {{ $methodFp }}"></div>
                        </div>
                        <div class="dash-method-foot">
                            <span style="display: inline-flex; align-items: center; gap: 6px;">
                                <span style="width: 9px; height: 9px; border-radius: 2px; background: #0284c7; display: inline-block;"></span>
                                Kamera Wajah: <strong>{{ $methodFace }}</strong>
                            </span>
                            <span style="display: inline-flex; align-items: center; gap: 6px;">
                                <span style="width: 9px; height: 9px; border-radius: 2px; background: #0d9488; display: inline-block;"></span>
                                Sidik Jari: <strong>{{ $methodFp }}</strong>
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- 4. GRID OPERASIONAL: SESI HARI INI & PRESENSI TERBARU --}}
        <div class="dash-ops-grid">
            {{-- Tabel Sesi Sholat Hari Ini --}}
            <section class="dash-surface dash-panel">
                <div class="dash-panel-head">
                    <div class="dash-panel-title">
                        <h3>Sesi Sholat Hari Ini</h3>
                        <p>Status pelaksanaan 5 waktu sholat pada {{ now()->translatedFormat('d F Y') }}</p>
                    </div>
                    <a class="dash-action-btn secondary" style="padding: 6px 12px; font-size: 12px;" href="{{ route('sessions.index') }}">
                        Kelola Sesi
                    </a>
                </div>

                @if ($todaySessions->isEmpty())
                    <div class="dash-empty-box">
                        <span>Belum ada sesi sholat untuk hari ini.</span>
                        <a class="dash-action-btn primary" style="padding: 6px 12px; font-size: 12px;" href="{{ route('sessions.index') }}">
                            Siapkan Sesi Hari Ini
                        </a>
                    </div>
                @else
                    <div class="dash-table-wrap">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Waktu Solat</th>
                                    <th>Jendela Jam</th>
                                    <th>Status</th>
                                    <th>Kehadiran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($todaySessions as $session)
                                    <tr>
                                        <td>
                                            <strong style="color: #0f172a; font-weight: 700;">{{ $session->prayerLabel() }}</strong>
                                        </td>
                                        <td style="color: #475569; font-weight: 500;">{{ $session->windowLabel() }}</td>
                                        <td>
                                            @if ($session->isOpen())
                                                <span class="dash-status-tag open">Sedang Dibuka</span>
                                            @elseif ($session->isClosed())
                                                <span class="dash-status-tag closed">Sudah Ditutup</span>
                                            @else
                                                <span class="dash-status-tag wait">Belum Dibuka</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span style="font-weight: 800; color: #0284c7;">{{ $session->logs_count }}</span>
                                            <span style="color: #64748b; font-size: 12px;">Jamaah</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Presensi Terakhir Masuk --}}
            <section class="dash-surface dash-panel">
                <div class="dash-panel-head">
                    <div class="dash-panel-title">
                        <h3>Presensi Terakhir Masuk</h3>
                        <p>Catatan kehadiran terbaru dari kamera wajah & sensor sidik jari</p>
                    </div>
                    <a class="dash-action-btn secondary" style="padding: 6px 12px; font-size: 12px;" href="{{ route('history.index') }}">
                        Lihat Semua
                    </a>
                </div>

                @if ($recent->isEmpty())
                    <div class="dash-empty-box">
                        <span>Belum ada presensi yang tercatat.</span>
                        <a class="dash-action-btn secondary" style="padding: 6px 12px; font-size: 12px;" href="{{ route('attendance.wajah') }}">
                            Mulai Pindai Wajah
                        </a>
                    </div>
                @else
                    <div class="dash-feed-list">
                        @foreach ($recent as $log)
                            <div class="dash-feed-item">
                                <div class="dash-feed-left">
                                    <div class="dash-feed-avatar">
                                        {{ strtoupper(substr($log->user?->name ?? 'J', 0, 1)) }}
                                    </div>
                                    <div style="min-width: 0;">
                                        <span class="dash-feed-name">{{ $log->user?->name ?? 'Anggota terhapus' }}</span>
                                        <span class="dash-feed-sub">
                                            {{ $log->session?->prayerLabel() ?? 'Sholat' }}
                                            · {{ $log->isFingerprint() ? 'Sidik Jari' : 'Kamera Wajah' }}
                                            @if ($log->user?->class_name)
                                                · {{ $log->user->class_name }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="dash-feed-right">
                                    <span class="dash-feed-time">{{ $log->scanned_at?->format('d/m H:i') }}</span>
                                    <span class="dash-status-tag {{ $log->status === \App\Models\SolatAttendanceLog::StatusTerlambat ? 'late' : 'open' }}">
                                        {{ $log->statusLabel() }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
