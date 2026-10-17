@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    {{-- Ornamen mandala di keempat sudut kartu --}}
    <svg class="guest-card-corner is-tl" aria-hidden="true"><use href="#card-mandala-corner" /></svg>
    <svg class="guest-card-corner is-tr" aria-hidden="true"><use href="#card-mandala-corner" /></svg>
    <svg class="guest-card-corner is-bl" aria-hidden="true"><use href="#card-mandala-corner" /></svg>
    <svg class="guest-card-corner is-br" aria-hidden="true"><use href="#card-mandala-corner" /></svg>

    {{-- Ikon kubah masjid biru-emas di tengah atas kartu --}}
    <div class="guest-card-dome" aria-hidden="true">
        <img class="guest-dome-img" src="{{ asset('images/login-dome.webp') }}" alt="">
    </div>

    <p class="guest-title">Absen Solat</p>

    {{-- Ornamen pembatas di bawah judul --}}
    <div class="guest-title-flourish" aria-hidden="true">
        <svg viewBox="0 0 220 18" class="guest-flourish-svg">
            <path d="M12,9 H86 M134,9 H208" stroke="#0c2f5c" stroke-width="1.4" stroke-linecap="round" />
            <path d="M86,9 C92,3 98,3 102,9 C98,15 92,15 86,9 Z" fill="none" stroke="#0c2f5c" stroke-width="1.3" />
            <path d="M134,9 C128,3 122,3 118,9 C122,15 128,15 134,9 Z" fill="none" stroke="#0c2f5c" stroke-width="1.3" />
            <polygon points="110,3 115,9 110,15 105,9" fill="#0c2f5c" />
            <circle cx="78" cy="9" r="2" fill="#0c2f5c" />
            <circle cx="142" cy="9" r="2" fill="#0c2f5c" />
        </svg>
    </div>

    <p class="guest-note">SMK Istiqomah Muhammadiyah 4. Masuk memakai NISN atau email dan sandi yang diberikan admin sekolah.
    </p>

    <div class="guest-flash-wrap">
        @include('partials.flash')
    </div>

    <form class="guest-form" method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="field">
            <label class="field-label guest-field-label" for="login">NISN atau Email</label>
            <div class="guest-input-wrap">
                <span class="guest-input-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 12c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm0 2.5c-3.34 0-10 1.68-10 5v2h20v-2c0-3.32-6.66-5-10-5z" />
                    </svg>
                </span>
                <input class="input-field guest-input" id="login" name="login" type="text"
                    value="{{ old('login', old('email')) }}" required autofocus autocomplete="username"
                    placeholder="Masukkan NISN atau email">
            </div>
        </div>

        <div class="field">
            <label class="field-label guest-field-label" for="password">Sandi</label>
            <div class="guest-input-wrap">
                <span class="guest-input-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm3 11c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z" />
                    </svg>
                </span>
                <input class="input-field guest-input" id="password" name="password" type="password" required
                    autocomplete="current-password" placeholder="Masukkan sandi">
            </div>
        </div>

        <div class="check-row guest-check-row">
            <input id="remember" name="remember" type="checkbox" value="1">
            <label for="remember">Tetap masuk di perangkat ini</label>
        </div>

        <button class="btn btn-primary btn-block guest-submit-btn" type="submit">Masuk</button>
    </form>
@endsection
