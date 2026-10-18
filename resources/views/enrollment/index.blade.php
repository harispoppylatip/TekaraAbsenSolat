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
    <div data-live="enrollment-header">
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
                    @if ($terkunci)
                        Semua foto yang dibutuhkan sudah terkumpul dan pendaftaran Anda sudah selesai.
                    @elseif ($state['finished'])
                        Semua foto yang dibutuhkan sudah terkumpul. Periksa foto Anda di bawah, lalu kirim untuk menyelesaikan pendaftaran.
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
        @endif
    </div>

    {{--
        Panduan variasi foto yang harus ada. Dilipat secara bawaan agar pengguna
        langsung fokus ke pengambilan foto, tetapi memuat tips penting serta 5 pose
        wajah yang disinkronkan dengan pemotretan otomatis.
    --}}
    <details class="card fold-card" data-guide="foto-yang-perlu-diambil">
        <summary>
            <span class="section-title">Foto yang perlu diambil</span>
            <span class="section-note">
                Sistem membutuhkan {{ $state['required'] }} sudut foto wajah agar saat absen wajah Anda mudah dikenali. Cukup ikuti 5 pose terpandu di bawah ini.
            </span>
            <span class="fold-card-hint">
                <span class="fold-card-open">Buka daftar foto</span>
                <span class="fold-card-close">Tutup daftar foto</span>
            </span>
        </summary>

        <div class="guide-summary-tips">
            <div class="guide-tip-item">
                <span class="guide-tip-icon">☀️</span>
                <div>
                    <strong>Cahaya terang</strong>
                    <p>Hadap sumber cahaya langsung, jangan membelakangi jendela atau lampu.</p>
                </div>
            </div>
            <div class="guide-tip-item">
                <span class="guide-tip-icon">😶</span>
                <div>
                    <strong>Wajah terbuka</strong>
                    <p>Lepas masker, kacamata hitam, atau topi agar seluruh wajah terlihat jelas.</p>
                </div>
            </div>
            <div class="guide-tip-item">
                <span class="guide-tip-icon">👤</span>
                <div>
                    <strong>Sendiri di layar</strong>
                    <p>Pastikan hanya ada satu wajah Anda di dalam bingkai kamera.</p>
                </div>
            </div>
        </div>

        <div class="variation-list">
            @php
                $savedByPose = $state['saved_by_pose'] ?? [];
                $missingPoses = $state['missing_poses'] ?? [];
                $missingLabels = collect($missingPoses)->pluck('short_label')->join(', ');
            @endphp
            @foreach ($variations as $index => $variasi)
                @php
                    $isPoseSaved = isset($savedByPose[$index + 1]);
                @endphp
                <div class="variation-item @if ($variasi['required']) variation-item-required @endif">
                    <p class="variation-label">
                        Pose {{ $index + 1 }}
                        <span class="variation-mark">{{ $variasi['required'] ? 'wajib' : 'tambahan' }}</span>
                        @if ($isPoseSaved)
                            <span class="cell-note" style="color: var(--primary-container); font-weight: 600; margin-left: 6px;">Sudah tersimpan</span>
                        @elseif ($variasi['required'])
                            <span class="cell-note" style="color: var(--outline); margin-left: 6px;">Belum diambil</span>
                        @endif
                    </p>
                    <p class="variation-title">{{ $variasi['label'] }}</p>
                    <p class="variation-hint">{{ $variasi['hint'] }}</p>
                </div>
            @endforeach
        </div>
    </details>

    {{--
        Pengambilan foto wajah. Kamera dibuka langsung agar murid dan orang tua
        tidak bingung mencari tombol kamera. Jika foto sudah cukup (5 dari 5),
        card ini disembunyikan dan akan muncul kembali bila ada foto yang dihapus.
    --}}
    @if (! $terkunci)
        <section class="card" data-shoot-card @if ($state['usable'] >= $state['required']) style="display: none;" @endif>
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
                <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-shoot-input hidden>

                <div class="tool-list">
                    <div class="tool-body" style="padding: 0;">
                        <p class="camera-instruction-note" data-shoot-instruction>
                            Panduan cepat: Nyalakan kamera, lalu klik tombol <strong>{{ $kurang === 5 ? 'Mulai foto otomatis (5 sudut)' : ($kurang > 0 ? "Mulai foto otomatis ({$kurang} foto lagi".($missingLabels ? ": {$missingLabels}" : '').')' : 'Foto sudah lengkap') }}</strong>. Sistem akan menghitung mundur 3 detik untuk setiap arah wajah secara berurutan.
                        </p>

                        <div data-shoot="{{ $state['max_per_submit'] }}"
                            data-needed="{{ $kurang }}"
                            data-required="{{ $state['required'] }}"
                            data-usable="{{ $state['usable'] }}"
                            data-missing-poses="{{ json_encode($missingPoses) }}">
                            <div class="camera-frame">
                                <video data-shoot-video playsinline muted></video>
                                <p class="camera-placeholder" data-shoot-placeholder>
                                    Kamera belum dinyalakan.
                                </p>

                                {{-- Panduan Wajah dan Hitung Mundur Otomatis --}}
                                <div class="camera-guide-overlay" data-shoot-guide hidden>
                                    <div class="camera-guide-oval"></div>
                                    <div class="camera-guide-prompt" data-shoot-guide-prompt>
                                        <span class="guide-step" data-shoot-guide-step>1/5</span>
                                        <span class="guide-text" data-shoot-guide-text>Hadap lurus ke kamera</span>
                                    </div>
                                    <div class="camera-guide-countdown" data-shoot-guide-countdown hidden>3</div>
                                </div>
                                <div class="camera-flash" data-shoot-flash hidden></div>
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
                                    <button class="btn btn-secondary" type="button" data-shoot-start>Nyalakan kamera</button>
                                    <button class="btn btn-primary" type="button" data-shoot-auto disabled>
                                        {{ $kurang === 5 ? 'Mulai foto otomatis (5 sudut)' : ($kurang > 0 ? "Mulai foto otomatis ({$kurang} foto lagi)" : 'Foto sudah lengkap') }}
                                    </button>
                                    <button class="btn btn-outline" type="button" data-shoot-capture disabled>
                                        Ambil manual
                                    </button>
                                    <button class="btn btn-outline btn-small" type="button" data-shoot-stop disabled>
                                        Matikan
                                    </button>
                                </div>
                            </div>

                            <p class="camera-status" data-shoot-status>
                                Pilih kamera, lalu nyalakan untuk mengambil foto wajah Anda secara langsung.
                            </p>

                            <div class="alert alert-warning" data-shoot-blocked hidden>
                                <strong>Kamera tidak bisa dipakai di peramban ini</strong>
                                <p>
                                    Pastikan izin akses kamera sudah diberikan dan halaman dibuka lewat protokol HTTPS.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="shot-stage">
                    <p class="section-note" data-shoot-counter>Belum ada foto yang siap dikirim.</p>
                    <p class="field-hint" data-shoot-note></p>
                    <div class="shot-grid" data-shoot-preview></div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit" data-shoot-submit disabled>Kirim foto untuk diperiksa</button>
                    <a class="btn btn-text" href="{{ route('enrollment.show') }}">Bersihkan pilihan</a>
                </div>
            </form>
        </section>
    @endif

    {{--
        Wilayah ini memutakhirkan dirinya sendiri setiap beberapa detik, jadi
        kemajuan pemeriksaan foto terlihat tanpa perlu memuat ulang halaman.
    --}}
    <div data-live="enrollment">
        @include('enrollment.partials.progress')
    </div>
@endsection
