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
@endphp

@section('content')
    <style>
        .guest-stage {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            justify-content: center;
            gap: 22px;
            max-width: 880px;
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
            background: rgba(255, 255, 255, 0.97);
            border-radius: 22px;
            border: 1px solid rgba(255, 255, 255, 0.92);
            box-shadow: 0 20px 50px rgba(2, 22, 48, 0.42), 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 24px 26px;
            flex: 1 1 430px;
            max-width: 458px;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
            color: #0f172a;
        }

        .guest-card-dome {
            display: flex;
            justify-content: center;
            margin-bottom: 8px;
        }

        .guest-dome-img {
            width: 84px;
            height: auto;
            filter: drop-shadow(0 4px 10px rgba(37, 99, 235, 0.2));
        }

        .guest-title {
            text-align: center;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 30px;
            font-weight: 700;
            color: #0f2744;
            letter-spacing: 0.3px;
            margin: 0;
            line-height: 1.2;
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
            transition: all 0.2s ease;
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
            transition: all 0.2s ease !important;
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
            box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4) !important;
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

        /* Panel Informasi Jadwal Sholat & Jam Digital */
        .info-clock-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-date-group {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .info-panel-heading {
            font-family: var(--font-heading);
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .info-date-text {
            font-size: 12.5px;
            color: #64748b;
            font-weight: 500;
        }

        .info-live-clock-box {
            text-align: right;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 7px 12px;
        }

        .info-live-clock {
            font-family: var(--font-heading);
            font-size: 19px;
            font-weight: 800;
            color: #0f2744;
            font-variant-numeric: tabular-nums;
            line-height: 1.15;
            letter-spacing: 0.4px;
        }

        .info-live-tz {
            font-size: 10.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Kotak Countdown Waktu Sholat Berikutnya */
        .info-countdown-card {
            background: #0f2744;
            color: #ffffff;
            border-radius: 16px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            box-shadow: 0 8px 20px rgba(15, 39, 68, 0.18);
        }

        .info-countdown-card.is-open {
            background: #14532d;
            box-shadow: 0 8px 20px rgba(20, 83, 45, 0.2);
        }

        .info-countdown-meta {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }

        .info-countdown-caption {
            font-size: 11.5px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.76);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .info-countdown-prayer {
            font-family: var(--font-heading);
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
        }

        .info-countdown-window {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.82);
            font-variant-numeric: tabular-nums;
        }

        .info-countdown-timer-wrap {
            text-align: right;
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 12px;
            padding: 8px 13px;
        }

        .info-countdown-timer {
            font-family: var(--font-heading);
            font-size: 21px;
            font-weight: 800;
            color: #ffffff;
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.6px;
            line-height: 1.15;
        }

        .info-countdown-unit {
            font-size: 10.5px;
            color: rgba(255, 255, 255, 0.72);
            font-weight: 600;
            margin-top: 2px;
        }

        /* Daftar 5 Waktu Sholat */
        .info-schedule-list {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .info-schedule-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 9px 13px;
            border-radius: 11px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: background 0.15s ease, border-color 0.15s ease;
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
            font-size: 14px;
            color: #0f172a;
            min-width: 68px;
        }

        .info-schedule-time {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            font-variant-numeric: tabular-nums;
        }

        .info-schedule-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
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

        .info-schedule-foot {
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
            font-size: 12px;
            color: #64748b;
        }

        @media (max-width: 860px) {
            .guest-stage {
                flex-direction: column;
                align-items: center;
                max-width: 420px;
                gap: 16px;
            }

            .guest-card,
            .guest-info-panel {
                flex: none;
                max-width: 420px !important;
                width: 100%;
            }
        }

        @media (max-width: 480px) {
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
    <aside class="guest-info-panel" aria-label="Jadwal Sholat Hari Ini">
        <div>
            <div class="info-clock-bar">
                <div class="info-date-group">
                    <h2 class="info-panel-heading">Jadwal Sholat Hari Ini</h2>
                    <span class="info-date-text" id="login-date-label">{{ $todayLabel ?? now()->translatedFormat('l, d F Y') }}</span>
                </div>
                <div class="info-live-clock-box">
                    <div class="info-live-clock" id="login-live-clock">{{ now()->format('H:i:s') }}</div>
                    <div class="info-live-tz">WITA · Samarinda</div>
                </div>
            </div>

            <div class="info-countdown-card {{ $activeSession ? 'is-open' : '' }}" id="login-countdown-card" style="margin-top: 14px;">
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

            <div class="info-schedule-list" id="login-schedule-list" style="margin-top: 14px;">
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

        <div class="info-schedule-foot">
            <span>{{ $daysSummary['summary'] ?? 'Aktif: Senin – Jumat' }}</span>
            <span>Zona Waktu WITA (UTC+8)</span>
        </div>
    </aside>

    <script>
        (function () {
            const serverIso = @json($serverNowIso ?? now()->toIso8601String());
            const serverStartMs = Date.parse(serverIso);
            const clientStartMs = Date.now();
            const offsetMs = Number.isFinite(serverStartMs) ? (serverStartMs - clientStartMs) : 0;

            const clockEl = document.getElementById('login-live-clock');
            const cardEl = document.getElementById('login-countdown-card');
            const captionEl = document.getElementById('login-countdown-caption');
            const prayerEl = document.getElementById('login-countdown-prayer');
            const windowEl = document.getElementById('login-countdown-window');
            const timerEl = document.getElementById('login-countdown-timer');
            const unitEl = document.getElementById('login-countdown-unit');
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

                if (clockEl) {
                    clockEl.textContent = pad(wita.hours) + ':' + pad(wita.minutes) + ':' + pad(wita.seconds);
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

                    if (cardEl) cardEl.classList.add('is-open');
                    if (captionEl) captionEl.textContent = activeRow.dataset.sessionOpen === '1'
                        ? 'Sesi Absen Sedang Dibuka'
                        : 'Waktu Sholat Sedang Berlangsung';
                    if (prayerEl) prayerEl.textContent = 'Sholat ' + label;
                    if (windowEl) windowEl.textContent = 'Jadwal pukul ' + start + ' – ' + end + ' WITA';
                    if (timerEl) timerEl.textContent = formatDuration(remainSec);
                    if (unitEl) unitEl.textContent = 'Sisa Waktu Jendela';
                } else if (nextRow) {
                    const label = nextRow.dataset.prayerLabel;
                    const start = nextRow.dataset.start;
                    const end = nextRow.dataset.end;
                    const startSec = parseMinutes(start) * 60;
                    const diffSec = isTomorrowSubuh
                        ? ((24 * 3600 - wita.totalSeconds) + startSec)
                        : Math.max(0, startSec - wita.totalSeconds);

                    if (cardEl) cardEl.classList.remove('is-open');
                    if (captionEl) captionEl.textContent = isTomorrowSubuh
                        ? 'Jadwal Sholat Besok Pagi'
                        : 'Menuju Waktu Sholat Berikutnya';
                    if (prayerEl) prayerEl.textContent = 'Sholat ' + label;
                    if (windowEl) windowEl.textContent = 'Jadwal pukul ' + start + ' – ' + end + ' WITA';
                    if (timerEl) timerEl.textContent = formatDuration(diffSec);
                    if (unitEl) unitEl.textContent = 'Hitung Mundur (Jam:Menit:Detik)';
                }
            }

            tick();
            setInterval(tick, 1000);
        })();
    </script>
@endsection
