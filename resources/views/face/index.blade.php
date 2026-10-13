@extends('layouts.app')

@section('title', 'Data Wajah')
@section('note', 'Pendaftaran dan pemeliharaan data wajah jamaah')

@section('content')
    {{-- Kemajuan pemrosesan wajah. Isinya diperbarui sendiri oleh peramban
         selama masih ada foto yang sedang diubah menjadi angka wajah. --}}
    <section class="card" data-face-progress data-endpoint="{{ route('face.progress') }}"
        data-retry-template="{{ route('face.queue.retry', ['batch' => '__BATCH__']) }}"
        @if ($batches === []) hidden @endif>
        <div class="section-head">
            <div>
                <h2 class="section-title">Proses pendaftaran wajah</h2>
                <p class="section-note">
                    Foto diubah menjadi angka wajah satu per satu di latar belakang, jadi halaman ini tidak
                    perlu ditunggu. Perkiraan satu foto {{ $expectedSeconds }} detik pada mesin tanpa kartu
                    grafis.
                </p>
            </div>
        </div>

        <div class="queue-list" data-face-batches>
            @foreach ($batches as $batch)
                @include('face.partials.batch', ['batch' => $batch])
            @endforeach
        </div>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Keadaan layanan wajah</h2>
                <p class="section-note">Alamat layanan yang dipakai aplikasi: {{ $serviceUrl }}</p>
            </div>
        </div>

        @if ($serviceReady && $serviceModel !== null && $serviceModel !== $expectedModel)
            {{-- Model yang dilaporkan mesin wajah berbeda dari yang diharapkan
                 aplikasi. Seluruh unggahan akan ditolak sampai keduanya sama,
                 jadi keadaan ini diberitahukan lebih dulu di sini supaya tidak
                 terbaca sebagai kegagalan satu per satu foto. --}}
            <div class="alert alert-error">
                <strong>Versi mesin wajah tidak sama dengan aplikasi</strong>
                <p>
                    Aplikasi mengharapkan model {{ $expectedModel }}, sedangkan mesin wajah melaporkan
                    {{ $serviceModel }}. Selama keduanya berbeda, setiap foto akan ditolak karena angka wajah dari
                    dua model tidak bisa dibandingkan.
                </p>
                <p>
                    Samakan <code>FACE_MODEL</code> di berkas <code>.env</code> aplikasi ini dengan
                    <code>FACE_MODEL_NAME</code> di berkas <code>.env</code> mesin wajah, lalu jalankan
                    <code>php artisan config:clear</code>. Perhatikan bahwa berkas <code>.env</code> tidak ikut
                    tersalin saat proyek dipindah ke server, jadi berkas itu wajib diperiksa sendiri.
                </p>
            </div>
        @elseif ($serviceReady)
            <div class="alert alert-success">
                <strong>Layanan wajah siap</strong>
                <p>
                    Foto yang diunggah langsung diterima, lalu diubah menjadi angka wajah satu per satu di latar
                    belakang.
                    @if ($serviceModel !== null)
                        Model yang dipakai: {{ $serviceModel }}.
                    @endif
                </p>
            </div>
        @else
            <div class="alert alert-warning">
                <strong>Layanan wajah tidak menyala</strong>
                <p>
                    Nyalakan layanan wajah pada alamat di atas, lalu muat ulang halaman ini.
                    Foto yang diunggah sekarang masih diterima dan menunggu di antrean, tetapi baru bisa
                    menjadi angka wajah setelah layanan menyala dan foto itu diulang dari panel antrean.
                </p>
            </div>
        @endif
    </section>

    {{-- Tiga cara menambah foto wajah, masing-masing dilipat supaya halaman
         tidak penuh isian sekaligus. Sengaja diletakkan di luar wilayah yang
         menyegarkan dirinya sendiri agar pilihan berkas dan isian yang sedang
         dikerjakan admin tidak terhapus di tengah jalan. --}}
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Tambah foto wajah</h2>
                <p class="section-note">
                    Buka panel di bawah ini, pilih anggota beserta foto wajahnya. Foto yang dikirim langsung
                    diterima, sedangkan pengubahannya menjadi angka wajah berjalan di latar belakang sehingga
                    tidak perlu ditunggu.
                </p>
            </div>
        </div>

        <div class="tool-list">
            <details class="tool-panel" data-record="tool-unggah" @if ($errors->any()) open @endif>
                <summary>
                    <span class="tool-name">Unggah foto wajah</span>
                    <span class="tool-note">Pilih anggota, lalu pilih foto wajahnya. Sekali kirim paling banyak
                        {{ $maxSubmit }} foto.</span>
                    <span class="tool-hint">
                        <span class="tool-hint-open">Buka</span>
                        <span class="tool-hint-close">Tutup</span>
                    </span>
                </summary>

                <div class="tool-body">
                    <form method="POST" action="{{ route('face.photos.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="form-grid">
                            <div class="field">
                                <label class="field-label" for="user_id">Anggota</label>
                                <select class="select-field" id="user_id" name="user_id" required>
                                    <option value="">Pilih anggota</option>
                                    @foreach ($candidates as $candidate)
                                        <option value="{{ $candidate->id }}" @selected((int) old('user_id') === $candidate->id)>
                                            {{ $candidate->name }}{{ $candidate->identifier_number ? ' (' . $candidate->identifier_number . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="field">
                                <label class="field-label" for="photos">Foto wajah</label>
                                <input class="input-field" type="file" id="photos" name="photos[]" accept="image/*"
                                    multiple required>
                                <p class="field-hint">Pilih beberapa foto sekaligus dari satu anggota, paling
                                    banyak {{ $maxSubmit }} foto sekali kirim.</p>
                                @error('photos')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                                @error('photos.*')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="check-row">
                            <input type="checkbox" id="replace" name="replace" value="1">
                            <label for="replace">Ganti foto wajah anggota ini: seluruh foto lama dihapus setelah
                                ada foto barunya yang berhasil disimpan</label>
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit">Unggah foto wajah</button>
                        </div>
                    </form>
                </div>
            </details>
        </div>
    </section>

    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Cari anggota</h2>
                <p class="section-note">Daftar di bawah memuat {{ \App\Http\Controllers\FaceDataController::PerPage }}
                    anggota setiap halaman.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('face.index') }}">
            <div class="filter-bar">
                <div class="field">
                    <label class="field-label" for="q">Nama, nomor induk, atau surel</label>
                    <input class="input-field" type="search" id="q" name="q" value="{{ $term }}"
                        placeholder="Contoh: Ahmad atau 2005221">
                </div>

                <div class="filter-actions">
                    <button class="btn btn-primary" type="submit">Cari</button>
                    <a class="btn btn-text" href="{{ route('face.index') }}">Bersihkan</a>
                </div>
            </div>
        </form>
    </section>

    <div data-live="wajah">
        <section class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Anggota terdaftar</p>
                <p class="stat-value">{{ $stats['anggota'] }}</p>
                <p class="stat-note">Murid, guru, dan petugas</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Sudah punya wajah</p>
                <p class="stat-value">{{ $stats['berwajah'] }}</p>
                <p class="stat-note">Siap absen lewat kamera</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Foto wajah tersimpan</p>
                <p class="stat-value">{{ $stats['foto'] }}</p>
                <p class="stat-note">Paling banyak {{ $stats['batas'] }} foto setiap anggota</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Petugas kamera</p>
                <p class="stat-value">{{ $operators->count() }}</p>
                <p class="stat-note">Boleh memakai halaman absen wajah</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Sudah terdaftar</p>
                <p class="stat-value">{{ $stats['selesai'] }}</p>
                <p class="stat-note">Dari {{ $stats['anggota'] }} anggota, minimal
                    {{ $stats['wajib_foto'] }} foto masing-masing</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Belum terdaftar</p>
                <p class="stat-value">{{ $stats['belum'] }}</p>
                <p class="stat-note">{{ $stats['sedang'] }} sedang diperiksa,
                    {{ $stats['perlu_daftar_ulang'] }} perlu daftar ulang</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Pendaftaran terakhir</p>
                <p class="stat-value stat-value-small">
                    {{ $stats['pendaftaran_terakhir']
                        ? \Illuminate\Support\Carbon::parse($stats['pendaftaran_terakhir'])->translatedFormat('d M Y')
                        : 'Belum ada' }}
                </p>
                <p class="stat-note">
                    {{ $stats['pendaftaran_terakhir']
                        ? \Illuminate\Support\Carbon::parse($stats['pendaftaran_terakhir'])->translatedFormat('H:i') . ' WIB'
                        : 'Belum ada anggota yang menyelesaikan pendaftaran sendiri' }}
                </p>
            </div>
        </section>

        {{--
            Daftar anggota yang wajahnya belum selesai. Dipisah dari daftar wajah
            terdaftar supaya admin bisa langsung melihat siapa yang perlu
            diingatkan, dan supaya halaman dengan halamannya sendiri tidak
            menggeser halaman daftar yang lain.
        --}}
        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Menunggu pendaftaran wajah</h2>
                    <p class="section-note">
                        Anggota di bawah ini belum bisa absen memakai wajah. Urutannya dari yang paling perlu
                        ditangani: yang perlu daftar ulang lebih dulu, lalu yang fotonya ditolak, lalu yang belum
                        selesai. Anggota melakukannya sendiri di halaman pendaftaran wajah setelah masuk.
                    </p>
                </div>
            </div>

            @if ($pending->isEmpty())
                <p class="empty-state">Seluruh anggota sudah menyelesaikan pendaftaran wajahnya.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Anggota</th>
                                <th>Keadaan</th>
                                <th>Foto tersimpan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending as $anggota)
                                <tr>
                                    <td>
                                        {{ $anggota->name }}
                                        <span class="cell-note">
                                            {{ $anggota->identifier_number ?: 'nomor induk belum ada' }}
                                            @if ($anggota->class_name)
                                                , {{ $anggota->class_name }}
                                            @endif
                                        </span>
                                    </td>
                                    <td>{{ $anggota->enrollmentLabel() }}</td>
                                    <td>{{ $anggota->face_embeddings_count }} dari {{ $stats['wajib_foto'] }} foto</td>
                                    <td>
                                        @if ($anggota->enrollmentStatus() === \App\Models\User::EnrollmentNeedsReenrollment)
                                            <span class="cell-note">Sudah diminta mendaftar ulang</span>
                                        @else
                                            <form method="POST"
                                                action="{{ route('face.enrollment.reopen', $anggota) }}">
                                                @csrf
                                                @method('PUT')
                                                <button class="btn btn-small btn-outline" type="submit"
                                                    data-confirm="Minta {{ $anggota->name }} mendaftarkan wajahnya kembali? Foto lamanya tetap tersimpan sampai foto barunya lolos pemeriksaan.">
                                                    Minta daftar ulang
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $pending->links('pagination.records') }}
            @endif
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Petugas kamera absen</h2>
                    <p class="section-note">
                        Keadaan tugas di bawah ini dibaca dari data yang sama dengan yang dipakai penjaga hak
                        akses halaman, jadi apa yang tertulis selalu sama dengan apa yang boleh masuk. Akun
                        berperan murid tidak bisa ditugaskan dari sini, tetapi akun yang sudah memegang tugas
                        tetap terdaftar supaya tugasnya bisa dicabut. Admin selalu boleh memakai halaman absen
                        wajah.
                    </p>
                </div>
            </div>

            @if ($assignable->isEmpty())
                <p class="empty-state">Belum ada guru atau petugas yang bisa ditugaskan.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Peran</th>
                                <th>Keadaan tugas</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assignable as $candidate)
                                <tr data-member="{{ $candidate->id }}">
                                    <td class="cell-strong">{{ $candidate->name }}</td>
                                    <td>{{ $candidate->roleLabel() }}</td>
                                    <td>
                                        @if ($candidate->can_scan_face)
                                            <span class="status status-open" data-operator-status>Sedang bertugas</span>
                                        @else
                                            <span class="status status-closed" data-operator-status>Tidak bertugas</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="cell-actions">
                                            <form method="POST"
                                                action="{{ route('face.operator.update', $candidate) }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="assigned"
                                                    value="{{ $candidate->can_scan_face ? 0 : 1 }}">
                                                <button class="btn btn-small btn-outline" type="submit">
                                                    {{ $candidate->can_scan_face ? 'Cabut tugas' : 'Tugaskan' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Wajah terdaftar</h2>
                    <p class="section-note">
                        Menampilkan {{ $members->firstItem() ?? 0 }} sampai {{ $members->lastItem() ?? 0 }}
                        dari {{ $members->total() }} anggota. Buka satu kartu untuk melihat fotonya.
                    </p>
                </div>
            </div>

            @if ($members->isEmpty())
                <p class="empty-state">Tidak ada anggota yang cocok dengan pencarian tersebut.</p>
            @else
                <div class="record-list">
                    @foreach ($members as $member)
                        <details class="record-card" data-record="{{ $member->id }}">
                            <summary>
                                <span class="record-card-name">{{ $member->name }}</span>
                                <span class="record-card-note">
                                    {{ $member->identifier_number ? 'Nomor induk ' . $member->identifier_number : $member->roleLabel() }}
                                    {{ $member->class_name ? ', kelas ' . $member->class_name : '' }}
                                </span>
                                <span class="record-card-note">
                                    {{ $member->face_embeddings_count > 0
                                        ? $member->face_embeddings_count . ' foto wajah tersimpan'
                                        : 'Belum ada foto wajah' }}
                                </span>
                                <span class="record-card-hint">Lihat detail</span>
                            </summary>

                            <div class="record-card-body">
                                @if ($member->faceEmbeddings->isEmpty())
                                    <p class="empty-state">Anggota ini belum punya foto wajah. Unggah fotonya lewat panel
                                        di atas.</p>
                                @else
                                    @if ($member->face_embeddings_count < $photosNeeded)
                                        <p class="cell-note">Baru {{ $member->face_embeddings_count }} foto. Anggota
                                            dengan {{ $photosNeeded }} foto atau lebih jauh lebih pasti dikenali,
                                            jadi tambahkan foto dari arah dan cahaya yang berbeda.</p>
                                    @endif

                                    <div class="table-wrap">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Keterangan</th>
                                                    <th>Model</th>
                                                    <th>Didaftarkan</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($member->faceEmbeddings as $embedding)
                                                    <tr>
                                                        <td class="cell-strong">
                                                            {{ $embedding->label ?: 'Tanpa keterangan' }}
                                                            @isset($outlierPhotos[$embedding->id])
                                                                <span class="cell-note">Berbeda jauh dari foto lain anggota
                                                                    ini, jaraknya
                                                                    {{ number_format($outlierPhotos[$embedding->id], 2, ',', '.') }}.
                                                                    Pastikan foto ini memang orang yang sama, atau ganti dengan
                                                                    foto
                                                                    yang lebih jelas.</span>
                                                            @endisset
                                                        </td>
                                                        <td>{{ $embedding->model }}</td>
                                                        <td>{{ $embedding->created_at?->translatedFormat('d F Y H:i') }}
                                                        </td>
                                                        <td>
                                                            <div class="cell-actions">
                                                                <form method="POST"
                                                                    action="{{ route('face.photos.destroy', $embedding) }}"
                                                                    data-confirm="Hapus satu foto wajah {{ $member->name }} ini?">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button class="btn btn-small btn-danger"
                                                                        type="submit">Hapus foto</button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="form-actions">
                                        <form method="POST" action="{{ route('face.member.destroy', $member) }}"
                                            data-confirm="Hapus seluruh foto wajah {{ $member->name }}? Anggota dan riwayat absennya tetap ada.">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-small btn-danger" type="submit">Hapus semua
                                                foto</button>
                                        </form>

                                        <a class="btn btn-small btn-outline"
                                            href="{{ route('members.edit', $member) }}">Ubah data anggota</a>
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>

                {{ $members->links('pagination.records') }}
            @endif
        </section>
    </div>
@endsection
