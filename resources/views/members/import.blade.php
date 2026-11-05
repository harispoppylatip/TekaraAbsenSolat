@extends('layouts.app')

@section('title', 'Impor & Ekspor Anggota')
@section('note', 'Tambah data anggota massal melalui berkas CSV atau pulihkan paket lengkap (.zip)')

@section('content')
    <div class="grid-2">
        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Unggah berkas CSV atau Paket ZIP</h2>
                    <p class="section-note">
                        Pilih berkas CSV atau paket ekspor anggota (.zip) dari komputer Anda. Paket ZIP akan memulihkan data anggota beserta foto wajah AI dan sidik jarinya.
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ route('members.import.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="field">
                    <label class="field-label" for="file">Berkas CSV / Paket Ekspor (.zip)</label>
                    <input class="input-field" type="file" id="file" name="file" accept=".csv,.zip,text/csv,text/plain,application/zip" required>
                    <p class="field-hint">Mendukung tabel .csv (pembatas koma atau titik koma) maupun paket lengkap .zip hasil ekspor data anggota. Ukuran maksimal 100 MB.</p>
                    @error('file')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="check-row" style="margin-top: 16px;">
                    <input type="checkbox" id="update_existing" name="update_existing" value="1" @checked(old('update_existing', true))>
                    <label for="update_existing">Perbarui data anggota (nama, email, peran, kelas, dan jurusan) jika nomor induk / NISN sudah terdaftar di sistem</label>
                </div>

                <div class="form-actions" style="margin-top: 24px;">
                    <button class="btn btn-primary" type="submit">Mulai impor</button>
                    <a class="btn btn-outline" href="{{ route('members.index') }}">Batal</a>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Petunjuk & Template</h2>
                    <p class="section-note">
                        Unduh template untuk memastikan susunan kolom CSV Anda sudah sesuai.
                    </p>
                </div>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                <a class="btn btn-primary btn-small" href="{{ route('members.export') }}">
                    Ekspor Data Anggota & Foto (.zip)
                </a>
                <a class="btn btn-secondary btn-small" href="{{ route('members.import.template', ['delimiter' => 'semicolon']) }}">
                    Unduh Template Excel (Kolom A-F)
                </a>
                <a class="btn btn-outline btn-small" href="{{ route('members.import.template', ['delimiter' => 'comma']) }}">
                    Unduh Template CSV (Koma)
                </a>
            </div>

            <div class="table-wrap">
                <table class="data-table" style="min-width: 100%;">
                    <thead>
                        <tr>
                            <th>Kolom</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="cell-strong">nama</span> (Kolom A)</td>
                            <td><span class="status status-open">Wajib</span></td>
                            <td>Nama lengkap anggota</td>
                        </tr>
                        <tr>
                            <td><span class="cell-strong">nisn</span> (Kolom B)</td>
                            <td><span class="status status-open">Wajib</span></td>
                            <td>Nomor induk siswa / pegawai (username & sandi awal murid)</td>
                        </tr>
                        <tr>
                            <td><span class="cell-strong">email</span> (Kolom C)</td>
                            <td><span class="status status-wait">Opsional</span></td>
                            <td>Boleh dikosongkan. Pengguna bisa mengisi sendiri di profil</td>
                        </tr>
                        <tr>
                            <td><span class="cell-strong">peran</span> (Kolom D)</td>
                            <td><span class="status status-wait">Opsional</span></td>
                            <td>Isi <code>murid</code>, <code>guru</code>, atau <code>admin</code> (bawaan: murid)</td>
                        </tr>
                        <tr>
                            <td><span class="cell-strong">kelas</span> (Kolom E)</td>
                            <td><span class="status status-wait">Opsional</span></td>
                            <td>Kelas siswa, contoh: <code>X</code>, <code>XI</code>, <code>XII</code>, atau <code>X .21</code> (khusus murid)</td>
                        </tr>
                        <tr>
                            <td><span class="cell-strong">jurusan</span> (Kolom F)</td>
                            <td><span class="status status-wait">Opsional</span></td>
                            <td>Jurusan siswa, contoh: <code>TKJ</code>, <code>TKRO</code>, <code>AKL</code>, <code>MPLB</code> (khusus murid)</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 18px; padding: 14px; background: var(--surface-variant); border-radius: var(--radius-sm); font-size: 13px; line-height: 1.6;">
                <strong>Membuat berkas langsung dari Microsoft Excel:</strong>
                <p style="margin: 6px 0 0; color: var(--on-surface-variant);">
                    Anda dapat langsung menyusun tabel di Excel dengan baris 1 sebagai judul (<code>nama</code>, <code>nisn</code>, <code>email</code>, <code>peran</code>, <code>kelas</code>, <code>jurusan</code>), lalu baris berikutnya data siswa.
                    Setelah selesai, simpan melalui <strong>File &gt; Save As &gt; CSV (Comma delimited) (*.csv)</strong> atau <strong>CSV UTF-8 (*.csv)</strong> dan unggah ke formulir di sebelah kiri.
                </p>
            </div>
        </section>
    </div>
@endsection
