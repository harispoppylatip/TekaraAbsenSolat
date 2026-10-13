@extends('layouts.app')

@section('title', 'Pendaftaran Wajah')
@section('note', 'Daftarkan wajah Anda supaya bisa absen tanpa menempelkan jari')

@push('scripts')
    @vite('resources/js/enrollment.js')
@endpush

@section('content')
    @php
        $kurang = max(0, $state['required'] - $state['usable']);
        $sisaJatah = max(0, $state['max'] - $state['total']);
        $terkunci = $state['locked'];
    @endphp

    {{--
        Identitas di bawah ini dibaca dari akun yang sedang masuk, bukan dari
        isian apa pun di halaman. Karena itu namanya ditampilkan sebagai
        keterangan, bukan sebagai kolom yang bisa diubah: tidak ada jalan
        mendaftarkan wajah untuk akun orang lain.
    --}}
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">{{ $user->name }}</h2>
                <p class="section-note">
                    Nomor induk {{ $user->identifier_number ?: 'belum ada' }}
                    @if ($user->class_name)
                        , kelas {{ $user->class_name }}
                    @endif
                    , peran {{ $user->roleLabel() }}.
                    Keadaan wajah Anda sekarang: <strong>{{ $state['label'] }}</strong>.
                </p>
            </div>

            <div class="section-actions">
                <a class="btn btn-outline btn-small" href="{{ route('password.edit') }}">Ganti sandi</a>
                <a class="btn btn-text btn-small" href="{{ route($user->homeRoute()) }}">Kembali</a>
            </div>
        </div>

        <div class="progress-block">
            <p class="progress-value">{{ $state['usable'] }} dari {{ $state['required'] }} foto</p>
            <div class="progress-track">
                <div class="progress-fill" style="width: {{ $state['percent'] }}%"></div>
            </div>
            <p class="progress-note">
                @if ($state['finished'])
                    Semua foto yang dibutuhkan sudah terkumpul dan sudah diperiksa. Wajah Anda siap dipakai absen.
                @else
                    Kurang {{ $kurang }} foto lagi supaya pendaftaran bisa diselesaikan.
                @endif
            </p>
        </div>

        @if ($state['unusable'] > 0)
            {{-- Terjadi pada akun yang wajahnya didaftarkan memakai mesin versi
                 lama. Angkanya masih tersimpan tetapi tidak bisa dibandingkan
                 dengan foto baru, jadi pemiliknya perlu tahu mengapa jumlah foto
                 terlihat ada padahal belum dihitung. --}}
            <div class="alert alert-warning">
                <strong>Ada {{ $state['unusable'] }} foto lama yang tidak bisa dipakai</strong>
                <p>
                    Foto itu dihitung mesin versi sebelumnya, jadi angkanya tidak bisa dibandingkan dengan foto
                    baru. Foto seperti itu tidak akan dipakai saat absen. Kirim foto baru sampai jumlahnya
                    cukup, atau hapus foto lama itu di bagian bawah halaman ini.
                </p>
            </div>
        @endif

        @if ($terkunci)
            {{--
                Akun yang pendaftarannya sudah selesai TIDAK boleh mengubah
                wajahnya sendiri. Alasannya ditulis apa adanya supaya tidak
                terbaca sebagai kerusakan: wajah yang sudah dipakai absen
                setiap hari tidak boleh berubah tanpa sepengetahuan sekolah,
                jadi pembukaannya harus lewat petugas.
            --}}
            <div class="alert alert-info">
                <strong>Pendaftaran wajah Anda sudah selesai dan terkunci</strong>
                <p>
                    Karena wajah Anda sudah dipakai untuk absen, fotonya tidak bisa lagi ditambah, diganti,
                    atau dihapus dari halaman ini. Bila memang perlu diganti, minta petugas sekolah membuka
                    pendaftaran ulang dari halaman Data Wajah. Setelah dibuka, halaman ini bisa dipakai lagi
                    seperti biasa.
                </p>
            </div>
        @endif

        @if ($state['pending'])
            <div class="alert alert-info" data-shoot-busy>
                <strong>Foto Anda sedang diperiksa</strong>
                <p>
                    Pemeriksaan satu foto memerlukan beberapa detik. Halaman ini memutakhirkan dirinya sendiri,
                    jadi biarkan terbuka sampai bagian kemajuan di bawah selesai. Kirim foto berikutnya setelah
                    yang ini selesai.
                </p>
            </div>
        @endif
    </section>

    @if ($terkunci)
        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Wajah Anda sudah terdaftar</h2>
                    <p class="section-note">
                        {{ $state['usable'] }} foto wajah tersimpan untuk akun Anda. Saat absen, berdirilah di
                        depan kamera dengan wajah terlihat jelas. Bila wajahnya tidak dikenali, minta petugas
                        sekolah mencatat kehadiran Anda secara manual.
                    </p>
                </div>
            </div>

            <div class="form-actions">
                <a class="btn btn-primary" href="{{ route($user->homeRoute()) }}">Lihat riwayat absen saya</a>
            </div>
        </section>
    @elseif ($state['finished'])
        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Foto yang dibutuhkan sudah terkumpul</h2>
                    <p class="section-note">
                        Periksa dulu hasilnya di bawah. Bila sudah sesuai, tekan Selesaikan pendaftaran supaya
                        wajah Anda bisa dipakai absen. Setelah diselesaikan, fotonya tidak bisa diubah lagi
                        tanpa dibuka petugas sekolah.
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('enrollment.complete') }}">
                @csrf
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Selesaikan pendaftaran</button>
                </div>
            </form>
        </section>
    @endif

    {{--
        Dua panduan di bawah ini dilipat, bukan dibuka, karena keduanya berisi
        bacaan yang panjang sedangkan yang perlu dikerjakan pemiliknya pertama
        kali justru bagian Ambil foto. Yang dilipat tetap bisa dibuka kapan saja
        tanpa memuat ulang halaman, dan terbuka sendiri di peramban yang tidak
        menjalankan JavaScript sebab unsur `details` memang begitu bawaannya.
    --}}
    <details class="card fold-card" data-guide="cara-mengambil-foto">
        <summary>
            <span class="section-title">Cara mengambil foto yang benar</span>
            <span class="section-note">
                Ambil foto dari tempat yang terang, bisa di dalam atau di luar ruangan. Yang penting wajah
                terlihat jelas, kamera tidak bergerak, dan tidak ada orang lain di dalam bingkai.
            </span>
            <span class="fold-card-hint">
                <span class="fold-card-open">Buka panduan</span>
                <span class="fold-card-close">Tutup panduan</span>
            </span>
        </summary>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Hal yang diperiksa</th>
                        <th>Cara yang benar</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Cahaya</td>
                        <td>Hadap ke arah cahaya, jangan membelakangi jendela. Hindari ruangan gelap dan hindari
                            lampu yang menyorot langsung ke wajah.</td>
                    </tr>
                    <tr>
                        <td>Ketajaman</td>
                        <td>Pegang telepon dengan tenang, atau minta orang lain memotretkan. Foto yang bergerak
                            akan ditolak.</td>
                    </tr>
                    <tr>
                        <td>Ukuran wajah</td>
                        <td>Wajah memenuhi sebagian besar layar. Jangan terlalu jauh sampai wajahnya kecil.</td>
                    </tr>
                    <tr>
                        <td>Arah hadap</td>
                        <td>Menghadap kamera. Boleh diputar sedikit ke kiri atau ke kanan, tetapi jangan sampai
                            telinga hilang dari bingkai.</td>
                    </tr>
                    <tr>
                        <td>Isi bingkai</td>
                        <td>Satu orang saja di dalam bingkai. Foto bersama tidak bisa dipakai.</td>
                    </tr>
                    <tr>
                        <td>Yang menutupi wajah</td>
                        <td>Rambut tidak menutupi dahi dan pipi. Lepaskan masker dan penutup kepala.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </details>

    {{-- Variasi foto yang harus ada. Susunannya diatur di config/face.php
         supaya jumlah wajibnya ikut berubah bila batas minimalnya diubah. --}}
    <details class="card fold-card" data-guide="foto-yang-perlu-diambil">
        <summary>
            <span class="section-title">Foto yang perlu diambil</span>
            <span class="section-note">
                {{ $state['required'] }} foto wajib, dan boleh ditambah sampai {{ $state['max'] }} foto.
                Sekali kirim paling banyak {{ $state['max_per_submit'] }} foto, jadi panduan ini bisa
                dikerjakan dalam beberapa kali kirim.
            </span>
            <span class="fold-card-hint">
                <span class="fold-card-open">Buka daftar foto</span>
                <span class="fold-card-close">Tutup daftar foto</span>
            </span>
        </summary>

        <div class="variation-list">
            @foreach ($variations as $index => $variasi)
                <div class="variation-item">
                    <p class="variation-label">
                        Foto {{ $index + 1 }}
                        <span class="variation-mark">{{ $variasi['required'] ? 'wajib' : 'tambahan' }}</span>
                    </p>
                    <p class="variation-title">{{ $variasi['label'] }}</p>
                    <p class="variation-hint">{{ $variasi['hint'] }}</p>
                </div>
            @endforeach
        </div>
    </details>

    {{--
        Dua cara mengambil foto. Sengaja dilipat supaya halaman tidak penuh
        isian sekaligus, dan supaya cara pertama yang dilihat adalah kamera.

        Seluruh bagian ini disembunyikan pada akun yang pendaftarannya sudah
        selesai, dan penolakannya juga diperiksa ulang di pengendali. Menyembunyikan
        tombol saja tidak cukup: permintaan tetap bisa dibuat langsung ke alamatnya.
    --}}
    @unless ($terkunci)
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Ambil foto</h2>
                <p class="section-note">
                    Foto yang siap dikirim dikumpulkan lebih dulu di bawah panel ini, jadi Anda bisa membuang
                    yang kurang bagus sebelum dikirim.
                </p>
            </div>
        </div>

        @if ($sisaJatah === 0)
            <div class="alert alert-warning">
                <strong>Jatah foto Anda sudah penuh</strong>
                <p>
                    Akun Anda sudah memuat {{ $state['total'] }} foto, yaitu batas terbanyaknya. Hapus beberapa
                    foto di bagian bawah halaman ini kalau ingin menambah foto baru.
                </p>
            </div>
        @endif

        <form method="POST" action="{{ route('enrollment.photos.store') }}" enctype="multipart/form-data"
            data-enrollment-form data-max-submit="{{ $state['max_per_submit'] }}"
            data-progress-url="{{ route('enrollment.progress') }}">
            @csrf
            <input type="hidden" name="replace" value="0">

            <div class="tool-list">
                <details class="tool-panel" data-record="enrollment-kamera" @if ($errors->any()) open @endif>
                    <summary>
                        <span class="tool-name">Ambil foto sekarang</span>
                        <span class="tool-note">Pakai kamera peramban. Paling nyaman dikerjakan dari telepon.</span>
                        <span class="tool-hint">
                            <span class="tool-hint-open">Buka</span>
                            <span class="tool-hint-close">Tutup</span>
                        </span>
                    </summary>

                    <div class="tool-body">
                        <div data-shoot="{{ $state['max_per_submit'] }}">
                            <div class="camera-frame">
                                <video data-shoot-video playsinline muted></video>
                                <p class="camera-placeholder" data-shoot-placeholder>
                                    Kamera belum dinyalakan.
                                </p>
                            </div>

                            <div class="camera-controls">
                                <div class="field">
                                    <label class="field-label" for="enrollment-facing">Kamera yang dipakai</label>
                                    <select class="select-field" id="enrollment-facing" data-shoot-facing>
                                        <option value="user">Kamera depan</option>
                                        <option value="environment">Kamera belakang</option>
                                    </select>
                                </div>

                                <div class="form-actions">
                                    <button class="btn btn-secondary" type="button" data-shoot-start>Nyalakan
                                        kamera</button>
                                    <button class="btn btn-primary" type="button" data-shoot-capture disabled>Ambil
                                        foto</button>
                                    <button class="btn btn-outline btn-small" type="button" data-shoot-stop
                                        disabled>Matikan</button>
                                </div>
                            </div>

                            <p class="camera-status" data-shoot-status>
                                Pilih kamera, lalu nyalakan. Bila kamera tidak bisa dipakai, pakai pilihan foto dari
                                galeri di panel berikutnya. Hasilnya sama.
                            </p>

                            <div class="alert alert-warning" data-shoot-blocked hidden>
                                <strong>Kamera tidak bisa dipakai di halaman ini</strong>
                                <p>
                                    Kamera peramban hanya bisa dipakai pada alamat yang memakai HTTPS dan pada
                                    peramban yang mendukungnya. Pakai panel pilihan foto dari galeri, hasilnya
                                    sama saja.
                                </p>
                            </div>
                        </div>
                    </div>
                </details>

                <details class="tool-panel" data-record="enrollment-galery">
                    <summary>
                        <span class="tool-name">Pilih foto dari galeri</span>
                        <span class="tool-note">Foto yang sudah pernah diambil bisa dipakai dari sini.</span>
                        <span class="tool-hint">
                            <span class="tool-hint-open">Buka</span>
                            <span class="tool-hint-close">Tutup</span>
                        </span>
                    </summary>

                    <div class="tool-body">
                        <div class="field">
                            <label class="field-label" for="photos">Foto wajah</label>
                            <input class="input-field" type="file" id="photos" name="photos[]"
                                accept="image/jpeg,image/png,image/webp" multiple data-shoot-input>
                            <p class="field-hint">
                                Format JPEG, PNG, atau WEBP. Ukuran satu foto paling besar 5 MB.
                            </p>
                            @error('photos')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                            @error('photos.*')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </details>
            </div>

            <div class="shot-stage">
                <p class="section-note" data-shoot-counter>Belum ada foto yang siap dikirim.</p>
                <p class="field-hint" data-shoot-note></p>
                <div class="shot-grid" data-shoot-preview></div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit" data-shoot-submit disabled>Kirim foto untuk
                    diperiksa</button>
                <a class="btn btn-text" href="{{ route('enrollment.show') }}">Bersihkan pilihan</a>
            </div>
        </form>
    </section>
    @endunless

    {{--
        Wilayah ini memutakhirkan dirinya sendiri setiap beberapa detik, jadi
        kemajuan pemeriksaan foto terlihat tanpa perlu memuat ulang halaman.
    --}}
    <div data-live="enrollment">
        @include('enrollment.partials.progress')
    </div>
@endsection
