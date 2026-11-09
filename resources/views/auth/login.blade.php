@extends('layouts.guest')

@section('title', 'Masuk · Absen Sholat')

@php
    $prayerItems = ($prayerTimes ?? collect())->map(function ($prayer) use ($todaySessions, $activeSession) {
        $session = ($todaySessions ?? collect())->get($prayer->prayer_name);
        $isSessionOpen = $activeSession && $activeSession->prayer_name === $prayer->prayer_name;
        $isSessionClosed = $session && $session->status === \App\Models\SolatSession::StatusSudahDitutup;

        return [
            'name' => $prayer->prayer_name,
            'label' => $prayer->label,
            'start' => $prayer->shortTime($prayer->start_time),
            'end' => $prayer->shortTime($prayer->end_time),
            'late_minutes' => (int) $prayer->late_minutes,
            'session_open' => (bool) $isSessionOpen,
            'session_closed' => (bool) $isSessionClosed,
        ];
    })->values();

    $recentItems = collect($recentItems ?? []);
@endphp

@section('mobile_top')
    {{-- Bilah Status & Navigasi Aplikasi Khusus Layar Mobile --}}
    <div class="mobile-app-chrome">
        <div class="mobile-live-strip" id="mobile-live-strip">
            <div class="mobile-live-left">
                <span class="mobile-live-caption" id="mobile-strip-caption">
                    {{ $activeSession ? 'Sesi Sedang Dibuka' : 'Sholat Berikutnya' }}
                </span>
                <strong class="mobile-live-title" id="mobile-strip-prayer">
                    {{ $activeSession ? 'Sholat '.$activeSession->prayerLabel() : 'Memuat...' }}
                </strong>
            </div>
            <div class="mobile-live-right">
                <span class="mobile-live-countdown" id="mobile-strip-timer">--:--:--</span>
                <span class="mobile-live-clock" id="mobile-strip-clock">{{ now()->format('H:i') }} WITA</span>
            </div>
        </div>

        <div class="mobile-app-tabs" role="tablist" aria-label="Menu Halaman Masuk">
            <button type="button" class="mobile-app-tab is-active" role="tab" aria-selected="true" data-mobile-view="login">
                Masuk Akun
            </button>
            <button type="button" class="mobile-app-tab" role="tab" aria-selected="false" data-mobile-view="schedule">
                Jadwal Sholat
            </button>
            <button type="button" class="mobile-app-tab" role="tab" aria-selected="false" data-mobile-view="recent">
                <span>15 Absen</span>
                <span class="mobile-tab-count" id="mobile-tab-count">{{ $recentItems->count() }}</span>
            </button>
        </div>
    </div>
@endsection

@section('content')
    <style>
        .guest-stage {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            justify-content: center;
            gap: 22px;
            max-width: 900px;
            width: 100%;
            margin: auto;
        }

        .guest-card {
            background: #ffffff !important;
            border-radius: 22px !important;
            border: 1px solid rgba(255, 255, 255, 0.92) !important;
            box-shadow: 0 20px 50px rgba(2, 22, 48, 0.42), 0 4px 12px rgba(0, 0, 0, 0.08) !important;
            padding: 28px 28px 30px !important;
            max-width: 400px !important;
            width: 100%;
            flex: 0 0 400px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .guest-info-panel {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.92);
            box-shadow: 0 20px 50px rgba(2, 22, 48, 0.42), 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 22px 24px;
            flex: 1 1 460px;
            max-width: 478px;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            color: #0f172a;
        }

        /* Navigasi Aplikasi Mobile (Sembunyi di Desktop) */
        .mobile-app-chrome {
            display: none;
            width: 100%;
            max-width: 420px;
            margin: 0 auto 12px auto;
            flex-direction: column;
            gap: 10px;
            z-index: 5;
        }

        .mobile-live-strip {
            background: rgba(15, 39, 68, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 14px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(2, 22, 48, 0.28);
        }

        .mobile-live-strip.is-open {
            background: rgba(20, 83, 45, 0.94);
        }

        .mobile-live-left {
            display: flex;
            flex-direction: column;
            gap: 1px;
            min-width: 0;
        }

        .mobile-live-caption {
            font-size: 10.5px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.75);
            text-transform: uppercase;
        }

        .mobile-live-title {
            font-family: var(--font-heading);
            font-size: 14.5px;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-live-right {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1px;
            flex-shrink: 0;
        }

        .mobile-live-countdown {
            font-family: var(--font-heading);
            font-size: 15px;
            font-weight: 800;
            color: #fcd34d;
            font-variant-numeric: tabular-nums;
            line-height: 1.15;
        }

        .mobile-live-clock {
            font-size: 11px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.8);
            font-variant-numeric: tabular-nums;
        }

        .mobile-app-tabs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
            background: rgba(15, 39, 68, 0.78);
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 4px;
            border-radius: 13px;
        }

        .mobile-app-tab {
            appearance: none;
            border: none;
            background: transparent;
            color: rgba(255, 255, 255, 0.82);
            font-family: var(--font-body);
            font-size: 12.5px;
            font-weight: 600;
            padding: 8px 6px;
            border-radius: 9px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: transform 0.15s ease, opacity 0.15s ease;
            white-space: nowrap;
        }

        .mobile-app-tab.is-active {
            background: #ffffff;
            color: #0f2744;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.16);
        }

        .mobile-tab-count {
            font-size: 10.5px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.16);
            color: inherit;
            font-variant-numeric: tabular-nums;
        }

        .mobile-app-tab.is-active .mobile-tab-count {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .guest-card-dome {
            display: flex;
            justify-content: center;
            margin-bottom: 6px;
        }

        .guest-dome-img {
            width: 82px;
            height: auto;
            filter: drop-shadow(0 4px 10px rgba(37, 99, 235, 0.2));
        }

        .guest-title {
            text-align: center;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 30px;
            font-weight: 700;
            color: #0f2744;
            margin: 0;
            line-height: 1.2;
            text-wrap: balance;
        }

        .guest-title-divider {
            width: 44px;
            height: 3px;
            background: #2563eb;
            border-radius: 999px;
            margin: 10px auto 12px auto;
        }

        .guest-note {
            text-align: center;
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin: 0 auto 14px auto;
            max-width: 320px;
            text-wrap: pretty;
        }

        .guest-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .guest-input {
            width: 100%;
            padding: 11px 14px 11px 42px;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            font-size: 14px;
            color: #0f172a;
            background: #f8fafc;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            outline: none;
        }

        .guest-input:focus {
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .guest-input-icon {
            position: absolute;
            left: 14px;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .guest-input-wrap:focus-within .guest-input-icon {
            color: #2563eb;
        }

        .guest-field-label {
            color: #1e293b;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
        }

        .guest-submit-btn {
            background: #2563eb !important;
            border: none !important;
            border-radius: 12px !important;
            padding: 12px 20px !important;
            font-size: 15px !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3) !important;
            transition: transform 0.15s ease, opacity 0.15s ease !important;
            cursor: pointer;
            margin-top: 6px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .guest-submit-btn:hover {
            background: #1d4ed8 !important;
            transform: translateY(-1px);
        }

        .guest-submit-btn:active {
            transform: translateY(0);
        }

        .guest-check-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #475569;
        }

        .guest-check-row input[type="checkbox"] {
            accent-color: #2563eb;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        /* Panel Informasi Jadwal Sholat & 15 Absen Terakhir */
        .info-clock-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-date-group {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .info-panel-heading {
            font-family: var(--font-heading);
            font-size: 15.5px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .info-date-text {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }

        .info-live-clock-box {
            text-align: right;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            padding: 6px 11px;
        }

        .info-live-clock {
            font-family: var(--font-heading);
            font-size: 18px;
            font-weight: 800;
            color: #0f2744;
            font-variant-numeric: tabular-nums;
            line-height: 1.15;
        }

        .info-live-tz {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }

        /* Kotak Countdown Waktu Sholat Berikutnya */
        .info-countdown-card {
            background: #0f2744;
            color: #ffffff;
            border-radius: 15px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 6px 16px rgba(15, 39, 68, 0.15);
            margin-top: 12px;
        }

        .info-countdown-card.is-open {
            background: #14532d;
            box-shadow: 0 6px 16px rgba(20, 83, 45, 0.18);
        }

        .info-countdown-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        .info-countdown-caption {
            font-size: 11px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.76);
            text-transform: uppercase;
        }

        .info-countdown-prayer {
            font-family: var(--font-heading);
            font-size: 18.5px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
        }

        .info-countdown-window {
            font-size: 11.5px;
            color: rgba(255, 255, 255, 0.82);
            font-variant-numeric: tabular-nums;
        }

        .info-countdown-timer-wrap {
            text-align: right;
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 11px;
            padding: 7px 12px;
        }

        .info-countdown-timer {
            font-family: var(--font-heading);
            font-size: 19px;
            font-weight: 800;
            color: #ffffff;
            font-variant-numeric: tabular-nums;
            line-height: 1.15;
        }

        .info-countdown-unit {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.72);
            font-weight: 600;
            margin-top: 2px;
        }

        /* Sub-Tab Switcher di dalam Panel Info */
        .info-subtabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            padding: 4px;
            margin-top: 12px;
        }

        .info-subtab {
            appearance: none;
            border: none;
            background: transparent;
            color: #475569;
            font-family: var(--font-body);
            font-size: 12.5px;
            font-weight: 600;
            padding: 7px 10px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .info-subtab.is-active {
            background: #ffffff;
            color: #0f172a;
            font-weight: 700;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        }

        .info-subtab-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 6px;
            background: #e2e8f0;
            color: #334155;
            font-variant-numeric: tabular-nums;
        }

        .info-subtab.is-active .info-subtab-badge {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .info-tab-pane {
            display: none;
            margin-top: 10px;
        }

        .info-tab-pane.is-active {
            display: block;
        }

        /* Daftar 5 Waktu Sholat */
        .info-schedule-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-schedule-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8.5px 12px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .info-schedule-row.is-next {
            background: #eff6ff;
            border-color: #93c5fd;
        }

        .info-schedule-row.is-active {
            background: #f0fdf4;
            border-color: #86efac;
        }

        .info-schedule-row.is-passed {
            opacity: 0.72;
        }

        .info-schedule-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .info-schedule-name {
            font-weight: 700;
            font-size: 13.5px;
            color: #0f172a;
            min-width: 66px;
        }

        .info-schedule-time {
            font-size: 12.5px;
            font-weight: 600;
            color: #475569;
            font-variant-numeric: tabular-nums;
        }

        .info-schedule-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            white-space: nowrap;
            background: #e2e8f0;
            color: #475569;
        }

        .info-schedule-row.is-next .info-schedule-badge {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .info-schedule-row.is-active .info-schedule-badge {
            background: #dcfce7;
            color: #15803d;
        }

        /* Daftar 15 Absen Terakhir (Scroll Rapi & Tidak Memenuhi Layar) */
        .info-recent-scroll {
            max-height: 242px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding-right: 2px;
            -webkit-overflow-scrolling: touch;
        }

        .info-recent-scroll::-webkit-scrollbar {
            width: 5px;
        }

        .info-recent-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 999px;
        }

        .info-recent-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 999px;
        }

        .info-recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 11px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .info-recent-person {
            display: flex;
            align-items: center;
            gap: 9px;
            min-width: 0;
            flex: 1;
        }

        .info-recent-avatar {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            background: #e0e7ff;
            color: #1e40af;
            font-size: 11.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .info-recent-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .info-recent-name {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.25;
        }

        .info-recent-sub {
            font-size: 11.5px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.25;
        }

        .info-recent-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 2px;
            flex-shrink: 0;
        }

        .info-recent-time {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
            line-height: 1.2;
        }

        .info-recent-status {
            font-size: 10.5px;
            font-weight: 700;
            padding: 1.5px 6px;
            border-radius: 5px;
            background: #dcfce7;
            color: #15803d;
        }

        .info-recent-status.is-late {
            background: #fef3c7;
            color: #b45309;
        }

        .info-recent-empty {
            padding: 28px 16px;
            text-align: center;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            color: #64748b;
            font-size: 12.5px;
        }

        .info-schedule-foot {
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
            font-size: 11.5px;
            color: #64748b;
        }

        /* Tampilan Aplikasi Mobile (Layar <= 860px) */
        @media (max-width: 860px) {
            .guest-shell {
                padding: 14px 14px 24px !important;
            }

            .guest-school-header {
                margin-bottom: 10px !important;
            }

            .guest-school-emblem {
                width: 52px !important;
                height: 52px !important;
                margin-bottom: 4px !important;
            }

            .guest-school-name {
                font-size: 16.5px !important;
            }

            .guest-school-city {
                font-size: 13px !important;
            }

            .mobile-app-chrome {
                display: flex;
            }

            .guest-stage {
                flex-direction: column;
                align-items: center;
                max-width: 420px;
                gap: 0;
                margin: 0 auto;
            }

            .guest-card,
            .guest-info-panel {
                flex: none;
                max-width: 420px !important;
                width: 100%;
                padding: 22px 20px 24px !important;
            }

            /* Pada mobile, hanya kartu yang sedang dipilih di tab aplikasi yang tampil */
            body[data-mobile-tab="login"] .guest-info-panel {
                display: none;
            }

            body[data-mobile-tab="schedule"] .guest-card,
            body[data-mobile-tab="recent"] .guest-card {
                display: none;
            }

            .info-recent-scroll {
                max-height: 320px;
            }
        }

        @media (max-width: 420px) {
            .info-countdown-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .info-countdown-timer-wrap {
                width: 100%;
                text-align: left;
                display: flex;
                align-items: baseline;
                justify-content: space-between;
            }
        }
    </style>

    {{-- Ornamen mandala di keempat sudut kartu --}}
    <svg class="guest-card-corner is-tl" aria-hidden="true"><use href="#card-mandala-corner" /></svg>
    <svg class="guest-card-corner is-tr" aria-hidden="true"><use href="#card-mandala-corner" /></svg>
    <svg class="guest-card-corner is-bl" aria-hidden="true"><use href="#card-mandala-corner" /></svg>
    <svg class="guest-card-corner is-br" aria-hidden="true"><use href="#card-mandala-corner" /></svg>

    {{-- Ikon kubah masjid biru-emas di tengah atas kartu --}}
    <div class="guest-card-dome" aria-hidden="true">
        <img class="guest-dome-img" src="{{ asset('images/login-dome.webp') }}" alt="">
    </div>

    <h1 class="guest-title">Absen Sholat</h1>

    <div class="guest-title-divider" aria-hidden="true"></div>

    <p class="guest-note">
        SMK Istiqomah Muhammadiyah 4. Masuk memakai NISN atau email dan sandi yang diberikan admin sekolah.
    </p>

    <div class="guest-flash-wrap">
        @include('partials.flash')
    </div>

    <form class="guest-form" method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="field">
            <label class="guest-field-label" for="login">NISN atau Email</label>
            <div class="guest-input-wrap">
                <span class="guest-input-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </span>
                <input class="guest-input" id="login" name="login" type="text"
                    value="{{ old('login', old('email')) }}" required autofocus autocomplete="username"
                    placeholder="Masukkan NISN atau email">
            </div>
        </div>

        <div class="field">
            <label class="guest-field-label" for="password">Sandi Akun</label>
            <div class="guest-input-wrap">
                <span class="guest-input-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </span>
                <input class="guest-input" id="password" name="password" type="password" required
                    autocomplete="current-password" placeholder="Masukkan kata sandi">
            </div>
        </div>

        <div class="guest-check-row">
            <input id="remember" name="remember" type="checkbox" value="1">
            <label for="remember">Tetap masuk di perangkat ini</label>
        </div>

        <button class="guest-submit-btn" type="submit">
            <span>Masuk ke Akun</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        </button>
    </form>
@endsection

@section('aside')
    <aside class="guest-info-panel" id="guest-info-panel" aria-label="Informasi Jadwal Sholat dan Presensi Terbaru">
        <div>
            <div class="info-clock-bar">
                <div class="info-date-group">
                    <h2 class="info-panel-heading">Informasi Ibadah Sekolah</h2>
                    <span class="info-date-text" id="login-date-label">{{ $todayLabel ?? now()->translatedFormat('l, d F Y') }}</span>
                </div>
                <div class="info-live-clock-box">
                    <div class="info-live-clock" id="login-live-clock">{{ now()->format('H:i:s') }}</div>
                    <div class="info-live-tz">WITA · Samarinda</div>
                </div>
            </div>

            <div class="info-countdown-card {{ $activeSession ? 'is-open' : '' }}" id="login-countdown-card">
                <div class="info-countdown-meta">
                    <span class="info-countdown-caption" id="login-countdown-caption">
                        {{ $activeSession ? 'Sesi Absen Sedang Dibuka' : 'Waktu Sholat Berikutnya' }}
                    </span>
                    <strong class="info-countdown-prayer" id="login-countdown-prayer">
                        {{ $activeSession ? $activeSession->prayerLabel() : 'Memuat Jadwal...' }}
                    </strong>
                    <span class="info-countdown-window" id="login-countdown-window">
                        @if ($activeSession)
                            Jendela {{ $activeSession->shortTime($activeSession->start_time) }} – {{ $activeSession->shortTime($activeSession->end_time) }} WITA
                        @else
                            Menghitung waktu menuju jadwal berikutnya
                        @endif
                    </span>
                </div>
                <div class="info-countdown-timer-wrap">
                    <div class="info-countdown-timer" id="login-countdown-timer">--:--:--</div>
                    <div class="info-countdown-unit" id="login-countdown-unit">Jam : Menit : Detik</div>
                </div>
            </div>

            {{-- Pemilih Sub-Tab: Jadwal 5 Waktu vs 15 Absen Terakhir --}}
            <div class="info-subtabs" role="tablist" aria-label="Pilih tampilan informasi">
                <button type="button" class="info-subtab is-active" role="tab" aria-selected="true" data-subtab="schedule">
                    <span>Jadwal 5 Waktu</span>
                </button>
                <button type="button" class="info-subtab" role="tab" aria-selected="false" data-subtab="recent">
                    <span>15 Absen Terakhir</span>
                    <span class="info-subtab-badge" id="desktop-subtab-count">{{ $recentItems->count() }}</span>
                </button>
            </div>

            {{-- Tab 1: Jadwal 5 Waktu Sholat --}}
            <div class="info-tab-pane is-active" id="pane-schedule" role="tabpanel">
                <div class="info-schedule-list" id="login-schedule-list">
                    @foreach ($prayerItems as $item)
                        <div class="info-schedule-row"
                            data-prayer-name="{{ $item['name'] }}"
                            data-prayer-label="{{ $item['label'] }}"
                            data-start="{{ $item['start'] }}"
                            data-end="{{ $item['end'] }}"
                            data-session-open="{{ $item['session_open'] ? '1' : '0' }}"
                            data-session-closed="{{ $item['session_closed'] ? '1' : '0' }}">
                            <div class="info-schedule-left">
                                <span class="info-schedule-name">{{ $item['label'] }}</span>
                                <span class="info-schedule-time">{{ $item['start'] }} – {{ $item['end'] }}</span>
                            </div>
                            <span class="info-schedule-badge" data-role="badge">Terjadwal</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Tab 2: 15 Absen Terakhir --}}
            <div class="info-tab-pane" id="pane-recent" role="tabpanel">
                @if ($recentItems->isEmpty())
                    <div class="info-recent-empty">
                        Belum ada catatan presensi sholat yang terekam.
                    </div>
                @else
                    <div class="info-recent-scroll">
                        @foreach ($recentItems as $log)
                            <div class="info-recent-item" data-log-id="{{ $log['id'] }}">
                                <div class="info-recent-person">
                                    <span class="info-recent-avatar" aria-hidden="true">{{ $log['initials'] }}</span>
                                    <div class="info-recent-text">
                                        <span class="info-recent-name">{{ $log['name'] }}</span>
                                        <span class="info-recent-sub">Sholat {{ $log['prayer'] }} · {{ $log['group'] }} · {{ $log['method'] }}</span>
                                    </div>
                                </div>
                                <div class="info-recent-meta">
                                    <span class="info-recent-time">{{ $log['time'] }} <small style="font-weight:500;color:#64748b;">{{ $log['date_label'] }}</small></span>
                                    <span class="info-recent-status {{ $log['is_late'] ? 'is-late' : '' }}">{{ $log['status'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="info-schedule-foot">
            <span>{{ $daysSummary['summary'] ?? 'Aktif: Senin – Jumat' }}</span>
            <span>Real-time Otomatis · WITA</span>
        </div>
    </aside>

    <script>
        (function () {
            document.body.setAttribute('data-mobile-tab', 'login');

            const liveDataUrl = @json(route('login.data'));
            const subtabButtons = Array.from(document.querySelectorAll('[data-subtab]'));
            const mobileButtons = Array.from(document.querySelectorAll('[data-mobile-view]'));
            const paneSchedule = document.getElementById('pane-schedule');
            const paneRecent = document.getElementById('pane-recent');
            const desktopCountEl = document.getElementById('desktop-subtab-count');
            const mobileCountEl = document.getElementById('mobile-tab-count');
            const dateLabelEl = document.getElementById('login-date-label');

            function activateSubtab(tabName) {
                subtabButtons.forEach(function (btn) {
                    const active = btn.getAttribute('data-subtab') === tabName;
                    btn.classList.toggle('is-active', active);
                    btn.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                if (paneSchedule) paneSchedule.classList.toggle('is-active', tabName === 'schedule');
                if (paneRecent) paneRecent.classList.toggle('is-active', tabName === 'recent');
            }

            subtabButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const target = btn.getAttribute('data-subtab');
                    activateSubtab(target);
                    if (window.innerWidth <= 860) {
                        setMobileTab(target, false);
                    }
                });
            });

            function setMobileTab(viewName, syncSubtab) {
                document.body.setAttribute('data-mobile-tab', viewName);
                mobileButtons.forEach(function (btn) {
                    const active = btn.getAttribute('data-mobile-view') === viewName;
                    btn.classList.toggle('is-active', active);
                    btn.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                if (syncSubtab !== false && (viewName === 'schedule' || viewName === 'recent')) {
                    activateSubtab(viewName);
                }
            }

            mobileButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setMobileTab(btn.getAttribute('data-mobile-view'), true);
                });
            });

            const serverIso = @json($serverNowIso ?? now()->toIso8601String());
            const serverStartMs = Date.parse(serverIso);
            const clientStartMs = Date.now();
            let offsetMs = Number.isFinite(serverStartMs) ? (serverStartMs - clientStartMs) : 0;

            const clockEl = document.getElementById('login-live-clock');
            const cardEl = document.getElementById('login-countdown-card');
            const captionEl = document.getElementById('login-countdown-caption');
            const prayerEl = document.getElementById('login-countdown-prayer');
            const windowEl = document.getElementById('login-countdown-window');
            const timerEl = document.getElementById('login-countdown-timer');
            const unitEl = document.getElementById('login-countdown-unit');

            const mobileStrip = document.getElementById('mobile-live-strip');
            const mobileCaption = document.getElementById('mobile-strip-caption');
            const mobilePrayer = document.getElementById('mobile-strip-prayer');
            const mobileTimer = document.getElementById('mobile-strip-timer');
            const mobileClock = document.getElementById('mobile-strip-clock');

            const rows = Array.from(document.querySelectorAll('#login-schedule-list .info-schedule-row'));

            function pad(num) {
                return String(Math.max(0, Math.floor(num))).padStart(2, '0');
            }

            function parseMinutes(hhmm) {
                if (!hhmm || typeof hhmm !== 'string') return 0;
                const parts = hhmm.split(':');
                return (parseInt(parts[0], 10) || 0) * 60 + (parseInt(parts[1], 10) || 0);
            }

            function formatDuration(totalSeconds) {
                const sec = Math.max(0, Math.floor(totalSeconds));
                const h = Math.floor(sec / 3600);
                const m = Math.floor((sec % 3600) / 60);
                const s = sec % 60;
                return pad(h) + ':' + pad(m) + ':' + pad(s);
            }

            function getWitaComponents() {
                const nowMs = Date.now() + offsetMs;
                const witaDate = new Date(nowMs + (8 * 3600 * 1000));
                return {
                    hours: witaDate.getUTCHours(),
                    minutes: witaDate.getUTCMinutes(),
                    seconds: witaDate.getUTCSeconds(),
                    totalSeconds: witaDate.getUTCHours() * 3600 + witaDate.getUTCMinutes() * 60 + witaDate.getUTCSeconds()
                };
            }

            function tick() {
                const wita = getWitaComponents();
                const fullClock = pad(wita.hours) + ':' + pad(wita.minutes) + ':' + pad(wita.seconds);

                if (clockEl) {
                    clockEl.textContent = fullClock;
                }
                if (mobileClock) {
                    mobileClock.textContent = pad(wita.hours) + ':' + pad(wita.minutes) + ' WITA';
                }

                if (rows.length === 0) {
                    return;
                }

                let activeRow = null;
                let nextRow = null;

                rows.forEach(function (row) {
                    const startSec = parseMinutes(row.dataset.start) * 60;
                    const endSec = parseMinutes(row.dataset.end) * 60;
                    const sessionOpen = row.dataset.sessionOpen === '1';

                    if (sessionOpen || (wita.totalSeconds >= startSec && wita.totalSeconds <= endSec)) {
                        if (!activeRow) {
                            activeRow = row;
                        }
                    } else if (wita.totalSeconds < startSec && !nextRow) {
                        nextRow = row;
                    }
                });

                const isTomorrowSubuh = !activeRow && !nextRow && rows.length > 0;
                if (isTomorrowSubuh) {
                    nextRow = rows[0];
                }

                rows.forEach(function (row) {
                    row.classList.remove('is-active', 'is-next', 'is-passed');
                    const badge = row.querySelector('[data-role="badge"]');
                    const startSec = parseMinutes(row.dataset.start) * 60;
                    const endSec = parseMinutes(row.dataset.end) * 60;
                    const sessionOpen = row.dataset.sessionOpen === '1';

                    if (row === activeRow) {
                        row.classList.add('is-active');
                        if (badge) badge.textContent = sessionOpen ? 'Sedang Dibuka' : 'Berlangsung';
                    } else if (row === nextRow) {
                        row.classList.add('is-next');
                        if (badge) badge.textContent = isTomorrowSubuh ? 'Besok Pagi' : 'Berikutnya';
                    } else if (wita.totalSeconds > endSec) {
                        row.classList.add('is-passed');
                        if (badge) badge.textContent = 'Selesai';
                    } else if (wita.totalSeconds < startSec) {
                        if (badge) badge.textContent = 'Terjadwal';
                    }
                });

                if (activeRow) {
                    const label = activeRow.dataset.prayerLabel;
                    const start = activeRow.dataset.start;
                    const end = activeRow.dataset.end;
                    const endSec = parseMinutes(end) * 60;
                    const remainSec = Math.max(0, endSec - wita.totalSeconds);
                    const formatted = formatDuration(remainSec);

                    if (cardEl) cardEl.classList.add('is-open');
                    if (captionEl) captionEl.textContent = activeRow.dataset.sessionOpen === '1'
                        ? 'Sesi Absen Sedang Dibuka'
                        : 'Waktu Sholat Sedang Berlangsung';
                    if (prayerEl) prayerEl.textContent = 'Sholat ' + label;
                    if (windowEl) windowEl.textContent = 'Jadwal pukul ' + start + ' – ' + end + ' WITA';
                    if (timerEl) timerEl.textContent = formatted;
                    if (unitEl) unitEl.textContent = 'Sisa Waktu Jendela';

                    if (mobileStrip) mobileStrip.classList.add('is-open');
                    if (mobileCaption) mobileCaption.textContent = 'Sedang Berlangsung';
                    if (mobilePrayer) mobilePrayer.textContent = 'Sholat ' + label + ' (' + start + '–' + end + ')';
                    if (mobileTimer) mobileTimer.textContent = formatted;
                } else if (nextRow) {
                    const label = nextRow.dataset.prayerLabel;
                    const start = nextRow.dataset.start;
                    const end = nextRow.dataset.end;
                    const startSec = parseMinutes(start) * 60;
                    const diffSec = isTomorrowSubuh
                        ? ((24 * 3600 - wita.totalSeconds) + startSec)
                        : Math.max(0, startSec - wita.totalSeconds);
                    const formatted = formatDuration(diffSec);

                    if (cardEl) cardEl.classList.remove('is-open');
                    if (captionEl) captionEl.textContent = isTomorrowSubuh
                        ? 'Jadwal Sholat Besok Pagi'
                        : 'Menuju Waktu Sholat Berikutnya';
                    if (prayerEl) prayerEl.textContent = 'Sholat ' + label;
                    if (windowEl) windowEl.textContent = 'Jadwal pukul ' + start + ' – ' + end + ' WITA';
                    if (timerEl) timerEl.textContent = formatted;
                    if (unitEl) unitEl.textContent = 'Hitung Mundur (Jam:Menit:Detik)';

                    if (mobileStrip) mobileStrip.classList.remove('is-open');
                    if (mobileCaption) mobileCaption.textContent = isTomorrowSubuh ? 'Jadwal Besok Pagi' : 'Sholat Berikutnya';
                    if (mobilePrayer) mobilePrayer.textContent = 'Sholat ' + label + ' · ' + start;
                    if (mobileTimer) mobileTimer.textContent = '-' + formatted;
                }
            }

            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            let lastSignature = Array.from(document.querySelectorAll('#pane-recent [data-log-id]'))
                .map(function (el) { return el.getAttribute('data-log-id'); })
                .join(',');

            function renderRecentLogs(logs) {
                if (!paneRecent || !Array.isArray(logs)) return;

                const count = logs.length;
                if (desktopCountEl) desktopCountEl.textContent = String(count);
                if (mobileCountEl) mobileCountEl.textContent = String(count);

                const newSignature = logs.map(function (item) { return item.id + ':' + item.status; }).join(',');
                if (newSignature === lastSignature) {
                    return;
                }
                lastSignature = newSignature;

                if (count === 0) {
                    paneRecent.innerHTML = '<div class="info-recent-empty">Belum ada catatan presensi sholat yang terekam.</div>';
                    return;
                }

                const existingScroll = paneRecent.querySelector('.info-recent-scroll');
                const prevScrollTop = existingScroll ? existingScroll.scrollTop : 0;

                const htmlItems = logs.map(function (log) {
                    const lateClass = log.is_late ? ' is-late' : '';
                    return '<div class="info-recent-item" data-log-id="' + escapeHtml(log.id) + '">' +
                        '<div class="info-recent-person">' +
                            '<span class="info-recent-avatar" aria-hidden="true">' + escapeHtml(log.initials) + '</span>' +
                            '<div class="info-recent-text">' +
                                '<span class="info-recent-name">' + escapeHtml(log.name) + '</span>' +
                                '<span class="info-recent-sub">Sholat ' + escapeHtml(log.prayer) + ' · ' + escapeHtml(log.group) + ' · ' + escapeHtml(log.method) + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="info-recent-meta">' +
                            '<span class="info-recent-time">' + escapeHtml(log.time) + ' <small style="font-weight:500;color:#64748b;">' + escapeHtml(log.date_label) + '</small></span>' +
                            '<span class="info-recent-status' + lateClass + '">' + escapeHtml(log.status) + '</span>' +
                        '</div>' +
                    '</div>';
                }).join('');

                paneRecent.innerHTML = '<div class="info-recent-scroll">' + htmlItems + '</div>';
                const updatedScroll = paneRecent.querySelector('.info-recent-scroll');
                if (updatedScroll && prevScrollTop > 0) {
                    updatedScroll.scrollTop = prevScrollTop;
                }
            }

            let isFetching = false;
            async function pollLiveData() {
                if (document.hidden || isFetching || !liveDataUrl) return;
                isFetching = true;
                try {
                    const response = await fetch(liveDataUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        cache: 'no-store'
                    });
                    if (!response.ok) return;
                    const data = await response.json();

                    if (data.server_now_iso) {
                        const parsed = Date.parse(data.server_now_iso);
                        if (Number.isFinite(parsed)) {
                            offsetMs = parsed - Date.now();
                        }
                    }

                    if (data.today_label && dateLabelEl) {
                        dateLabelEl.textContent = data.today_label;
                    }

                    const activePrayer = data.active_prayer || null;
                    const closedList = Array.isArray(data.closed_prayers) ? data.closed_prayers : [];

                    rows.forEach(function (row) {
                        const pName = row.getAttribute('data-prayer-name');
                        row.dataset.sessionOpen = (activePrayer && pName === activePrayer) ? '1' : '0';
                        row.dataset.sessionClosed = closedList.indexOf(pName) !== -1 ? '1' : '0';
                    });

                    renderRecentLogs(data.recent_logs);
                    tick();
                } catch (e) {
                    // Abaikan gangguan jaringan sementara
                } finally {
                    isFetching = false;
                }
            }

            tick();
            setInterval(tick, 1000);
            setInterval(pollLiveData, 4000);

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    pollLiveData();
                }
            });
        })();
    </script>
@endsection
