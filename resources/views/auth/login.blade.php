@extends('layouts.guest')

@section('title', 'Masuk · Absen Sholat')

@section('content')
    <style>
        .guest-card {
            background: #ffffff !important;
            border-radius: 24px !important;
            border: 1px solid rgba(255, 255, 255, 0.9) !important;
            box-shadow: 0 20px 50px rgba(2, 22, 48, 0.45), 0 4px 12px rgba(0, 0, 0, 0.1) !important;
            padding: 28px 28px 30px !important;
            max-width: 410px !important;
        }

        .guest-card-dome {
            display: flex;
            justify-content: center;
            margin-bottom: 8px;
        }

        .guest-dome-img {
            width: 88px;
            height: auto;
            filter: drop-shadow(0 4px 10px rgba(37, 99, 235, 0.2));
        }

        .guest-title {
            text-align: center;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 32px;
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
            margin: 10px auto 14px auto;
        }

        .guest-note {
            text-align: center;
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin: 0 auto 16px auto;
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
