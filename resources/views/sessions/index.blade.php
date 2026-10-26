@extends('layouts.app')

@section('title', 'Sesi Absen')
@section('note', 'Sesi absen dibuka dan ditutup sendiri setiap waktu solat')

@php($sessionService = app(\App\Services\SolatSessionService::class))
@php($isAdmin = auth()->user()?->isAdmin() ?? false)

@section('content')
    <style>
        /* ========================================================
           Desain Modern Halaman Sesi Absen Sholat
           Sesuai Pedoman Rules.md (No dot indicators, clean UI)
           ======================================================== */
        .session-mgmt-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* 1. Header & Date Control Bar */
        .session-header-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
        }

        .session-header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 18px;
        }

        .session-header-title-box h2 {
            font-size: 19px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .session-date-badge {
            font-size: 13px;
            font-weight: 600;
            color: #0369a1;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 3px 10px;
            border-radius: 8px;
        }

        .session-header-title-box p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .session-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-action-ghost {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-action-ghost:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* Date Navigation Bar */
        .session-date-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        .date-nav-group {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-date-nav {
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: #475569;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s ease;
        }

        .btn-date-nav:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .btn-date-nav.active-today {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
        }

        .date-input-wrapper {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .session-date-input {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            background: #ffffff;
            outline: none;
            transition: border-color 0.15s ease;
        }

        .session-date-input:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        .btn-generate-session {
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: #ffffff;
            background: #0f172a;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.15s ease;
        }

        .btn-generate-session:hover {
            background: #1e293b;
        }

        /* Holiday Alert */
        .holiday-alert-banner {
            margin-top: 14px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .holiday-alert-icon {
            color: #d97706;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .holiday-alert-text {
            font-size: 13px;
            color: #92400e;
            line-height: 1.5;
        }

        .holiday-alert-text strong {
            display: block;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        /* 2. Top Metric Stats */
        .session-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        @media (max-width: 992px) {
            .session-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .session-stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .session-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 1px 4px rgba(0, 30, 64, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .session-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 30, 64, 0.06);
        }

        .session-stat-card.active-open {
            border-color: #86efac;
            background: #f0fdf4;
        }

        .session-stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .session-stat-label {
            font-size: 12.5px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .session-stat-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
        }

        .session-stat-pill.pill-green {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .session-stat-pill.pill-neutral {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .session-stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 4px;
        }

        .session-stat-value.value-active {
            color: #166534;
        }

        .session-stat-subtext {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }

        /* 3. Mode Uji Coba Absen (Clean & Compact Bar) */
        .test-session-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 20px;
            box-shadow: 0 1px 4px rgba(0, 30, 64, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .test-session-bar.is-active {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .test-session-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 260px;
        }

        .test-session-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #f1f5f9;
            color: #475569;
        }

        .test-session-bar.is-active .test-session-icon {
            background: #dcfce7;
            color: #15803d;
        }

        .test-session-info h3 {
            font-size: 14.5px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .test-session-info p {
            font-size: 12.5px;
            color: #64748b;
            margin: 0;
        }

        .test-session-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-test-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-test-action.btn-test-start {
            background: #0284c7;
            color: #ffffff;
        }

        .btn-test-action.btn-test-start:hover {
            background: #0369a1;
        }

        .btn-test-action.btn-test-stop {
            background: #ffffff;
            color: #b45309;
            border-color: #fde68a;
        }

        .btn-test-action.btn-test-stop:hover {
            background: #fffbeb;
            color: #92400e;
        }

        .btn-test-action.btn-test-delete {
            background: #ffffff;
            color: #dc2626;
            border-color: #fecaca;
        }

        .btn-test-action.btn-test-delete:hover {
            background: #fef2f2;
            color: #b91c1c;
        }

        /* 4. Two Columns Layout for Sesi List & Logs */
        .session-main-layout {
            display: grid;
            grid-template-columns: 1fr 1.35fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 1080px) {
            .session-main-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Card Panels */
        .session-panel-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
            overflow: hidden;
        }

        .session-panel-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .session-panel-title-group h2 {
            font-size: 16.5px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px 0;
        }

        .session-panel-title-group p {
            font-size: 12.5px;
            color: #64748b;
            margin: 0;
        }

        /* Sesi Item List Cards */
        .session-item-list {
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .session-item-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            transition: all 0.15s ease;
            position: relative;
        }

        .session-item-card:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .session-item-card.is-selected {
            border-color: #0284c7;
            background: #f0f9ff;
            box-shadow: 0 0 0 1px #0284c7;
        }

        .session-item-card.is-open {
            border-left: 4px solid #16a34a;
        }

        .session-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }

        .session-item-check {
            flex-shrink: 0;
        }

        .session-item-check input[type="checkbox"] {
            width: 17px;
            height: 17px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            cursor: pointer;
        }

        .session-item-info {
            min-width: 0;
            flex: 1;
        }

        .session-item-title-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }

        .session-prayer-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .session-status-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .session-status-badge.status-open {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .session-status-badge.status-closed {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .session-status-badge.status-wait {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .session-status-badge.status-selected {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .session-item-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            color: #64748b;
            flex-wrap: wrap;
        }

        .session-time-text {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            color: #334155;
        }

        .session-count-text {
            font-weight: 600;
            color: #0284c7;
        }

        .session-late-text {
            color: #dc2626;
            font-weight: 600;
        }

        .session-item-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            flex-wrap: wrap;
        }

        .btn-session-mini {
            padding: 6px 11px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s ease;
        }

        .btn-session-mini.btn-view {
            background: #f8fafc;
            color: #334155;
            border-color: #cbd5e1;
        }

        .btn-session-mini.btn-view:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .btn-session-mini.btn-view.is-active {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
        }

        .btn-session-mini.btn-open {
            background: #16a34a;
            color: #ffffff;
        }

        .btn-session-mini.btn-open:hover {
            background: #15803d;
        }

        .btn-session-mini.btn-close {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .btn-session-mini.btn-close:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-session-mini.btn-del {
            background: #ffffff;
            color: #94a3b8;
            border-color: #e2e8f0;
            padding: 6px 8px;
        }

        .btn-session-mini.btn-del:hover {
            color: #dc2626;
            border-color: #fca5a5;
            background: #fef2f2;
        }

        /* Bulk session actions bar */
        .session-bulk-bar {
            padding: 10px 16px;
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Catatan Kehadiran Panel */
        .logs-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .logs-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .logs-table td {
            padding: 13px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }

        .logs-table tr:hover td {
            background: #fafafa;
        }

        .log-user-name {
            font-weight: 600;
            color: #0f172a;
            display: block;
        }

        .log-user-sub {
            font-size: 11.5px;
            color: #64748b;
        }

        .log-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .log-badge.status-ontime {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .log-badge.status-late {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Empty States */
        .session-empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #64748b;
        }

        .session-empty-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #94a3b8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }

        .session-empty-title {
            font-size: 14.5px;
            font-weight: 700;
            color: #334155;
            margin: 0 0 4px 0;
        }

        .session-empty-desc {
            font-size: 12.5px;
            color: #64748b;
            max-width: 380px;
            margin: 0 auto;
            line-height: 1.45;
        }

        /* Collapsible Info Box */
        .info-disclosure {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            margin-top: 14px;
        }

        .info-disclosure summary {
            font-size: 12.5px;
            font-weight: 600;
            color: #0284c7;
            cursor: pointer;
            outline: none;
            user-select: none;
        }

        .info-disclosure summary:hover {
            color: #0369a1;
        }

        .info-disclosure-body {
            font-size: 12.5px;
            color: #475569;
            line-height: 1.5;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #cbd5e1;
        }
    </style>

    <div class="session-mgmt-container">
        {{-- ========================================================
             1. HEADER & DATE CONTROLS
             ======================================================== --}}
        <section class="session-header-card">
            <div class="session-header-top">
                <div class="session-header-title-box">
                    <h2>
                        Sesi Absen Sholat
                        <span class="session-date-badge">{{ $date->translatedFormat('l, d F Y') }}</span>
                    </h2>
                    <p>Sesi dibuka dan ditutup otomatis sesuai jadwal waktu solat sekolah.</p>
                </div>

                <div class="session-header-actions">
                    @if ($isAdmin)
                        <a class="btn-action-ghost" href="{{ route('prayer-times.index') }}#hari-absen">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            Atur Hari Absen
                        </a>
                    @endif
                    <a class="btn-action-ghost" href="{{ route('attendance.wajah') }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                        Absen Wajah
                    </a>
                    <a class="btn-action-ghost" href="{{ route('reports.index', ['from' => $date->toDateString(), 'to' => $date->toDateString()]) }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                        Rekap Harian
                    </a>
                </div>
            </div>

            {{-- Date Navigation Toolbar --}}
            <div class="session-date-toolbar">
                <div class="date-nav-group">
                    <a class="btn-date-nav" href="{{ route('sessions.index', ['date' => $date->copy()->subDay()->toDateString()]) }}" title="Lihat hari sebelumnya">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                        Kemarin
                    </a>
                    <a class="btn-date-nav {{ $date->isToday() ? 'active-today' : '' }}" href="{{ route('sessions.index', ['date' => now()->toDateString()]) }}" title="Ke tanggal hari ini">
                        Hari Ini
                    </a>
                    <a class="btn-date-nav" href="{{ route('sessions.index', ['date' => $date->copy()->addDay()->toDateString()]) }}" title="Lihat hari berikutnya">
                        Besok
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>

                    <form method="GET" action="{{ route('sessions.index') }}" class="date-input-wrapper">
                        <input class="session-date-input" type="date" id="date" name="date" value="{{ $date->toDateString() }}" data-auto-submit>
                    </form>
                </div>

                @if ($isActiveDay || $isAdmin)
                    <form method="POST" action="{{ route('sessions.generate') }}" style="display: inline-flex;">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                        <button class="btn-generate-session" id="generate" type="submit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                            Buat Sesi Tanggal Ini
                        </button>
                    </form>
                @endif
            </div>

            {{-- Holiday Alert if applicable --}}
            @if (! $isActiveDay)
                <div class="holiday-alert-banner">
                    <svg class="holiday-alert-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <div class="holiday-alert-text">
                        <strong>Hari {{ $date->translatedFormat('l') }} Termasuk Hari Libur Absen</strong>
                        Jadwal rutin sekolah: {{ $daysSummary['summary'] }}. Sesi otomatis tidak dibuat pada hari libur, namun Admin tetap dapat membuat atau membuka sesi secara mandiri jika ada agenda sekolah.
                    </div>
                </div>
            @endif

            {{-- Collapsible info disclosure --}}
            <details class="info-disclosure">
                <summary>Petunjuk & Cara Kerja Jadwal Otomatis</summary>
                <div class="info-disclosure-body">
                    Absen hanya tercatat saat sesi solat dibuka. Sistem secara otomatis menyiapkan sesi untuk hari ini dan besok setelah tengah malam, membuka sesi <strong>{{ $sessionService::earlyOpenMinutes() }} menit</strong> sebelum jadwal mulai, dan menutupnya otomatis di akhir jendela waktu solat. Tombol Buka dan Tutup manual tetap tersedia jika sesi perlu dimulai atau diakhiri lebih awal.
                </div>
            </details>
        </section>

        {{-- ========================================================
             DYNAMIC CONTENT (Live Polling Target)
             ======================================================== --}}
        <div data-live="sesi-absen" style="display: flex; flex-direction: column; gap: 20px;">
            {{-- 2. TOP METRIC STATS --}}
            <div class="session-stats-grid">
                {{-- Stat 1: Sesi Terbuka Sekarang (Highlight Utama) --}}
                <div class="session-stat-card {{ $activeSession ? 'active-open' : '' }}">
                    <div class="session-stat-top">
                        <span class="session-stat-label">Sesi Aktif Sekarang</span>
                        @if ($activeSession)
                            <span class="session-stat-pill pill-green">DIBUKA</span>
                        @else
                            <span class="session-stat-pill pill-neutral">TIDAK ADA</span>
                        @endif
                    </div>
                    <div class="session-stat-value {{ $activeSession ? 'value-active' : '' }}">
                        {{ $activeSession ? $activeSession->prayerLabel() : 'Menunggu' }}
                    </div>
                    <div class="session-stat-subtext">
                        @if ($activeSession)
                            Jam {{ $activeSession->windowLabel() }} (sejak {{ $activeSession->opened_at?->format('H:i') }})
                        @else
                            Absen tidak menerima catatan saat ini
                        @endif
                    </div>
                </div>

                {{-- Stat 2: Catatan Kehadiran Hari Ini --}}
                <div class="session-stat-card">
                    <div class="session-stat-top">
                        <span class="session-stat-label">Total Hadir Tanggal Ini</span>
                        <span class="session-stat-pill pill-neutral">SCAN</span>
                    </div>
                    <div class="session-stat-value">
                        {{ $sessions->sum('logs_count') }}
                    </div>
                    <div class="session-stat-subtext">
                        @if ($sessions->sum('late_count') > 0)
                            <span style="color: #dc2626; font-weight: 600;">{{ $sessions->sum('late_count') }}</span> di antaranya terlambat
                        @else
                            Semua hadir tepat waktu
                        @endif
                    </div>
                </div>

                {{-- Stat 3: Sesi Tanggal Ini --}}
                <div class="session-stat-card">
                    <div class="session-stat-top">
                        <span class="session-stat-label">Sesi Solat</span>
                        <span class="session-stat-pill pill-neutral">JADWAL</span>
                    </div>
                    <div class="session-stat-value">
                        {{ $sessions->count() }}
                    </div>
                    <div class="session-stat-subtext">
                        Dari {{ $prayerOptions->where('is_active', true)->count() }} waktu solat aktif sekolah
                    </div>
                </div>

                {{-- Stat 4: Jamaah Terdaftar --}}
                <div class="session-stat-card">
                    <div class="session-stat-top">
                        <span class="session-stat-label">Jamaah Terdaftar</span>
                        <span class="session-stat-pill pill-neutral">SISWA</span>
                    </div>
                    <div class="session-stat-value">
                        {{ $memberTotal }}
                    </div>
                    <div class="session-stat-subtext">
                        Total akun murid yang wajib absen
                    </div>
                </div>
            </div>

            {{-- 3. MODE UJI COBA ABSEN (Compact & Clean) --}}
            @if ($isAdmin || $testSession !== null)
                <div class="test-session-bar {{ $testSession !== null ? 'is-active' : '' }}">
                    <div class="test-session-left">
                        <div class="test-session-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <path d="m9 15 2 2 4-4"></path>
                            </svg>
                        </div>
                        <div class="test-session-info">
                            <h3>
                                Mode Uji Coba Absen
                                @if ($testSession !== null)
                                    <span class="session-status-badge status-open">MENYALA</span>
                                @else
                                    <span class="session-status-badge status-closed">NONAKTIF</span>
                                @endif
                            </h3>
                            <p>
                                @if ($testSession !== null)
                                    Aktif sejak pukul {{ $testSession->opened_at?->format('H:i') }} ({{ $testCount }} scan masuk). Petugas & jamaah dapat uji scan kapan saja.
                                @else
                                    Uji kamera absen kapan saja tanpa membuka sesi solat resmi (jeda {{ $testCooldownMinutes }} menit/orang).
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="test-session-actions">
                        @if ($testSession === null)
                            @if ($isAdmin)
                                @if ($activeSession !== null)
                                    <span style="font-size: 12px; color: #b45309;">Sesi {{ $activeSession->prayerLabel() }} sedang buka. Tutup dulu untuk uji coba.</span>
                                @else
                                    <form method="POST" action="{{ route('sessions.test.start') }}">
                                        @csrf
                                        <button class="btn-test-action btn-test-start" type="submit">
                                            Nyalakan Uji Coba
                                        </button>
                                    </form>
                                @endif
                            @endif
                        @else
                            @if ($isAdmin)
                                <form method="POST" action="{{ route('sessions.test.stop') }}">
                                    @csrf
                                    @method('PUT')
                                    <button class="btn-test-action btn-test-stop" type="submit">
                                        Matikan Uji Coba
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('sessions.test.destroy') }}"
                                    data-confirm="Hapus sesi uji coba beserta {{ $testCount }} catatan percobaan dan foto buktinya?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-test-action btn-test-delete" type="submit">
                                        Hapus Sesi Uji Coba
                                    </button>
                                </form>
                            @endif
                            <a class="btn-test-action" style="background: #f1f5f9; color: #334155;" href="{{ route('attendance.wajah') }}">
                                Buka Kamera
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Preview log uji coba bila ada dan aktif --}}
                @if ($testSession !== null && $testLogs->isNotEmpty())
                    <details class="info-disclosure" style="margin-top: -10px; background: #ffffff;">
                        <summary>Lihat {{ $testLogs->count() }} Hasil Scan Uji Coba Terakhir (Total: {{ $testCount }})</summary>
                        <div class="logs-table-wrap" style="margin-top: 10px;">
                            <table class="logs-table">
                                <thead>
                                    <tr>
                                        <th>Nama Jamaah</th>
                                        <th>Kelas</th>
                                        <th>Pukul</th>
                                        <th>Keadaan</th>
                                        <th>Bukti</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($testLogs as $log)
                                        <tr>
                                            <td>
                                                <span class="log-user-name">{{ $log->user?->name ?? 'Anggota terhapus' }}</span>
                                                @if ($log->isFingerprint())
                                                    <span class="log-user-sub">{{ $log->summaryLine() }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $log->user?->class_name ?: '-' }}</td>
                                            <td>{{ $log->scanned_at?->format('H:i:s') }}</td>
                                            <td>
                                                <span class="log-badge {{ $log->status === \App\Models\SolatAttendanceLog::StatusTerlambat ? 'status-late' : 'status-ontime' }}">
                                                    {{ $log->statusLabel() }}
                                                </span>
                                            </td>
                                            <td>
                                                @include('partials.proof-photo', [
                                                    'log' => $log,
                                                    'proofFallback' => 'Tanpa bukti',
                                                ])
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            @endif

            {{-- 4. TWO COLUMNS LAYOUT: SESI LIST (KIRI) & CATATAN KEHADIRAN (KANAN) --}}
            <div class="session-main-layout">
                {{-- KOLOM KIRI: DAFTAR SESI --}}
                <div class="session-panel-card">
                    <div class="session-panel-header">
                        <div class="session-panel-title-group">
                            <h2>Daftar Sesi Sholat</h2>
                            <p>Pilih salah satu sesi untuk meninjau log kehadiran jamaah.</p>
                        </div>

                        @if ($isAdmin && $sessions->isNotEmpty())
                            <form method="POST" action="{{ route('sessions.destroy-day') }}"
                                data-confirm="Hapus semua sesi tanggal {{ $date->translatedFormat('d F Y') }} beserta seluruh catatan kehadirannya?">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                                <button class="btn-session-mini btn-del" type="submit" title="Hapus semua sesi tanggal ini">
                                    Hapus Semua Sesi
                                </button>
                            </form>
                        @endif
                    </div>

                    @if ($sessions->isEmpty())
                        <div class="session-empty-state">
                            <div class="session-empty-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </div>
                            <h3 class="session-empty-title">Belum Ada Sesi</h3>
                            <p class="session-empty-desc">
                                Sesi solat disiapkan otomatis tiap tengah malam. Tekan tombol <strong>Buat Sesi Tanggal Ini</strong> di atas jika ingin membuatnya sekarang.
                            </p>
                        </div>
                    @else
                        {{-- Form container for bulk delete --}}
                        <form id="hapus-sesi-terpilih" method="POST" action="{{ route('sessions.destroy-selected') }}"
                            data-confirm="Hapus sesi yang dicentang? Catatan kehadiran dan foto bukti ikut terhapus.">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                        </form>

                        <div class="session-item-list">
                            @foreach ($sessions as $session)
                                @php($isSelected = $selected && $selected->is($session))
                                <div class="session-item-card {{ $isSelected ? 'is-selected' : '' }} {{ $session->isOpen() ? 'is-open' : '' }}">
                                    <div class="session-item-left">
                                        @if ($isAdmin)
                                            <div class="session-item-check">
                                                <input type="checkbox" name="sessions[]" value="{{ $session->id }}"
                                                    form="hapus-sesi-terpilih"
                                                    aria-label="Pilih sesi {{ $session->prayerLabel() }}">
                                            </div>
                                        @endif

                                        <div class="session-item-info">
                                            <div class="session-item-title-row">
                                                <span class="session-prayer-name">{{ $session->prayerLabel() }}</span>

                                                @if ($session->isOpen())
                                                    <span class="session-status-badge status-open">Sedang Dibuka</span>
                                                @elseif ($session->isClosed())
                                                    <span class="session-status-badge status-closed">Sudah Ditutup</span>
                                                @else
                                                    <span class="session-status-badge status-wait">Belum Dibuka</span>
                                                @endif

                                                @if ($isSelected)
                                                    <span class="session-status-badge status-selected">Dipilih</span>
                                                @endif
                                            </div>

                                            <div class="session-item-meta">
                                                <span class="session-time-text">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <polyline points="12 6 12 12 16 14"></polyline>
                                                    </svg>
                                                    {{ $session->windowLabel() }}
                                                </span>
                                                <span>&bull;</span>
                                                <span class="session-count-text">
                                                    {{ $session->logs_count }} Hadir
                                                </span>
                                                @if ($session->late_count > 0)
                                                    <span>&bull;</span>
                                                    <span class="session-late-text">
                                                        {{ $session->late_count }} Terlambat
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="session-item-actions">
                                        {{-- Tombol Pilih/Lihat Catatan --}}
                                        <a class="btn-session-mini btn-view {{ $isSelected ? 'is-active' : '' }}"
                                            href="{{ route('sessions.index', ['date' => $date->toDateString(), 'session' => $session->id]) }}">
                                            {{ $isSelected ? 'Dilihat' : 'Lihat' }}
                                        </a>

                                        {{-- Tombol Buka / Tutup --}}
                                        @if ($session->isOpen())
                                            <form method="POST" action="{{ route('sessions.close', $session) }}"
                                                data-confirm="Tutup sesi {{ $session->prayerLabel() }}? Absen tidak akan menerima catatan baru.">
                                                @csrf
                                                @method('PUT')
                                                <button class="btn-session-mini btn-close" type="submit" title="Tutup Sesi">
                                                    Tutup
                                                </button>
                                            </form>
                                        @elseif ($sessionService->canOpen($session, auth()->user()))
                                            <form method="POST" action="{{ route('sessions.open', $session) }}">
                                                @csrf
                                                @method('PUT')
                                                <button class="btn-session-mini btn-open" type="submit" title="Buka Sesi Sekarang">
                                                    Buka
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Tombol Hapus Satuan (Admin) --}}
                                        @if ($isAdmin)
                                            <form id="hapus-sesi-{{ $session->id }}" method="POST"
                                                action="{{ route('sessions.destroy', $session) }}"
                                                data-confirm="Hapus sesi {{ $session->prayerLabel() }} tanggal {{ $date->translatedFormat('d F Y') }} beserta {{ $session->logs_count }} catatan kehadirannya?">
                                                @csrf
                                                @method('DELETE')
                                            </form>
                                            <button class="btn-session-mini btn-del" type="submit"
                                                form="hapus-sesi-{{ $session->id }}" title="Hapus sesi ini">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($isAdmin)
                            <div class="session-bulk-bar">
                                <span style="font-size: 12px; color: #64748b; margin-right: auto;">Pilih centang sesi untuk hapus bersamaan:</span>
                                <button class="btn-session-mini btn-del" type="submit" form="hapus-sesi-terpilih">
                                    Hapus yang Dicentang
                                </button>
                            </div>
                        @endif
                    @endif
                </div>

                {{-- KOLOM KANAN: LOG CATATAN KEHADIRAN SESI TERPILIH --}}
                <div class="session-panel-card">
                    <div class="session-panel-header">
                        <div class="session-panel-title-group">
                            <h2>
                                @if ($selected)
                                    Catatan Kehadiran: {{ $selected->prayerLabel() }}
                                @else
                                    Catatan Kehadiran
                                @endif
                            </h2>
                            <p>
                                @if ($selected)
                                    Jam {{ $selected->windowLabel() }} &bull;
                                    @if ($selected->opened_at === null)
                                        Belum dibuka
                                    @else
                                        Dibuka {{ $selected->opener?->name ?? 'otomatis' }} ({{ $selected->opened_at->format('H:i') }})
                                    @endif
                                    @if ($selected->closed_at !== null)
                                        &bull; Ditutup {{ $selected->closer?->name ?? 'otomatis' }} ({{ $selected->closed_at->format('H:i') }})
                                    @endif
                                @else
                                    Pilih sesi di sebelah kiri untuk melihat daftar jamaah yang hadir.
                                @endif
                            </p>
                        </div>

                        @if ($selected && $logs->isNotEmpty())
                            <span class="session-stat-pill pill-green">
                                {{ $logs->count() }} Jamaah
                            </span>
                        @endif
                    </div>

                    @if ($selected === null)
                        <div class="session-empty-state">
                            <div class="session-empty-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <h3 class="session-empty-title">Pilih Sesi Terlebih Dahulu</h3>
                            <p class="session-empty-desc">
                                Klik tombol <strong>Lihat</strong> pada salah satu sesi di sebelah kiri untuk menampilkan riwayat presensi.
                            </p>
                        </div>
                    @elseif ($logs->isEmpty())
                        <div class="session-empty-state">
                            <div class="session-empty-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                            </div>
                            <h3 class="session-empty-title">Belum Ada Presensi Masuk</h3>
                            <p class="session-empty-desc">
                                Belum ada catatan jamaah yang memindai wajah atau sidik jari pada sesi {{ $selected->prayerLabel() }} ini.
                            </p>
                        </div>
                    @else
                        <div class="logs-table-wrap">
                            <table class="logs-table">
                                <thead>
                                    <tr>
                                        <th>Nama Jamaah</th>
                                        <th>Kelas</th>
                                        <th>Pukul</th>
                                        <th>Keadaan</th>
                                        <th>Bukti</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($logs as $log)
                                        <tr>
                                            <td>
                                                <span class="log-user-name">{{ $log->user?->name ?? 'Anggota terhapus' }}</span>
                                                @if ($log->isFingerprint())
                                                    <span class="log-user-sub">{{ $log->summaryLine() }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $log->user?->class_name ?: '-' }}</td>
                                            <td>
                                                <strong style="color: #0f172a;">{{ $log->scanned_at?->format('H:i:s') }}</strong>
                                            </td>
                                            <td>
                                                <span class="log-badge {{ $log->status === \App\Models\SolatAttendanceLog::StatusTerlambat ? 'status-late' : 'status-ontime' }}">
                                                    {{ $log->statusLabel() }}
                                                </span>
                                            </td>
                                            <td>
                                                @include('partials.proof-photo', [
                                                    'log' => $log,
                                                    'proofFallback' => 'Tanpa bukti',
                                                ])
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
