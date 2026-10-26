@extends('layouts.app')

@section('title', 'Perangkat')
@section('note', 'Pusat pengaturan sensor sidik jari dan perangkat absen')

@section('content')
    <style>
        /* ========================================================
           Desain Modern Halaman Perangkat Absen
           Sesuai Pedoman Rules.md (No dot indicators, clean UI)
           ======================================================== */
        .devices-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .devices-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        @media (max-width: 768px) {
            .devices-stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .device-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 1px 4px rgba(0, 30, 64, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .device-stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .device-stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .device-stat-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .device-stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 4px;
        }

        .device-stat-subtext {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }

        .device-panel-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
        }

        .device-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }

        .device-panel-title h2 {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px 0;
        }

        .device-panel-title p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .device-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .device-badge.status-open {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .device-badge.status-wait {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .device-badge.status-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .device-grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 16px;
        }

        .device-item-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 1px 4px rgba(0, 30, 64, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .btn-device-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }

        .btn-device-primary {
            background: #0284c7;
            color: #ffffff;
        }

        .btn-device-primary:hover {
            background: #0369a1;
        }

        .btn-device-secondary {
            background: #f1f5f9;
            color: #475569;
            border-color: #cbd5e1;
        }

        .btn-device-secondary:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .btn-device-ghost {
            background: #f8fafc;
            color: #334155;
            border-color: #cbd5e1;
        }

        .btn-device-ghost:hover {
            background: #f1f5f9;
        }

        .btn-device-del {
            background: #ffffff;
            color: #dc2626;
            border-color: #fecaca;
        }

        .btn-device-del:hover {
            background: #fef2f2;
        }
    </style>

    <div class="devices-container" data-live="perangkat">
        {{-- Metric Stats --}}
        <section class="devices-stats-grid">
            <div class="device-stat-card">
                <div class="device-stat-top">
                    <span class="device-stat-label">Perangkat Terdaftar</span>
                    <span class="device-stat-pill">SENSOR</span>
                </div>
                <div class="device-stat-value">
                    {{ $stats['total'] }}
                </div>
                <div class="device-stat-subtext">
                    {{ $stats['aktif'] }} perangkat sedang aktif
                </div>
            </div>

            <div class="device-stat-card">
                <div class="device-stat-top">
                    <span class="device-stat-label">Menunggu Verifikasi</span>
                    <span class="device-stat-pill">BARU</span>
                </div>
                <div class="device-stat-value">
                    {{ $stats['menunggu'] }}
                </div>
                <div class="device-stat-subtext">
                    Alat baru terhubung yang belum dikonfigurasi
                </div>
            </div>

            <div class="device-stat-card">
                <div class="device-stat-top">
                    <span class="device-stat-label">Presensi Alat</span>
                    <span class="device-stat-pill">SCAN</span>
                </div>
                <div class="device-stat-value">
                    {{ $stats['absen'] }}
                </div>
                <div class="device-stat-subtext">
                    Total catatan yang menyertakan kode alat
                </div>
            </div>
        </section>

        {{-- Perangkat Baru Menunggu Pendaftaran --}}
        @if ($pending->isNotEmpty())
            <section class="device-panel-card" style="border-color: #fde68a; background: #fffdf5;">
                <div class="device-panel-header">
                    <div class="device-panel-title">
                        <h2>Perangkat Baru Terdeteksi</h2>
                        <p>Perangkat yang baru terhubung belum bisa dipakai. Beri nama, tentukan jenis, lalu daftarkan.</p>
                    </div>
                </div>

                <div class="device-grid-cards">
                    @foreach ($pending as $device)
                        <div class="device-item-card" style="background: #ffffff;">
                            <div>
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                    <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">{{ $device->name }}</h3>
                                    <span class="device-badge status-wait">Menunggu</span>
                                </div>
                                <p style="font-size: 12px; color: #64748b; margin-bottom: 12px;">
                                    ID Alat: <code>{{ $device->device_id }}</code> &bull; Terakhir aktif: {{ $device->last_seen_at?->translatedFormat('d M H:i') ?? 'Belum pernah' }}
                                </p>
                            </div>

                            <form method="POST" action="{{ route('devices.update', $device) }}">
                                @csrf
                                @method('PUT')

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                                    <div>
                                        <label style="display: block; font-size: 11.5px; font-weight: 600; color: #475569; margin-bottom: 3px;">Nama Perangkat</label>
                                        <input class="input-field" style="padding: 6px 10px; font-size: 12.5px; width: 100%; border-radius: 7px; border: 1px solid #cbd5e1;" type="text" name="name" value="{{ $device->name }}" required>
                                    </div>

                                    <div>
                                        <label style="display: block; font-size: 11.5px; font-weight: 600; color: #475569; margin-bottom: 3px;">Jenis</label>
                                        <select class="select-field" style="padding: 6px 10px; font-size: 12.5px; width: 100%; border-radius: 7px; border: 1px solid #cbd5e1;" name="type" required>
                                            @foreach ($types as $value => $label)
                                                <option value="{{ $value }}" @selected($device->type === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div style="margin-bottom: 12px;">
                                    <label style="display: block; font-size: 11.5px; font-weight: 600; color: #475569; margin-bottom: 3px;">Letak Perangkat</label>
                                    <input class="input-field" style="padding: 6px 10px; font-size: 12.5px; width: 100%; border-radius: 7px; border: 1px solid #cbd5e1;" type="text" name="location" value="{{ $device->location }}" placeholder="Musala lantai 1">
                                </div>

                                <div style="display: flex; gap: 8px;">
                                    <button class="btn-device-action btn-device-ghost" style="padding: 6px 12px;" type="submit">
                                        Simpan
                                    </button>
                            </form>

                                    <form method="POST" action="{{ route('devices.register', $device) }}"
                                        data-confirm="Daftarkan perangkat {{ $device->name }}? Status awalnya akan dimatikan sampai Anda menyalakannya.">
                                        @csrf
                                        <button class="btn-device-action btn-device-primary" style="padding: 6px 12px;" type="submit">
                                            Daftarkan
                                        </button>
                                    </form>
                                </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Daftar Perangkat Terdaftar --}}
        <section class="device-panel-card">
            <div class="device-panel-header">
                <div class="device-panel-title">
                    <h2>Daftar Perangkat Absen Terdaftar</h2>
                    <p>Kelola sensor sidik jari AS608, tablet, dan pemindai absensi yang terhubung ke sistem.</p>
                </div>
            </div>

            @if ($registered->isEmpty())
                <div style="padding: 32px 18px; text-align: center; color: #64748b; font-size: 13.5px;">
                    Belum ada perangkat yang didaftarkan di sistem.
                </div>
            @else
                <div class="device-grid-cards">
                    @foreach ($registered as $device)
                        <div class="device-item-card">
                            <div>
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                    <h3 style="font-size: 15.5px; font-weight: 700; color: #0f172a; margin: 0;">{{ $device->name }}</h3>
                                    <span class="device-badge {{ \App\Support\Status::device($device) }}">
                                        {{ $device->statusLabel() }}
                                    </span>
                                </div>

                                <p style="font-size: 12px; color: #64748b; margin-bottom: 12px;">
                                    {{ $device->typeLabel() }}{{ $device->location ? ' · ' . $device->location : '' }} &bull;
                                    ID: <code>{{ $device->device_id }}</code> &bull;
                                    Terakhir: {{ $device->last_seen_at?->translatedFormat('d M H:i') ?? 'Belum pernah' }}
                                </p>
                            </div>

                            <form method="POST" action="{{ route('devices.update', $device) }}" style="padding-top: 10px; border-top: 1px solid #f1f5f9;">
                                @csrf
                                @method('PUT')

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
                                    <div>
                                        <label style="display: block; font-size: 11.5px; font-weight: 600; color: #475569; margin-bottom: 3px;">Nama</label>
                                        <input class="input-field" style="padding: 6px 10px; font-size: 12.5px; width: 100%; border-radius: 7px; border: 1px solid #cbd5e1;" type="text" name="name" value="{{ $device->name }}" required>
                                    </div>

                                    <div>
                                        <label style="display: block; font-size: 11.5px; font-weight: 600; color: #475569; margin-bottom: 3px;">Jenis</label>
                                        <select class="select-field" style="padding: 6px 10px; font-size: 12.5px; width: 100%; border-radius: 7px; border: 1px solid #cbd5e1;" name="type" required>
                                            @foreach ($types as $value => $label)
                                                <option value="{{ $value }}" @selected($device->type === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div style="margin-bottom: 10px;">
                                    <label style="display: block; font-size: 11.5px; font-weight: 600; color: #475569; margin-bottom: 3px;">Letak Perangkat</label>
                                    <input class="input-field" style="padding: 6px 10px; font-size: 12.5px; width: 100%; border-radius: 7px; border: 1px solid #cbd5e1;" type="text" name="location" value="{{ $device->location }}">
                                </div>

                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                    <button class="btn-device-action btn-device-ghost" style="padding: 6px 12px;" type="submit">
                                        Simpan
                                    </button>
                            </form>

                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <form method="POST" action="{{ route('devices.status', $device) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="active" value="{{ $device->isActive() ? 0 : 1 }}">
                                            <button class="btn-device-action {{ $device->isActive() ? 'btn-device-secondary' : 'btn-device-primary' }}" style="padding: 6px 12px;" type="submit">
                                                {{ $device->isActive() ? 'Matikan' : 'Nyalakan' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('devices.destroy', $device) }}"
                                            data-confirm="Hapus perangkat {{ $device->name }}? Riwayat absen tetap tersimpan.">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn-device-action btn-device-del" style="padding: 6px 10px;" type="submit" title="Hapus perangkat">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
