@extends('layouts.app')

@section('title', 'Pengaturan Akun')
@section('note', 'Ubah surel dan sandi akun yang sedang dipakai')

@section('content')
    <section class="card" style="margin-bottom: 24px;">
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

    @if (filled(auth()->user()->email))
        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Surel / Email Akun</h2>
                    <p class="section-note">
                        Email akun Anda sudah tersimpan: <strong>{{ auth()->user()->email }}</strong>.
                    </p>
                </div>
            </div>

            <details class="fold-card" style="border: none; padding: 0; background: transparent;">
                <summary style="padding: 0; font-size: 13.5px; color: var(--primary-container); font-weight: 600;">
                    <span>Ubah alamat email</span>
                </summary>
                <div style="margin-top: 14px; max-width: 480px;">
                    <form method="POST" action="{{ route('profile.email') }}">
                        @csrf
                        @method('PUT')

                        <div class="field">
                            <label class="field-label" for="profile_email">Alamat email baru</label>
                            <input class="input-field" id="profile_email" name="email" type="email"
                                value="{{ old('email', auth()->user()->email) }}" placeholder="nama@email.com">
                            <p class="field-hint">Boleh dikosongkan jika hanya ingin masuk memakai NISN / nomor induk.</p>
                            @error('email')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-actions" style="margin-top: 14px;">
                            <button class="btn btn-secondary btn-small" type="submit">Perbarui email</button>
                        </div>
                    </form>
                </div>
            </details>
        </section>
    @else
        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Surel / Email Akun</h2>
                    <p class="section-note">
                        Email bersifat opsional untuk alternatif masuk ke aplikasi. Anda tetap bisa masuk menggunakan nomor induk / NISN.
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.email') }}">
                @csrf
                @method('PUT')

                <div class="field" style="max-width: 480px;">
                    <label class="field-label" for="profile_email">Alamat email</label>
                    <input class="input-field" id="profile_email" name="email" type="email"
                        value="{{ old('email') }}" placeholder="nama@email.com">
                    <p class="field-hint">Boleh dikosongkan jika hanya ingin masuk memakai NISN / nomor induk.</p>
                    @error('email')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Simpan email</button>
                </div>
            </form>
        </section>
    @endif
@endsection
