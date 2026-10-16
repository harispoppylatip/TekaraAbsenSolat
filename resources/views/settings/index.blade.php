@extends('layouts.app')

@section('title', 'Pengaturan & Pembersihan Data')
@section('note', 'Kelola pembersihan riwayat absen, data wajah, sesi, atau reset data sistem')

@section('content')
    <details class="alert alert-warning fold-alert" data-record="peringatan-pengaturan">
        <summary>
            <span class="fold-alert-head">
                <strong>Perhatian sebelum menghapus data</strong>
                <span>Seluruh penghapusan di halaman ini bersifat permanen beserta berkas fotonya di server.</span>
            </span>
            <span class="fold-alert-hint">
                <span class="fold-alert-open">Lihat rincian</span>
                <span class="fold-alert-close">Tutup rincian</span>
            </span>
        </summary>
        <div class="fold-alert-body">
            <p>
                Data yang dihapus dari halaman ini langsung dibersihkan dari basis data maupun folder penyimpanan privat
                (foto bukti absen dan foto pendaftaran wajah). Pastikan Anda sudah mengunduh rekap kehadiran di halaman
                Rekap Kehadiran bila datanya masih diperlukan untuk laporan sekolah.
            </p>
        </div>
    </details>

    <section class="stat-grid" aria-label="Ringkasan jumlah data">
        <div class="stat-card">
            <p class="stat-label">Riwayat Absen</p>
            <p class="stat-value">{{ $attendanceCount }}</p>
            <p class="stat-note">Catatan kehadiran tersimpan</p>
        </div>

        <div class="stat-card">
            <p class="stat-label">Sesi Absen</p>
            <p class="stat-value">{{ $sessionCount }}</p>
            <p class="stat-note">Sesi solat & uji coba</p>
        </div>

        <div class="stat-card">
            <p class="stat-label">Data Wajah</p>
            <p class="stat-value">{{ $faceCount }}</p>
            <p class="stat-note">Foto wajah terdaftar ({{ $queueCount }} di antrean)</p>
        </div>

        <div class="stat-card">
            <p class="stat-label">Anggota</p>
            <p class="stat-value">{{ $memberCount }}</p>
            <p class="stat-note">Murid & petugas ({{ $adminCount }} admin aman)</p>
        </div>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Pembersihan Data per Bagian</h2>
                <p class="section-note">
                    Pilih bagian data tertentu yang ingin dibersihkan tanpa mengganggu data lainnya.
                </p>
            </div>
        </div>

        <div class="variation-list">
            <div class="variation-item">
                <p class="variation-title">Hapus Seluruh Riwayat Absen</p>
                <p class="variation-hint">
                    Menghapus {{ $attendanceCount }} catatan kehadiran beserta foto bukti absennya. Sesi absen, anggota,
                    dan data wajah tetap utuh.
                </p>
                <form class="mt-top" method="POST" action="{{ route('settings.clear-attendance') }}"
                    data-confirm="PERINGATAN: Seluruh riwayat kehadiran ({{ $attendanceCount }} catatan) beserta foto bukti absen akan dihapus permanen dan tidak bisa dikembalikan. Lanjutkan?">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-small" type="submit" @disabled($attendanceCount === 0)>
                        Hapus semua riwayat absen
                    </button>
                </form>
            </div>

            <div class="variation-item">
                <p class="variation-title">Hapus Seluruh Sesi & Riwayat Absen</p>
                <p class="variation-hint">
                    Menghapus {{ $sessionCount }} sesi absen dan {{ $attendanceCount }} catatan kehadiran beserta foto
                    buktinya. Jadwal waktu solat tetap tersimpan.
                </p>
                <form class="mt-top" method="POST" action="{{ route('settings.clear-sessions') }}"
                    data-confirm="PERINGATAN: Seluruh sesi absen ({{ $sessionCount }} sesi) dan riwayat kehadiran ({{ $attendanceCount }} catatan) akan dihapus permanen. Jadwal waktu solat tetap aman. Lanjutkan?">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-small" type="submit"
                        @disabled($sessionCount === 0 && $attendanceCount === 0)>
                        Hapus semua sesi & riwayat
                    </button>
                </form>
            </div>

            <div class="variation-item">
                <p class="variation-title">Hapus Seluruh Data Wajah</p>
                <p class="variation-hint">
                    Menghapus {{ $faceCount }} foto wajah terdaftar dan {{ $queueCount }} antrean foto, lalu membuka ulang
                    status pendaftaran wajah semua akun.
                </p>
                <form class="mt-top" method="POST" action="{{ route('settings.clear-faces') }}"
                    data-confirm="PERINGATAN: Seluruh data dan foto wajah ({{ $faceCount }} foto) akan dihapus permanen. Semua anggota harus mendaftarkan wajah ulang. Lanjutkan?">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-small" type="submit"
                        @disabled($faceCount === 0 && $queueCount === 0)>
                        Hapus semua data wajah
                    </button>
                </form>
            </div>

            <div class="variation-item">
                <p class="variation-title">Hapus Seluruh Murid & Petugas</p>
                <p class="variation-hint">
                    Menghapus {{ $memberCount }} akun murid dan petugas beserta foto wajah dan riwayat absen mereka.
                    {{ $adminCount }} akun Administrator tidak ikut dihapus.
                </p>
                <form class="mt-top" method="POST" action="{{ route('settings.clear-members') }}"
                    data-confirm="PERINGATAN: Seluruh {{ $memberCount }} akun Murid dan Petugas beserta data wajah dan riwayat absennya akan dihapus permanen (akun Admin tetap aman). Lanjutkan?">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-small" type="submit" @disabled($memberCount === 0)>
                        Hapus semua murid & petugas
                    </button>
                </form>
            </div>

            <div class="variation-item">
                <p class="variation-title">Hapus Seluruh Perangkat</p>
                <p class="variation-hint">
                    Menghapus {{ $deviceCount }} perangkat terdaftar sehingga perangkat harus didaftarkan ulang sebelum
                    mengirim absen.
                </p>
                <form class="mt-top" method="POST" action="{{ route('settings.clear-devices') }}"
                    data-confirm="PERINGATAN: Seluruh {{ $deviceCount }} perangkat terdaftar akan dihapus permanen. Lanjutkan?">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-small" type="submit" @disabled($deviceCount === 0)>
                        Hapus semua perangkat
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Hapus Seluruh Data (Reset Total Sistem)</h2>
                <p class="section-note">
                    Mengosongkan seluruh data operasional aplikasi sekaligus untuk memulai dari awal (misalnya pergantian
                    tahun ajaran baru).
                </p>
            </div>
        </div>

        <div class="alert alert-error">
            <strong>Peringatan Keras: Tindakan Tidak Dapat Dibatalkan</strong>
            <p>
                Tindakan ini akan menghapus bersih seluruh riwayat kehadiran, seluruh sesi absen, seluruh foto dan sidik
                wajah, seluruh perangkat, serta seluruh akun murid dan petugas. Hanya akun Administrator dan jadwal waktu
                solat yang tetap dipertahankan.
            </p>
        </div>

        <form class="mt-top" method="POST" action="{{ route('settings.clear-all') }}"
            data-confirm="PERINGATAN KERAS: Seluruh data operasional (riwayat absen, sesi, wajah, perangkat, serta akun murid & petugas) akan dihapus bersih secara permanen! Yakin ingin melanjutkan?">
            @csrf
            @method('DELETE')

            <div class="field">
                <label class="field-label" for="confirm_text">
                    Ketik <strong>HAPUS SEMUA</strong> (huruf kapital) untuk membuka pengaman penghapusan total
                </label>
                <input class="input-field" type="text" id="confirm_text" name="confirm_text"
                    placeholder="HAPUS SEMUA" autocomplete="off" required>
                @error('confirm_text')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-actions">
                <button class="btn btn-danger" type="submit">
                    Hapus seluruh data sekarang
                </button>
            </div>
        </form>
    </section>
@endsection

