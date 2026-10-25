@extends('layouts.app')

@section('title', 'Pengaturan, Cadangan & Pembersihan Data')
@section('note', 'Unduh cadangan lengkap (database & seluruh foto), pulihkan data, atau bersihkan data sistem')

@section('content')
    @php
        $formattedSize = $photoTotalBytes >= 1048576
            ? number_format($photoTotalBytes / 1048576, 2, ',', '.').' MB'
            : number_format(max($photoTotalBytes / 1024, 0), 1, ',', '.').' KB';
    @endphp

    {{-- Pengaturan Sensitivitas & Ambang Wajah --}}
    <section class="card" style="margin-bottom: 24px;">
        <div class="section-head">
            <div>
                <h2 class="section-title">Sensitivitas & Pengenalan Wajah</h2>
                <p class="section-note">
                    Atur ambang kecocokan wajah (toleransi jarak) dan ukuran wajah minimum untuk pemindaian CCTV latar belakang maupun kamera pemindai langsung. Pengaturan langsung aktif seketika tanpa perlu mematikan atau menyalakan ulang server.
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.face-sensitivity') }}">
            @csrf
            @method('PUT')

            <div class="form-grid" style="margin-bottom: 16px;">
                <div class="field">
                    <label class="field-label" for="face_cctv_tolerance">
                        Toleransi CCTV (Jarak Jauh)
                    </label>
                    <input class="input-field" type="number" step="0.01" min="0.5" max="2.0" id="face_cctv_tolerance" name="face_cctv_tolerance"
                        value="{{ old('face_cctv_tolerance', $faceCctvTolerance) }}" required>
                    <p class="field-hint">Standar rekomendasi: <strong>1.15</strong></p>
                    @error('face_cctv_tolerance')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="field-label" for="face_tolerance">
                        Toleransi Kamera Web / Petugas
                    </label>
                    <input class="input-field" type="number" step="0.01" min="0.5" max="2.0" id="face_tolerance" name="face_tolerance"
                        value="{{ old('face_tolerance', $faceTolerance) }}" required>
                    <p class="field-hint">Standar rekomendasi: <strong>1.15</strong></p>
                    @error('face_tolerance')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="field-label" for="face_cctv_min_face_size">
                        Ukuran Min. Wajah CCTV (px)
                    </label>
                    <input class="input-field" type="number" step="1" min="0" max="500" id="face_cctv_min_face_size" name="face_cctv_min_face_size"
                        value="{{ old('face_cctv_min_face_size', $faceCctvMinFaceSize) }}" required>
                    <p class="field-hint">Standar: <strong>40</strong> px (wajah kecil/jauh)</p>
                    @error('face_cctv_min_face_size')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="field-label" for="face_min_face_size">
                        Ukuran Min. Wajah Kamera Web (px)
                    </label>
                    <input class="input-field" type="number" step="1" min="0" max="500" id="face_min_face_size" name="face_min_face_size"
                        value="{{ old('face_min_face_size', $faceMinFaceSize) }}" required>
                    <p class="field-hint">Standar: <strong>80</strong> px (kamera jarak dekat)</p>
                    @error('face_min_face_size')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Dropdown Keterangan / Detail Panduan --}}
            <details style="background: var(--surface-variant, #f8fafc); border: 1px solid var(--outline, #e2e8f0); border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;">
                <summary style="cursor: pointer; font-size: 13.5px; font-weight: 600; color: var(--primary); user-select: none;">
                    Lihat detail panduan & cara kerja angka sensitivitas
                </summary>
                <div style="margin-top: 10px; font-size: 13px; color: var(--on-surface-variant); line-height: 1.6;">
                    <p style="margin-bottom: 8px;">
                        <strong>Cara kerja toleransi pencocokan:</strong> Sistem mengenali wajah dengan mengukur jarak matematis (jarak Euclidean) antara pola wajah jamaah di database dengan wajah yang ditangkap kamera. Semakin kecil angkanya, semakin identik wajah tersebut.
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px;">
                        <div>
                            <strong>Angka lebih besar (misal 1.20 - 1.30):</strong> Pengenalan lebih sensitif/mudah cocok. Cocok untuk CCTV sudut tinggi, pencahayaan minim, atau jamaah bergerak cepat. Potensi salah orang (false positive) sedikit meningkat.
                        </div>
                        <div>
                            <strong>Angka lebih kecil (misal 0.90 - 1.05):</strong> Pengenalan sangat ketat dan akurat. Mengurangi risiko salah deteksi, namun wajah yang sedikit menunduk atau bayangan redup bisa tidak terdeteksi.
                        </div>
                        <div>
                            <strong>Ukuran Minimum Wajah:</strong> Membatasi ukuran kotak wajah terkecil (dalam piksel) yang akan diproses AI. Angka 40 px pada CCTV membuat jamaah di saf belakang tetap bisa teridentifikasi.
                        </div>
                    </div>
                    <p style="margin: 0; color: var(--on-surface);">
                        Pengaturan yang disimpan di sini langsung dimuat oleh pemindai latar belakang (background worker) pada putaran pemindaian berikutnya secara otomatis.
                    </p>
                </div>
            </details>

            <div class="form-actions" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <button class="btn btn-primary" type="submit">
                    Simpan Sensitivitas Wajah
                </button>
                <button class="btn btn-outline" type="submit" name="reset_defaults" value="1" formnovalidate
                    data-confirm="Kembalikan semua nilai sensitivitas wajah ke rekomendasi standar bawaan sistem?">
                    Kembalikan ke Rekomendasi Bawaan
                </button>
            </div>
        </form>
    </section>

    {{-- Pengaturan Foto Bukti Absensi --}}
    <section class="card" style="margin-bottom: 24px;">
        <div class="section-head">
            <div>
                <h2 class="section-title">Foto Bukti Pemindaian Wajah</h2>
                <p class="section-note">
                    Atur apakah foto bukti absensi yang disimpan sistem menyertakan kotak pendeteksi wajah dan label nama jamaah.
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.proof-overlay') }}">
            @csrf
            @method('PUT')

            <div class="field" style="margin-bottom: 18px;">
                <label class="checkbox-row" for="proof_box_overlay" style="cursor: pointer; align-items: flex-start; gap: 12px;">
                    <input type="checkbox" id="proof_box_overlay" name="proof_box_overlay" value="1" @checked($proofOverlayEnabled) style="margin-top: 3px; width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <strong style="display: block; font-size: 14.5px; color: var(--on-surface);">Sertakan kotak dan label nama pada foto bukti absen</strong>
                        <p class="field-hint" style="margin-top: 4px; color: var(--on-surface-variant); font-size: 13px; line-height: 1.5;">
                            Saat opsi ini aktif, setiap foto bukti yang disimpan (baik dari siaran CCTV latar belakang maupun pemindai kamera petugas) akan otomatis dibubuhi kotak hijau/oranye serta label 2 baris nama anggota dan status kehadiran persis seperti yang terlihat di layar.
                        </p>
                    </div>
                </label>
            </div>

            <div class="form-actions" style="margin-top: 14px;">
                <button class="btn btn-primary" type="submit">
                    Simpan Pengaturan Foto Bukti
                </button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Cadangan & Pemulihan Sistem (Backup & Restore)</h2>
                <p class="section-note">
                    Simpan seluruh data website (akun murid/guru, jadwal, sesi, riwayat absen, sidik wajah, beserta
                    folder foto anak-anak) ke dalam 1 berkas <strong>.zip</strong> untuk disimpan di komputer, flashdisk,
                    atau Google Drive.
                </p>
            </div>
        </div>

        <div class="variation-list">
            <div class="variation-item">
                <p class="variation-title">1. Simpan Cadangan Lengkap (Download Backup .zip)</p>
                <p class="variation-hint">
                    Satu klik untuk mengunduh seluruh isi database ({{ $memberCount + $adminCount }} akun,
                    {{ $sessionCount }} sesi, {{ $attendanceCount }} riwayat absen, {{ $faceCount }} sidik wajah)
                    sekaligus {{ $photoFileCount }} berkas foto anak-anak ({{ $formattedSize }}) dalam satu berkas
                    <strong>.zip</strong>.
                </p>
                <div class="form-actions mt-top">
                    <a class="btn btn-primary" href="{{ route('settings.backup.download') }}">
                        Unduh Backup Lengkap (.zip)
                    </a>
                </div>
            </div>

            <div class="variation-item">
                <p class="variation-title">2. Kembalikan Data dari Cadangan (Restore Backup .zip)</p>
                <p class="variation-hint">
                    Pindah server atau tidak sengaja menghapus data? Pilih berkas <strong>.zip</strong> hasil unduhan
                    backup di atas untuk mengembalikan seluruh database dan folder foto anak-anak persis seperti semula.
                </p>

                <form class="mt-top" method="POST" action="{{ route('settings.backup.restore') }}"
                    enctype="multipart/form-data"
                    data-confirm="PERINGATAN: Memulihkan cadangan akan mengganti data dan foto saat ini dengan isi dari berkas .zip yang Anda pilih. Lanjutkan pemulihan data?">
                    @csrf

                    <div class="field">
                        <label class="field-label" for="backup_file">Pilih Berkas Cadangan (.zip)</label>
                        <input class="input-field" type="file" id="backup_file" name="backup_file" accept=".zip,application/zip" required>
                        @error('backup_file')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="checkbox-row" for="confirm_restore">
                            <input type="checkbox" id="confirm_restore" name="confirm_restore" value="1" required>
                            <span>Saya mengerti bahwa data saat ini akan digantikan oleh data dari berkas cadangan (.zip) ini.</span>
                        </label>
                        @error('confirm_restore')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-secondary" type="submit">
                            Pulihkan Data Sekarang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

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
                (foto bukti absen dan foto pendaftaran wajah). Sangat disarankan menekan tombol
                <strong>Unduh Backup Lengkap (.zip)</strong> di atas terlebih dahulu sebelum menghapus data.
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
            <p class="stat-label">Data & Berkas Foto</p>
            <p class="stat-value">{{ $photoFileCount }}</p>
            <p class="stat-note">{{ $faceCount }} wajah terdaftar ({{ $formattedSize }})</p>
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

