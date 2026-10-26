@extends('layouts.app')

@section('title', 'Waktu Solat')
@section('note', 'Jam acuan dan hari aktif pembukaan sesi absen sekolah')

@section('content')
    <style>
        /* ========================================================
           Desain Modern Halaman Waktu Solat & Hari Absen
           Sesuai Pedoman Rules.md (No dot indicators, clean UI)
           ======================================================== */
        .prayer-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .prayer-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
        }

        .prayer-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }

        .prayer-card-title h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px 0;
        }

        .prayer-card-title p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
            max-width: 75ch;
        }

        /* Day Picker Chips Grid */
        .day-chips-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        @media (max-width: 900px) {
            .day-chips-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 500px) {
            .day-chips-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .day-chip-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 14px 10px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            cursor: pointer;
            transition: all 0.15s ease;
            position: relative;
        }

        .day-chip-label:hover {
            border-color: #94a3b8;
            background: #f1f5f9;
        }

        .day-chip-label input[type="checkbox"] {
            margin-bottom: 8px;
            width: 17px;
            height: 17px;
            cursor: pointer;
        }

        .day-chip-name {
            font-size: 13.5px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .day-chip-status {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 6px;
        }

        .day-chip-status.is-active {
            background: #dcfce7;
            color: #15803d;
        }

        .day-chip-status.is-off {
            background: #f1f5f9;
            color: #64748b;
        }

        /* Prayer Times Cards Grid */
        .prayer-list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 16px;
        }

        .prayer-item-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 1px 4px rgba(0, 30, 64, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color 0.15s ease;
        }

        .prayer-item-card:hover {
            border-color: #cbd5e1;
        }

        .prayer-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .prayer-item-title-box h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2px 0;
        }

        .prayer-item-title-box span {
            font-size: 12px;
            color: #64748b;
        }

        .prayer-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .prayer-badge.status-open {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .prayer-badge.status-closed {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .prayer-edit-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
        }

        .prayer-form-grid-mini {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .prayer-input-group label {
            display: block;
            font-size: 11.5px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 3px;
        }

        .prayer-input-mini {
            width: 100%;
            padding: 6px 9px;
            border-radius: 7px;
            border: 1px solid #cbd5e1;
            font-size: 12.5px;
            color: #1e293b;
            background: #ffffff;
            outline: none;
        }

        .prayer-input-mini:focus {
            border-color: #0284c7;
        }

        .prayer-bottom-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 10px;
            margin-top: 10px;
            border-top: 1px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 8px;
        }

        .btn-prayer-save {
            padding: 6px 14px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            background: #0284c7;
            color: #ffffff;
            border: none;
            cursor: pointer;
        }

        .btn-prayer-save:hover {
            background: #0369a1;
        }

        .btn-prayer-del {
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            background: #ffffff;
            color: #dc2626;
            border: 1px solid #fecaca;
            cursor: pointer;
        }

        .btn-prayer-del:hover {
            background: #fef2f2;
        }

        /* Collapsible Form Tambah Waktu Solat */
        .add-prayer-details {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 18px;
        }

        .add-prayer-details summary {
            font-size: 13.5px;
            font-weight: 700;
            color: #0284c7;
            cursor: pointer;
            outline: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .add-prayer-body {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
        }
    </style>

    <div class="prayer-container">
        {{-- 1. HARI ABSEN SEKOLAH --}}
        <section class="prayer-card" id="hari-absen">
            <div class="prayer-card-header">
                <div class="prayer-card-title">
                    <h2>Hari Aktif Presensi Sekolah</h2>
                    <p>
                        Pilih hari sekolah di mana sesi absen solat disiapkan dan dibuka otomatis. Pada hari libur, sistem tidak akan membuat sesi otomatis kecuali dibuat manual oleh admin.
                    </p>
                </div>

                <span class="prayer-badge status-open">
                    {{ $daysSummary['summary'] }}
                </span>
            </div>

            <form method="POST" action="{{ route('prayer-times.days.update') }}">
                @csrf
                @method('PUT')

                <div class="day-chips-grid">
                    @foreach ($dayOptions as $dayNumber => $dayLabel)
                        @php($checked = in_array($dayNumber, old('days', $activeDays), false))
                        <label class="day-chip-label" for="day-{{ $dayNumber }}">
                            <input type="checkbox" id="day-{{ $dayNumber }}" name="days[]" value="{{ $dayNumber }}" @checked($checked)>
                            <span class="day-chip-name">{{ $dayLabel }}</span>
                            <span class="day-chip-status {{ in_array($dayNumber, $activeDays, true) ? 'is-active' : 'is-off' }}">
                                {{ in_array($dayNumber, $activeDays, true) ? 'Aktif' : 'Libur' }}
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('days')
                    <p class="field-error" style="margin-top: 10px;">{{ $message }}</p>
                @enderror

                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f1f5f9;">
                    <button class="btn-prayer-save" style="padding: 8px 16px; font-size: 13px;" type="submit">
                        Simpan Hari Aktif
                    </button>
                    <span style="font-size: 12.5px; color: #64748b;">
                        {{ count($activeDays) }} hari aktif dalam sepekan.
                    </span>
                </div>
            </form>
        </section>

        {{-- 2. FORM TAMBAH WAKTU SOLAT (COLLAPSIBLE) --}}
        <section class="prayer-card">
            <details class="add-prayer-details" @if ($errors->any()) open @endif>
                <summary>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Tambah Waktu Solat Baru
                </summary>

                <div class="add-prayer-body">
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 14px;">
                        Sesi absen harian dibuat mengikuti jam di sini. Dibuka otomatis {{ $earlyOpenMinutes }} menit sebelum jam mulai.
                    </p>

                    <form method="POST" action="{{ route('prayer-times.store') }}">
                        @csrf

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 14px;">
                            <div class="prayer-input-group">
                                <label for="prayer_name">Kode Waktu Solat (cth: subuh)</label>
                                <input class="prayer-input-mini" type="text" id="prayer_name" name="prayer_name" value="{{ old('prayer_name') }}" placeholder="subuh" required>
                                @error('prayer_name')<p class="field-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="prayer-input-group">
                                <label for="label">Nama Tampilan</label>
                                <input class="prayer-input-mini" type="text" id="label" name="label" value="{{ old('label') }}" placeholder="Solat Subuh" required>
                                @error('label')<p class="field-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="prayer-input-group">
                                <label for="start_time">Jam Mulai</label>
                                <input class="prayer-input-mini" type="time" id="start_time" name="start_time" value="{{ old('start_time') }}" required>
                                @error('start_time')<p class="field-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="prayer-input-group">
                                <label for="end_time">Jam Selesai</label>
                                <input class="prayer-input-mini" type="time" id="end_time" name="end_time" value="{{ old('end_time') }}" required>
                                @error('end_time')<p class="field-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="prayer-input-group">
                                <label for="late_minutes">Toleransi Terlambat (menit)</label>
                                <input class="prayer-input-mini" type="number" id="late_minutes" name="late_minutes" value="{{ old('late_minutes', config('solat.late_minutes')) }}" min="0" max="180" required>
                                @error('late_minutes')<p class="field-error">{{ $message }}</p>@enderror
                            </div>

                            <div class="prayer-input-group">
                                <label for="sort_order">Urutan Tampil</label>
                                <input class="prayer-input-mini" type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $prayers->count() + 1) }}" min="0" required>
                                @error('sort_order')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px;">
                            <input type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', true))>
                            <label for="is_active" style="font-size: 13px; color: #334155;">Aktif dan otomatis disiapkan setiap hari</label>
                        </div>

                        <button class="btn-prayer-save" style="padding: 8px 16px; font-size: 13px;" type="submit">
                            Simpan Waktu Solat
                        </button>
                    </form>
                </div>
            </details>
        </section>

        {{-- 3. DAFTAR WAKTU SOLAT AKTIF --}}
        <div data-live="waktu-solat">
            <div class="prayer-list-grid">
                @foreach ($prayers as $prayer)
                    <div class="prayer-item-card">
                        <div class="prayer-item-top">
                            <div class="prayer-item-title-box">
                                <h3>{{ $prayer->label }}</h3>
                                <span>Kode: <code>{{ $prayer->prayer_name }}</code> &bull; Toleransi {{ $prayer->late_minutes }} mnt</span>
                            </div>

                            <span class="prayer-badge {{ $prayer->is_active ? 'status-open' : 'status-closed' }}">
                                {{ $prayer->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>

                        <form method="POST" action="{{ route('prayer-times.update', $prayer) }}" class="prayer-edit-form">
                            @csrf
                            @method('PUT')

                            <div class="prayer-form-grid-mini">
                                <div class="prayer-input-group">
                                    <label for="label-{{ $prayer->id }}">Nama</label>
                                    <input class="prayer-input-mini" type="text" id="label-{{ $prayer->id }}" name="label" value="{{ $prayer->label }}" required>
                                </div>

                                <div class="prayer-input-group">
                                    <label for="prayer-name-{{ $prayer->id }}">Kode</label>
                                    <input class="prayer-input-mini" type="text" id="prayer-name-{{ $prayer->id }}" name="prayer_name" value="{{ $prayer->prayer_name }}" required>
                                </div>

                                <div class="prayer-input-group">
                                    <label for="start-{{ $prayer->id }}">Jam Mulai</label>
                                    <input class="prayer-input-mini" type="time" id="start-{{ $prayer->id }}" name="start_time" value="{{ $prayer->shortTime($prayer->start_time) }}" required>
                                </div>

                                <div class="prayer-input-group">
                                    <label for="end-{{ $prayer->id }}">Jam Selesai</label>
                                    <input class="prayer-input-mini" type="time" id="end-{{ $prayer->id }}" name="end_time" value="{{ $prayer->shortTime($prayer->end_time) }}" required>
                                </div>

                                <div class="prayer-input-group">
                                    <label for="late-{{ $prayer->id }}">Toleransi (mnt)</label>
                                    <input class="prayer-input-mini" type="number" id="late-{{ $prayer->id }}" name="late_minutes" value="{{ $prayer->late_minutes }}" min="0" max="180" required>
                                </div>

                                <div class="prayer-input-group">
                                    <label for="sort-{{ $prayer->id }}">Urutan</label>
                                    <input class="prayer-input-mini" type="number" id="sort-{{ $prayer->id }}" name="sort_order" value="{{ $prayer->sort_order }}" min="0" required>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 7px; margin-top: 4px;">
                                <input type="checkbox" id="active-{{ $prayer->id }}" name="is_active" value="1" @checked($prayer->is_active)>
                                <label for="active-{{ $prayer->id }}" style="font-size: 12px; color: #475569;">Aktifkan sesi ini setiap hari</label>
                            </div>

                            <div class="prayer-bottom-actions">
                                <button class="btn-prayer-save" type="submit">
                                    Perbarui
                                </button>
                        </form>

                                <form method="POST" action="{{ route('prayer-times.destroy', $prayer) }}"
                                    data-confirm="Hapus waktu solat {{ $prayer->label }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-prayer-del" type="submit" title="Hapus waktu solat ini">
                                        Hapus
                                    </button>
                                </form>
                            </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
