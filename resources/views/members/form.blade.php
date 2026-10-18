@extends('layouts.app')

@section('title', $mode === 'create' ? 'Tambah anggota' : 'Ubah anggota')
@section('note', $mode === 'create' ? 'Daftarkan murid, guru, atau petugas baru' : 'Perbarui data ' . $member->name)

@section('content')
    <div class="grid-2">
        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Data anggota</h2>
                    <p class="section-note">
                        Nomor induk dipakai sebagai sandi awal murid. Sandi guru dan petugas memakai sandi bawaan aplikasi.
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ $mode === 'create' ? route('members.store') : route('members.update', $member) }}"
                enctype="multipart/form-data">
                @csrf

                @if ($mode === 'edit')
                    @method('PUT')
                @endif

                <div class="form-grid">
                    <div class="field">
                        <label class="field-label" for="name">Nama lengkap</label>
                        <input class="input-field" type="text" id="name" name="name"
                            value="{{ old('name', $member->name) }}" required>
                        @error('name')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="identifier_number">Nomor induk</label>
                        <input class="input-field" type="text" id="identifier_number" name="identifier_number"
                            value="{{ old('identifier_number', $member->identifier_number) }}" required>
                        <p class="field-hint">Bisa nomor induk siswa, nomor pegawai, atau kode petugas.</p>
                        @error('identifier_number')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="email">Gmail / Email (Opsional)</label>
                        <input class="input-field" type="email" id="email" name="email"
                            value="{{ old('email', $member->email) }}">
                        <p class="field-hint">Boleh dikosongkan. Anggota tetap bisa masuk menggunakan nomor induk / NISN.</p>
                        @error('email')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="role">Peran</label>
                        <select class="select-field" id="role" name="role" required>
                            @foreach (\App\Models\User::roles() as $value => $label)
                                <option value="{{ $value }}" @selected(old('role', $member->role) === $value)>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="class_name">Kelas</label>
                        <input class="input-field" type="text" id="class_name" name="class_name"
                            value="{{ old('class_name', $member->class_name) }}" list="kelas-tersedia">
                        <datalist id="kelas-tersedia">
                            @foreach ($classes as $class)
                                <option value="{{ $class }}"></option>
                            @endforeach
                        </datalist>
                        <p class="field-hint">Wajib untuk murid, boleh dikosongkan untuk guru dan petugas.</p>
                        @error('class_name')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="phone_number">Nomor telepon</label>
                        <input class="input-field" type="text" id="phone_number" name="phone_number"
                            value="{{ old('phone_number', $member->phone_number) }}">
                        <p class="field-hint">Boleh dikosongkan.</p>
                        @error('phone_number')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="password">Sandi</label>
                        <input class="input-field" type="password" id="password" name="password"
                            autocomplete="new-password">
                        <p class="field-hint">
                            {{ $mode === 'create'
                                ? 'Boleh dikosongkan, sandi awal otomatis mengikuti peran.'
                                : 'Kosongkan bila sandi tidak ingin diubah. Wajib diganti saat anggota masuk pertama kali.' }}
                        </p>
                        @error('password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="photo">Foto wajah</label>
                        <input class="input-field" type="file" id="photo" name="photo" accept="image/*">
                        <p class="field-hint">Opsional. Satu foto, ukuran paling besar 5 MB.</p>
                        @error('photo')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="check-row">
                    <input type="checkbox" id="can_scan_face" name="can_scan_face" value="1"
                        @checked(old('can_scan_face', $member->can_scan_face))>
                    <label for="can_scan_face">Boleh menjadi petugas pemindai wajah di halaman absen wajah</label>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary"
                        type="submit">{{ $mode === 'create' ? 'Simpan anggota' : 'Simpan perubahan' }}</button>
                    <a class="btn btn-text" href="{{ route('members.index') }}">Batal</a>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Data wajah</h2>
                    <p class="section-note">Wajah dipakai kamera untuk mengenali jamaah saat absen.</p>
                </div>
            </div>

            @if ($mode === 'create')
                <p class="empty-state">Data wajah dapat ditambahkan setelah anggota tersimpan.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <tbody>
                            <tr>
                                <td>Foto wajah tersimpan</td>
                                <td class="cell-strong">{{ $member->face_embeddings_count }} foto</td>
                            </tr>
                            <tr>
                                <td>Sandi terakhir diubah</td>
                                <td>{{ $member->password_changed_at?->translatedFormat('d F Y H:i') ?? 'Belum pernah' }}
                                </td>
                            </tr>
                            <tr>
                                <td>Masuk terakhir</td>
                                <td>{{ $member->last_login_at?->translatedFormat('d F Y H:i') ?? 'Belum pernah' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="form-actions">
                    <a class="btn btn-outline btn-small" href="{{ route('face.index', ['q' => $member->name]) }}">Kelola
                        foto wajah</a>

                    <form method="POST" action="{{ route('members.password', $member) }}"
                        data-confirm="Kembalikan sandi {{ $member->name }} ke sandi awal?">
                        @csrf
                        <button class="btn btn-small btn-secondary" type="submit">Reset sandi</button>
                    </form>
                </div>
            @endif
        </section>
    </div>
@endsection
