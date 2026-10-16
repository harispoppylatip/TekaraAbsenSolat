@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
    <p class="guest-title">Absen Solat</p>
    <p class="guest-note">SMK Istiqomah Muhammadiyah 4. Masuk memakai NISN atau email dan sandi yang diberikan admin sekolah.
    </p>

    <div style="margin-top: 18px;">
        @include('partials.flash')
    </div>

    <form class="guest-form" method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="field">
            <label class="field-label" for="login">NISN atau Email</label>
            <input class="input-field" id="login" name="login" type="text" value="{{ old('login', old('email')) }}"
                required autofocus autocomplete="username" placeholder="Masukkan NISN atau email">
        </div>

        <div class="field">
            <label class="field-label" for="password">Sandi</label>
            <input class="input-field" id="password" name="password" type="password" required
                autocomplete="current-password" placeholder="Masukkan sandi">
        </div>

        <div class="check-row">
            <input id="remember" name="remember" type="checkbox" value="1">
            <label for="remember">Tetap masuk di perangkat ini</label>
        </div>

        <button class="btn btn-primary btn-block" type="submit">Masuk</button>
    </form>

    {{-- <p class="guest-foot">
        Untuk murid, sandi awal bawaan adalah NISN Anda dan wajib diganti setelah berhasil masuk.<br>
        Lupa sandi? Hubungi admin sekolah untuk dibuatkan sandi baru.
    </p> --}}
@endsection
