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

        .prayer-chips-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        @media (max-width: 900px) {
            .prayer-chips-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 500px) {
            .prayer-chips-grid {
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
            grid-template-columns: repeat(auto-fit, minmax(min(320px, 100%), 1fr));
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

        /* Kartu Sinkronisasi KHGT Muhammadiyah */
        .khgt-meta-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 11px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 14px;
            font-size: 12.5px;
            color: #334155;
        }

        .khgt-times-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 8px;
            margin-bottom: 14px;
        }

        .khgt-time-pill {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .khgt-time-name {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            text-transform: capitalize;
        }

        .khgt-time-val {
            font-family: var(--font-heading);
            font-size: 13.5px;
            font-weight: 800;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
        }

        .khgt-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }
    </style>

    @php
        $khgt = $khgtConfig ?? [
            'auto_sync' => true,
            'latitude' => '-0.464768',
            'longitude' => '117.138239',
            'window_minutes' => 45,
            'last_synced_at' => null,
            'last_location' => null,
            'last_times' => [],
            'api_url' => 'https://khgt.muhammadiyah.or.id/prayer?lat=-0.464768&long=117.138239',
        ];
    @endphp

    <div class="prayer-container">
        {{-- 0. SINKRONISASI OTOMATIS API KHGT MUHAMMADIYAH --}}
        <section class="prayer-card" id="sinkron-khgt">
            <div class="prayer-card-header">
                <div class="prayer-card-title">
                    <h2>Sinkronisasi Jadwal Sholat KHGT Muhammadiyah</h2>
                    <p>
                        Ambil jam mulai waktu sholat otomatis dari API resmi Kalender Hijriah Global Tunggal (<code>khgt.muhammadiyah.or.id</code>) sesuai koordinat lokasi sekolah.
                    </p>
                </div>

                <span class="prayer-badge {{ !empty($khgt['auto_sync']) ? 'status-open' : 'status-closed' }}">
                    {{ !empty($khgt['auto_sync']) ? 'Sinkron Harian Otomatis Aktif' : 'Mode Sinkron Manual' }}
                </span>
            </div>

            <div class="khgt-meta-bar">
                <div>
                    <strong>Lokasi API:</strong>
                    <span>{{ $khgt['last_location'] ?? 'SMK Istiqomah Muhammadiyah 4 Samarinda (-0.464768, 117.138239)' }}</span>
                </div>
                <div style="color: #64748b; font-variant-numeric: tabular-nums;">
                    @if (!empty($khgt['last_synced_at']))
                        Terakhir disinkronkan: <strong>{{ $khgt['last_synced_at'] }}</strong>
                    @else
                        Belum pernah disinkronkan
                    @endif
                </div>
            </div>

            @if (!empty($khgt['last_times']))
                <div class="khgt-times-strip" aria-label="Jadwal KHGT Terakhir">
                    @foreach ($khgt['last_times'] as $kName => $kVal)
                        <div class="khgt-time-pill">
                            <span class="khgt-time-name">{{ $kName }}</span>
                            <span class="khgt-time-val">{{ $kVal }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('prayer-times.sync-khgt') }}">
                @csrf

                <div class="khgt-form-grid">
                    <div class="prayer-input-group">
                        <label for="khgt_latitude">Latitude (Garis Lintang)</label>
                        <input class="prayer-input-mini" type="text" id="khgt_latitude" name="latitude"
                            value="{{ old('latitude', $khgt['latitude']) }}" placeholder="-0.464768" required>
                        @error('latitude')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="prayer-input-group">
                        <label for="khgt_longitude">Longitude (Garis Bujur)</label>
                        <input class="prayer-input-mini" type="text" id="khgt_longitude" name="longitude"
                            value="{{ old('longitude', $khgt['longitude']) }}" placeholder="117.138239" required>
                        @error('longitude')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="prayer-input-group">
                        <label for="khgt_window">Lama Waktu Absen Dibuka (menit)</label>
                        <input class="prayer-input-mini" type="number" id="khgt_window" name="window_minutes"
                            value="{{ old('window_minutes', $khgt['window_minutes']) }}" min="10" max="180" required>
                        <span style="display:block;font-size:11px;color:#64748b;margin-top:3px;">Rentang dari jam mulai sampai jam selesai absen (contoh: 60 menit)</span>
                        @error('window_minutes')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                    <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; cursor: pointer;">
                        <input type="checkbox" name="auto_sync" value="1" @checked(old('auto_sync', $khgt['auto_sync'])) style="width: 16px; height: 16px;">
                        <span>Perbarui jadwal waktu sholat otomatis setiap hari dari API KHGT Muhammadiyah</span>
                    </label>

                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <a href="{{ $khgt['api_url'] }}" target="_blank" rel="noopener"
                            style="font-size: 12px; font-weight: 600; color: #475569; padding: 7px 12px; border-radius: 7px; border: 1px solid #cbd5e1; background: #ffffff; text-decoration: none;">
                            Lihat JSON API
                        </a>
                        <button class="btn-prayer-save" style="padding: 8px 16px; font-size: 13px;" type="submit">
                            Ambil &amp; Sinkronkan dari KHGT Sekarang
                        </button>
                    </div>
                </div>
            </form>
        </section>

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
                        @php
                            $checked = in_array($dayNumber, old('days', $activeDays), false);
                        @endphp
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

        {{-- 2. PILIHAN WAKTU SOLAT YANG DIPAKAI (SISTEM CEKBOX 6 WAKTU) --}}
        @php
            $activePrayerCount = $prayers->where('is_active', true)->count();
        @endphp
        <section class="prayer-card" id="pilihan-waktu-solat">
            <div class="prayer-card-header">
                <div class="prayer-card-title">
                    <h2>Pilihan Waktu Solat yang Dipakai</h2>
                    <p>
                        Centang waktu solat mana saja yang ingin diambil atau dipakai oleh sekolah (total 6 waktu: Subuh, Duha, Zuhur, Ashar, Maghrib, dan Isya). Data di bawah dapat diedit jam serta toleransinya, namun tidak dapat dihapus.
                    </p>
                </div>

                <span class="prayer-badge {{ $activePrayerCount > 0 ? 'status-open' : 'status-closed' }}">
                    {{ $activePrayerCount }} dari {{ $prayers->count() }} Waktu Dipakai
                </span>
            </div>

            <form method="POST" action="{{ route('prayer-times.active.update') }}">
                @csrf
                @method('PUT')

                <div class="prayer-chips-grid">
                    @foreach ($prayers as $prayer)
                        <label class="day-chip-label" for="check-prayer-{{ $prayer->id }}">
                            <input type="checkbox" id="check-prayer-{{ $prayer->id }}" name="prayers[]"
                                value="{{ $prayer->prayer_name }}" @checked($prayer->is_active)>
                            <span class="day-chip-name">{{ $prayer->label }}</span>
                            <span style="font-size: 11px; color: #64748b; margin-bottom: 4px; font-variant-numeric: tabular-nums;">
                                {{ $prayer->shortTime($prayer->start_time) }} – {{ $prayer->shortTime($prayer->end_time) }}
                            </span>
                            <span class="day-chip-status {{ $prayer->is_active ? 'is-active' : 'is-off' }}">
                                {{ $prayer->is_active ? 'Dipakai' : 'Nonaktif' }}
                            </span>
                        </label>
                    @endforeach
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f1f5f9;">
                    <button class="btn-prayer-save" style="padding: 8px 16px; font-size: 13px;" type="submit">
                        Simpan Pilihan Waktu Solat
                    </button>
                    <span style="font-size: 12.5px; color: #64748b;">
                        Sesi absen otomatis dibuka {{ $earlyOpenMinutes }} menit sebelum jam mulai pada waktu solat yang dicentang.
                    </span>
                </div>
            </form>
        </section>

        {{-- 3. PENGATURAN JAM & TOLERANSI 6 WAKTU SOLAT (BISA DIEDIT, TIDAK BISA DIHAPUS) --}}
        <div data-live="waktu-solat">
            <div class="prayer-list-grid">
                @foreach ($prayers as $prayer)
                    <div class="prayer-item-card" style="{{ !$prayer->is_active ? 'opacity: 0.78; background: #f8fafc;' : '' }}">
                        <div class="prayer-item-top">
                            <div class="prayer-item-title-box">
                                <h3>{{ $prayer->label }}</h3>
                                <span>Kode: <code>{{ $prayer->prayer_name }}</code> &bull; Toleransi {{ $prayer->late_minutes }} mnt</span>
                            </div>

                            <span class="prayer-badge {{ $prayer->is_active ? 'status-open' : 'status-closed' }}">
                                {{ $prayer->is_active ? 'Dipakai' : 'Tidak Dipakai' }}
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
                                    <label for="prayer-name-{{ $prayer->id }}">Kode (Tetap)</label>
                                    <input class="prayer-input-mini" type="text" id="prayer-name-{{ $prayer->id }}" name="prayer_name" value="{{ $prayer->prayer_name }}" readonly style="background: #f1f5f9; color: #64748b; cursor: not-allowed;">
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
                                    <input class="prayer-input-mini" type="number" id="sort-{{ $prayer->id }}" name="sort_order" value="{{ $prayer->sort_order }}" min="1" max="6" required>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 7px; margin-top: 4px;">
                                <input type="checkbox" id="active-{{ $prayer->id }}" name="is_active" value="1" @checked($prayer->is_active)>
                                <label for="active-{{ $prayer->id }}" style="font-size: 12px; color: #475569; cursor: pointer;">Pakai / aktifkan waktu solat ini</label>
                            </div>

                            <div class="prayer-bottom-actions">
                                <button class="btn-prayer-save" type="submit">
                                    Simpan Perubahan
                                </button>
                                <span style="font-size: 11.5px; color: #64748b;">
                                    Data tetap &bull; Tidak dapat dihapus
                                </span>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
