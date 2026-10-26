@extends('layouts.app')

@section('title', 'Data Sidik Jari')
@section('note', 'Pilih dan daftarkan anggota yang diizinkan memakai mode absen sidik jari')

@section('content')
    <style>
        /* ========================================================
           Desain Modern Halaman Data Sidik Jari
           Sesuai Pedoman Rules.md (No dot indicators, clean UI)
           ======================================================== */
        .fp-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .fp-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        @media (max-width: 992px) {
            .fp-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .fp-stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .fp-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 1px 4px rgba(0, 30, 64, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .fp-stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .fp-stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .fp-stat-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .fp-stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 4px;
        }

        .fp-stat-subtext {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }

        /* Pending Enroll Banner */
        .fp-enroll-banner {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
        }

        .fp-enroll-left {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .fp-enroll-icon {
            color: #15803d;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .fp-enroll-text h4 {
            font-size: 14.5px;
            font-weight: 700;
            color: #166534;
            margin: 0 0 3px 0;
        }

        .fp-enroll-text p {
            font-size: 12.5px;
            color: #15803d;
            margin: 0;
        }

        /* Layout Grid Tengah */
        .fp-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .fp-grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .fp-panel-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
        }

        .fp-panel-title h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px 0;
        }

        .fp-panel-title p {
            font-size: 12.5px;
            color: #64748b;
            margin: 0 0 16px 0;
        }

        .btn-fp-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }

        .btn-fp-primary {
            background: #0284c7;
            color: #ffffff;
        }

        .btn-fp-primary:hover {
            background: #0369a1;
        }

        .btn-fp-ghost {
            background: #f8fafc;
            color: #334155;
            border-color: #cbd5e1;
        }

        .btn-fp-ghost:hover {
            background: #f1f5f9;
        }

        /* Table */
        .fp-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .fp-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .fp-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-align: left;
            padding: 12px 18px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .fp-table td {
            padding: 13px 18px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }

        .fp-table tr:hover td {
            background: #fafafa;
        }

        .fp-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 7px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .fp-badge.status-open {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .fp-badge.status-closed {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
    </style>

    <div class="fp-container" data-live="sidik-jari">
        {{-- Metric Stats --}}
        <section class="fp-stats-grid">
            <div class="fp-stat-card">
                <div class="fp-stat-top">
                    <span class="fp-stat-label">Anggota Terdaftar</span>
                    <span class="fp-stat-pill">SIDIK JARI</span>
                </div>
                <div class="fp-stat-value">
                    {{ $stats['terdaftar'] }}
                </div>
                <div class="fp-stat-subtext">
                    Dari total {{ $stats['total_anggota'] }} anggota
                </div>
            </div>

            <div class="fp-stat-card">
                <div class="fp-stat-top">
                    <span class="fp-stat-label">Template Tersimpan</span>
                    <span class="fp-stat-pill">SIDIK JARI</span>
                </div>
                <div class="fp-stat-value">
                    {{ $stats['template'] }}
                </div>
                <div class="fp-stat-subtext">
                    Mode cadangan selain wajah
                </div>
            </div>

            <div class="fp-stat-card">
                <div class="fp-stat-top">
                    <span class="fp-stat-label">Sensor Aktif</span>
                    <span class="fp-stat-pill">PERANGKAT</span>
                </div>
                <div class="fp-stat-value">
                    {{ $stats['perangkat_aktif'] }}
                </div>
                <div class="fp-stat-subtext">
                    Siap menerima scan jari
                </div>
            </div>

            <div class="fp-stat-card">
                <div class="fp-stat-top">
                    <span class="fp-stat-label">Presensi Hari Ini</span>
                    <span class="fp-stat-pill">LOG</span>
                </div>
                <div class="fp-stat-value">
                    {{ $stats['absen_hari_ini'] }}
                </div>
                <div class="fp-stat-subtext">
                    Tercatat lewat sensor AS608
                </div>
            </div>
        </section>

        {{-- Pending Enrollment Alert --}}
        @if (is_array($pendingEnroll))
            <div class="fp-enroll-banner">
                <div class="fp-enroll-left">
                    <svg class="fp-enroll-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 3"></path>
                        <path d="M14 13.12c0 2.38 0 6.38-1 8.88"></path>
                        <path d="M2 16h.01"></path>
                        <path d="M21.8 16c.2-2 .131-5.354 0-6"></path>
                        <path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2"></path>
                        <path d="M8.65 22c.21-.66.45-1.32.57-2"></path>
                        <path d="M9 6.8a6 6 0 0 1 9 5.2v2"></path>
                    </svg>
                    <div class="fp-enroll-text">
                        <h4>Menunggu Tempelan Jari: {{ $pendingEnroll['name'] ?? '-' }}</h4>
                        <p>
                            Posisi: <strong>{{ $pendingEnroll['finger_position'] ?? 'Telunjuk Kanan' }}</strong> &bull;
                            Tempelkan jari 2 kali ke sensor AS608 sebelum jam {{ $pendingEnroll['expires_at'] ?? '-' }}.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('fingerprint.command.cancel') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn-fp-action" style="background: #ffffff; color: #dc2626; border: 1px solid #fecaca;" type="submit">
                        Batalkan Perintah
                    </button>
                </form>
            </div>
        @endif

        {{-- Form Rekam & Petunjuk --}}
        <div class="fp-grid-2">
            <section class="fp-panel-card">
                <div class="fp-panel-title">
                    <h3>Daftarkan Anggota Baru</h3>
                    <p>Pilih nama anggota dan kirim perintah perekaman ke sensor AS608.</p>
                </div>

                <form method="POST" action="{{ route('fingerprint.enroll') }}">
                    @csrf

                    <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;" for="enroll-user-id">Pilih Anggota</label>
                            <select class="select-field" style="width: 100%; padding: 8px 10px; font-size: 13px; border-radius: 8px; border: 1px solid #cbd5e1;" id="enroll-user-id" name="user_id" required>
                                <option value="">Pilih nama anggota...</option>
                                @foreach ($allMembers as $item)
                                    <option value="{{ $item->id }}" @selected((int) old('user_id') === $item->id)>
                                        {{ $item->name }} ({{ $item->identifier_number }}{{ $item->class_name ? ' / ' . $item->class_name : '' }})
                                        {{ $item->fingerprints_count > 0 ? '[Sudah Ada]' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;" for="enroll-finger-position">Posisi Jari</label>
                                <select class="select-field" style="width: 100%; padding: 8px 10px; font-size: 13px; border-radius: 8px; border: 1px solid #cbd5e1;" id="enroll-finger-position" name="finger_position">
                                    @foreach ($fingerPositions as $pos)
                                        <option value="{{ $pos }}" @selected(old('finger_position', 'Telunjuk Kanan') === $pos)>{{ $pos }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px;" for="enroll-device-id">Sensor Tujuan</label>
                                <select class="select-field" style="width: 100%; padding: 8px 10px; font-size: 13px; border-radius: 8px; border: 1px solid #cbd5e1;" id="enroll-device-id" name="device_id">
                                    <option value="">Semua Sensor Aktif</option>
                                    @foreach ($activeDevices as $device)
                                        <option value="{{ $device->device_id }}" @selected(old('device_id') === $device->device_id)>{{ $device->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button class="btn-fp-action btn-fp-primary" type="submit">
                            Kirim Perintah Rekam
                        </button>
                        <a class="btn-fp-action btn-fp-ghost" href="{{ route('devices.index') }}">
                            Kelola Perangkat
                        </a>
                    </div>
                </form>
            </section>

            <section class="fp-panel-card">
                <div class="fp-panel-title">
                    <h3>Alur Presensi Sidik Jari</h3>
                    <p>Cara kerja perekaman jari dan integrasi sensor AS608.</p>
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px; font-size: 12.5px; color: #475569; line-height: 1.5;">
                    <div><strong>1.</strong> Pastikan perangkat terhubung dan berstatus aktif di menu Perangkat.</div>
                    <div><strong>2.</strong> Pilih anggota di formulir lalu tekan tombol Kirim Perintah.</div>
                    <div><strong>3.</strong> LCD alat menampilkan ID anggota. Tempelkan jari 2 kali di sensor hingga muncul notifikasi sukses.</div>
                    <div><strong>4.</strong> Saat sesi solat dibuka, anggota cukup menempelkan jari untuk mencatat kehadiran.</div>
                </div>

                <details class="info-disclosure" style="margin-top: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px;">
                    <summary style="font-size: 12px; font-weight: 600; color: #0284c7; cursor: pointer;">Alamat API IoT (Mode: {{ strtoupper($apiMode) }})</summary>
                    <div style="margin-top: 8px; font-size: 11.5px; color: #475569;">
                        <div>Lokal: <code>{{ $apiUrlOffline }}</code></div>
                        <div style="margin-top: 4px;">Online: <code>{{ $apiUrlOnline }}</code></div>
                    </div>
                </details>
            </section>
        </div>

        {{-- Daftar Anggota Sidik Jari --}}
        <section class="fp-panel-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 18px 22px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div class="fp-panel-title" style="margin: 0;">
                    <h3 style="margin: 0 0 2px 0;">Daftar Anggota Pengguna Sidik Jari</h3>
                    <p style="margin: 0;">Kelola hak akses presensi sidik jari dan rekam ulang jari.</p>
                </div>

                <form method="GET" action="{{ route('fingerprint.index') }}" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <input class="input-field" style="padding: 6px 12px; font-size: 12.5px; border-radius: 8px; border: 1px solid #cbd5e1;" type="search" name="q" value="{{ $search }}" placeholder="Cari nama atau nomor induk...">
                    <select class="select-field" style="padding: 6px 10px; font-size: 12.5px; border-radius: 8px; border: 1px solid #cbd5e1;" name="filter">
                        <option value="terdaftar" @selected($filter === 'terdaftar')>Sudah Terdaftar</option>
                        <option value="belum" @selected($filter === 'belum')>Belum Terdaftar</option>
                        <option value="semua" @selected($filter === 'semua')>Semua</option>
                    </select>
                    <button class="btn-fp-action btn-fp-primary" style="padding: 6px 12px;" type="submit">Cari</button>
                    @if ($search !== '' || $filter !== 'terdaftar')
                        <a class="btn-fp-action btn-fp-ghost" style="padding: 6px 12px;" href="{{ route('fingerprint.index') }}">Reset</a>
                    @endif
                </form>
            </div>

            @if ($members->isEmpty())
                <div style="padding: 32px 18px; text-align: center; color: #64748b; font-size: 13.5px;">
                    Tidak ada anggota yang cocok dengan filter tersebut.
                </div>
            @else
                <div class="fp-table-wrap">
                    <table class="fp-table">
                        <thead>
                            <tr>
                                <th>Nama Anggota</th>
                                <th>Nomor Induk</th>
                                <th>Kelas / Peran</th>
                                <th>Posisi Jari</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($members as $member)
                                @php
                                    $latestFp = $member->fingerprints->first();
                                @endphp
                                <tr>
                                    <td>
                                        <strong style="color: #0f172a;">{{ $member->name }}</strong>
                                    </td>
                                    <td>{{ $member->identifier_number ?: '-' }}</td>
                                    <td>{{ $member->class_name ?: $member->roleLabel() }}</td>
                                    <td>
                                        @if ($latestFp)
                                            <span style="font-weight: 600;">{{ $latestFp->finger_position ?: 'Telunjuk Kanan' }}</span>
                                        @else
                                            <span style="color: #94a3b8;">Belum ada template</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($member->fingerprints_count > 0)
                                            <span class="fp-badge status-open">Bisa Absen</span>
                                        @else
                                            <span class="fp-badge status-closed">Belum Terdaftar</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            @if ($member->fingerprints_count > 0)
                                                <form method="POST" action="{{ route('fingerprint.replace', $member) }}"
                                                    data-confirm="Hapus sidik jari lama {{ $member->name }} lalu kirim perintah rekam ulang?">
                                                    @csrf
                                                    <button class="btn-fp-action btn-fp-ghost" style="padding: 5px 9px; font-size: 11.5px;" type="submit">Rekam Ulang</button>
                                                </form>

                                                <form method="POST" action="{{ route('fingerprint.member.destroy', $member) }}"
                                                    data-confirm="Hapus data sidik jari {{ $member->name }}?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn-fp-action" style="padding: 5px 9px; font-size: 11.5px; background: #ffffff; color: #dc2626; border-color: #fecaca;" type="submit">Hapus</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('fingerprint.enroll') }}">
                                                    @csrf
                                                    <input type="hidden" name="user_id" value="{{ $member->id }}">
                                                    <input type="hidden" name="finger_position" value="Telunjuk Kanan">
                                                    <button class="btn-fp-action btn-fp-primary" style="padding: 5px 9px; font-size: 11.5px;" type="submit">Rekam Jari</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="padding: 14px 20px; border-top: 1px solid #f1f5f9;">
                    {{ $members->links('pagination.records') }}
                </div>
            @endif
        </section>

        {{-- Log Presensi Sidik Jari Terbaru --}}
        <section class="fp-panel-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 18px 22px; border-bottom: 1px solid #f1f5f9;">
                <div class="fp-panel-title" style="margin: 0;">
                    <h3 style="margin: 0 0 2px 0;">Log Presensi Sidik Jari Terbaru</h3>
                    <p style="margin: 0;">15 catatan kehadiran terakhir yang masuk dari sensor AS608.</p>
                </div>
            </div>

            @if ($recentLogs->isEmpty())
                <div style="padding: 32px 18px; text-align: center; color: #64748b; font-size: 13.5px;">
                    Belum ada catatan presensi dari sensor sidik jari.
                </div>
            @else
                <div class="fp-table-wrap">
                    <table class="fp-table">
                        <thead>
                            <tr>
                                <th>Nama Jamaah</th>
                                <th>Kelas</th>
                                <th>Waktu Solat</th>
                                <th>Waktu Scan</th>
                                <th>Perangkat</th>
                                <th>Keadaan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentLogs as $log)
                                <tr>
                                    <td>
                                        <strong style="color: #0f172a; display: block;">{{ $log->user?->name ?? 'Anggota terhapus' }}</strong>
                                        <span style="font-size: 11.5px; color: #64748b;">{{ $log->summaryLine() }}</span>
                                    </td>
                                    <td>{{ $log->user?->class_name ?: '-' }}</td>
                                    <td>
                                        <span class="fp-badge status-closed">{{ $log->session?->prayerLabel() ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <strong style="color: #0f172a;">{{ $log->scanned_at?->translatedFormat('d M Y') }}</strong> {{ $log->scanned_at?->format('H:i:s') }}
                                    </td>
                                    <td><code>{{ $log->device_id }}</code></td>
                                    <td>
                                        <span class="fp-badge {{ $log->status === \App\Models\SolatAttendanceLog::StatusTerlambat ? 'status-late' : 'status-open' }}">
                                            {{ $log->statusLabel() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
