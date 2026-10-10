@extends('layouts.app')

@section('title', 'Waktu Solat')
@section('note', 'Jam acuan pembukaan sesi absen setiap hari')

@section('content')
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Tambah waktu solat</h2>
                <p class="section-note">
                    Sesi absen dibuat dari daftar ini dan jendelanya mengikuti jam di sini. Sesinya disiapkan sendiri
                    setiap hari sesudah tengah malam, dibuka sendiri
                    {{ $earlyOpenMinutes }} menit sebelum jam mulai, dan ditutup di akhir jendela. Waktu solat yang
                    tidak aktif tidak ikut dibuat.
                </p>
            </div>
        </div>

        <div class="tool-list">
            <details class="tool-panel" data-record="tool-tambah-waktu-solat" @if ($errors->any()) open @endif>
                <summary>
                    <span class="tool-name">Formulir waktu solat</span>
                    <span class="tool-note">
                        Isi kode, nama, jam mulai, jam selesai, toleransi terlambat, dan urutan tampilnya.
                    </span>
                    <span class="tool-hint">
                        <span class="tool-hint-open">Buka</span>
                        <span class="tool-hint-close">Tutup</span>
                    </span>
                </summary>

                <div class="tool-body">
                    @if (!empty($suggestions))
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Usulan waktu solat</th>
                                        <th>Kode</th>
                                        <th>Jam usulan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($suggestions as $default)
                                        <tr>
                                            <td class="cell-strong">{{ $default['label'] }}</td>
                                            <td>{{ $default['prayer_name'] }}</td>
                                            <td>{{ $default['start_time'] }} sampai {{ $default['end_time'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('prayer-times.store') }}">
                        @csrf

                        <div class="form-grid">
                            <div class="field">
                                <label class="field-label" for="prayer_name">Kode waktu solat</label>
                                <input class="input-field" type="text" id="prayer_name" name="prayer_name"
                                    value="{{ old('prayer_name') }}" placeholder="subuh" required>
                                <p class="field-hint">Huruf kecil tanpa spasi, dipakai sistem untuk mencocokkan
                                    sesi.</p>
                                @error('prayer_name')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="field">
                                <label class="field-label" for="label">Nama tampilan</label>
                                <input class="input-field" type="text" id="label" name="label"
                                    value="{{ old('label') }}" placeholder="Solat Subuh" required>
                                @error('label')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="field">
                                <label class="field-label" for="start_time">Jam mulai</label>
                                <input class="input-field" type="time" id="start_time" name="start_time"
                                    value="{{ old('start_time') }}" required>
                                @error('start_time')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="field">
                                <label class="field-label" for="end_time">Jam selesai</label>
                                <input class="input-field" type="time" id="end_time" name="end_time"
                                    value="{{ old('end_time') }}" required>
                                <p class="field-hint">Harus lebih lambat daripada jam mulai.</p>
                                @error('end_time')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="field">
                                <label class="field-label" for="late_minutes">Toleransi terlambat</label>
                                <input class="input-field" type="number" id="late_minutes" name="late_minutes"
                                    value="{{ old('late_minutes', config('solat.late_minutes')) }}" min="0"
                                    max="180" required>
                                <p class="field-hint">Satuan menit dihitung dari jam mulai.</p>
                                @error('late_minutes')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="field">
                                <label class="field-label" for="sort_order">Urutan tampil</label>
                                <input class="input-field" type="number" id="sort_order" name="sort_order"
                                    value="{{ old('sort_order', $prayers->count() + 1) }}" min="0" required>
                                @error('sort_order')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="check-row">
                            <input type="checkbox" id="is_active" name="is_active" value="1"
                                @checked(old('is_active', true))>
                            <label for="is_active">Aktif dan ikut dibuat setiap hari</label>
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit">Tambah waktu solat</button>
                        </div>
                    </form>
                </div>
            </details>
        </div>
    </section>

    <div data-live="waktu-solat">
        @if ($prayers->isEmpty())
            <section class="card">
                <p class="empty-state">Belum ada waktu solat. Tambahkan lewat formulir di atas.</p>
            </section>
        @else
            <div class="grid-2">
                @foreach ($prayers as $prayer)
                    <section class="card">
                        <div class="section-head">
                            <div>
                                <h2 class="section-title">{{ $prayer->label }}</h2>
                                <p class="section-note">
                                    {{ $prayer->windowLabel() }} dengan toleransi {{ $prayer->late_minutes }} menit.
                                    Kode {{ $prayer->prayer_name }}.
                                </p>
                            </div>

                            <div class="section-actions">
                                <span class="status {{ $prayer->is_active ? 'status-open' : 'status-closed' }}">
                                    {{ $prayer->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('prayer-times.update', $prayer) }}">
                            @csrf
                            @method('PUT')

                            <div class="form-grid">
                                <div class="field">
                                    <label class="field-label" for="label-{{ $prayer->id }}">Nama tampilan</label>
                                    <input class="input-field" type="text" id="label-{{ $prayer->id }}"
                                        name="label" value="{{ $prayer->label }}" required>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="prayer-name-{{ $prayer->id }}">Kode waktu
                                        solat</label>
                                    <input class="input-field" type="text" id="prayer-name-{{ $prayer->id }}"
                                        name="prayer_name" value="{{ $prayer->prayer_name }}" required>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="start-{{ $prayer->id }}">Jam mulai</label>
                                    <input class="input-field" type="time" id="start-{{ $prayer->id }}"
                                        name="start_time" value="{{ $prayer->shortTime($prayer->start_time) }}" required>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="end-{{ $prayer->id }}">Jam selesai</label>
                                    <input class="input-field" type="time" id="end-{{ $prayer->id }}"
                                        name="end_time" value="{{ $prayer->shortTime($prayer->end_time) }}" required>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="late-{{ $prayer->id }}">Toleransi terlambat</label>
                                    <input class="input-field" type="number" id="late-{{ $prayer->id }}"
                                        name="late_minutes" value="{{ $prayer->late_minutes }}" min="0"
                                        max="180" required>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="sort-{{ $prayer->id }}">Urutan tampil</label>
                                    <input class="input-field" type="number" id="sort-{{ $prayer->id }}"
                                        name="sort_order" value="{{ $prayer->sort_order }}" min="0" required>
                                </div>
                            </div>

                            <div class="check-row">
                                <input type="checkbox" id="active-{{ $prayer->id }}" name="is_active" value="1"
                                    @checked($prayer->is_active)>
                                <label for="active-{{ $prayer->id }}">Aktif dan ikut dibuat setiap hari</label>
                            </div>

                            <div class="form-actions">
                                <button class="btn btn-primary btn-small" type="submit">Simpan</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('prayer-times.destroy', $prayer) }}"
                            data-confirm="Hapus waktu solat {{ $prayer->label }}?">
                            @csrf
                            @method('DELETE')

                            <div class="form-actions">
                                <button class="btn btn-small btn-danger" type="submit">Hapus</button>
                                <span class="field-hint">
                                    Dipakai {{ $sessionTotals[$prayer->prayer_name] ?? 0 }} sesi absen.
                                </span>
                            </div>
                        </form>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endsection
