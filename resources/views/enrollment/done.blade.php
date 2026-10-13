@extends('layouts.app')

@section('title', 'Pendaftaran Selesai')
@section('note', 'Wajah Anda sudah terdaftar dan siap dipakai absen')

@section('content')
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Terima kasih, {{ $user->name }}</h2>
                <p class="section-note">
                    Wajah Anda sudah terdaftar dengan {{ $state['usable'] }} foto. Saat absen, berdirilah di depan
                    kamera dengan wajah terlihat jelas, lalu tunggu sampai layar menampilkan nama Anda.
                </p>
            </div>
        </div>

        <div class="progress-block">
            <p class="progress-value">{{ $state['usable'] }} dari {{ $state['required'] }} foto</p>
            <div class="progress-track">
                <div class="progress-fill" style="width: 100%"></div>
            </div>
            <p class="progress-note">Pemeriksaan seluruh foto sudah selesai.</p>
        </div>

        <div class="form-actions">
            <a class="btn btn-primary" href="{{ route($user->homeRoute()) }}">Lihat riwayat absen saya</a>
            <a class="btn btn-outline" href="{{ route('enrollment.show') }}">Tambah atau ganti foto</a>
        </div>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Yang perlu Anda tahu</h2>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Hal</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Yang disimpan</td>
                        <td>Yang disimpan hanya angka wajah, bukan gambarnya. Foto yang Anda kirim sudah dihapus
                            dari server setelah diperiksa.</td>
                    </tr>
                    <tr>
                        <td>Yang bisa melihat</td>
                        <td>Angka wajah tidak bisa dibaca manusia dan tidak pernah ditampilkan di halaman mana pun.
                            Petugas sekolah hanya melihat jumlah fotonya.</td>
                    </tr>
                    <tr>
                        <td>Bila wajah tidak dikenali</td>
                        <td>Cahaya di depan kamera sangat menentukan. Bila belum dikenali, coba lagi sebentar atau
                            minta petugas sekolah mencatat kehadiran Anda secara manual.</td>
                    </tr>
                    <tr>
                        <td>Bila ingin mengganti foto</td>
                        <td>Buka kembali halaman pendaftaran wajah. Foto lama bisa dihapus satu per satu atau
                            seluruhnya, lalu diganti dengan foto yang baru.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection
