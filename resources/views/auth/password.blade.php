@extends('layouts.app')

@section('title', 'Ganti Sandi')
@section('note', 'Ubah sandi akun yang sedang dipakai')

@section('content')
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Sandi akun</h2>
                <p class="section-note">Sandi bawaan sebaiknya diganti setelah masuk pertama kali. Minimal enam karakter.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="current_password">Sandi saat ini</label>
                    <input class="input-field" id="current_password" name="current_password" type="password" required
                        autocomplete="current-password">
                    @error('current_password')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="field">
                    <label class="field-label" for="password">Sandi baru</label>
                    <input class="input-field" id="password" name="password" type="password" required
                        autocomplete="new-password">
                    @error('password')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="field">
                    <label class="field-label" for="password_confirmation">Ulangi sandi baru</label>
                    <input class="input-field" id="password_confirmation" name="password_confirmation" type="password"
                        required autocomplete="new-password">
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Simpan sandi baru</button>
                <a class="btn btn-outline" href="{{ route(auth()->user()->homeRoute()) }}">Kembali</a>
            </div>
        </form>
    </section>
@endsection
