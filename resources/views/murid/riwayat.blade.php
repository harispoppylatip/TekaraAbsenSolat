@extends('layouts.app')

@section('title', 'Riwayat Absen')
@section('note', 'Catatan kehadiran solat Anda')

@section('content')
    <style>
        /* ========================================================
           Desain Modern Halaman Riwayat Saya Jamaah / Murid
           Sesuai Pedoman Rules.md (No dot indicators, clean UI)
           ======================================================== */
        .my-history-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* 1. Banner Notifikasi Wajib Daftar Wajah */
        .enroll-alert-banner {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 16px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.06);
        }

        .enroll-alert-left {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            flex: 1;
            min-width: 280px;
        }

        .enroll-alert-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #fef3c7;
            color: #d97706;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .enroll-alert-title {
            font-size: 15.5px;
            font-weight: 700;
            color: #92400e;
            margin: 0 0 3px 0;
        }

        .enroll-alert-desc {
            font-size: 13px;
            color: #b45309;
            margin: 0;
            line-height: 1.45;
        }

        /* 2. Kartu Profil & Filter Tanggal */
        .history-profile-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }

        .history-profile-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .history-user-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .history-user-avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 2px solid #dbeafe;
        }

        .history-user-name {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }

        .history-user-meta {
            font-size: 12.5px;
            color: #64748b;
            margin-top: 3px;
        }

        /* Filter Toolbar */
        .history-filter-bar {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .history-date-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .history-date-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }

        .history-date-input {
            padding: 6px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            color: #0f172a;
            background: #f8fafc;
            outline: none;
        }

        .history-date-input:focus {
            border-color: #2563eb;
            background: #ffffff;
        }

        /* 3. 4 Kartu Metrik Ringkasan */
        .history-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
        }

        .history-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 10px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .history-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
        }

        .history-stat-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .history-stat-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }

        .history-stat-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .history-stat-icon.green { background: #f0fdf4; color: #16a34a; }
        .history-stat-icon.amber { background: #fffbeb; color: #d97706; }
        .history-stat-icon.blue { background: #eff6ff; color: #2563eb; }
        .history-stat-icon.indigo { background: #f5f3ff; color: #7c3aed; }

        .history-stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
            margin: 0;
        }

        .history-stat-sub {
            font-size: 12px;
            color: #94a3b8;
            margin: 0;
        }

        /* 4. Tabel Card Modern */
        .history-table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }

        .history-table-head {
            margin-bottom: 16px;
        }

        .history-section-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .history-section-subtitle {
            font-size: 13px;
            color: #64748b;
            margin-top: 3px;
            margin-bottom: 0;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-status.hadir {
            background: #dcfce7;
            color: #166534;
        }

        .badge-status.terlambat {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>

    <div class="my-history-container">

        {{-- 1. Notifikasi Jika Wajah Belum Terdaftar --}}
        @if ($needsEnrollment)
            <aside class="enroll-alert-banner" aria-label="Peringatan Pendaftaran Wajah">
                <div class="enroll-alert-left">
                    <div class="enroll-alert-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                    </div>
                    <div>
                        <h2 class="enroll-alert-title">Wajah Anda Belum Terdaftar untuk Presensi Solat</h2>
                        <p class="enroll-alert-desc">
                            @if ($faceCount > 0)
                                Anda baru memiliki {{ $faceCount }} dari 5 sudut foto wajah. Lengkapi pendaftaran sekarang agar kehadiran solat Anda dapat terdeteksi otomatis oleh kamera sekolah.
                            @else
                                Anda belum mendaftarkan wajah. Daftarkan 5 sudut foto wajah Anda sekarang agar dapat melakukan absensi kehadiran mandiri di depan kamera sekolah.
                            @endif
                        </p>
                    </div>
                </div>

                <a class="btn btn-primary" href="{{ route('enrollment.show') }}" style="background: #d97706; border-color: #b45309; display: inline-flex; align-items: center; gap: 8px; font-weight: 700; white-space: nowrap; padding: 10px 18px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    Daftarkan Wajah Sekarang
                </a>
            </aside>
        @endif

        {{-- 2. Kartu Profil & Filter Rentang Tanggal --}}
        <section class="history-profile-card">
            <div class="history-profile-top">
                <div class="history-user-info">
                    <div class="history-user-avatar">
                        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="history-user-name">{{ $user->name }}</div>
                        <div class="history-user-meta">
                            {{ $user->identifier_number ? 'Nomor Induk: ' . $user->identifier_number : 'Tanpa NIS/NIP' }}
                            {{ $user->class_name ? ' · Kelas ' . $user->class_name : '' }}
                            · {{ $user->roleLabel() }}
                        </div>
                    </div>
                </div>

                <div>
                    @if ($isFaceEnrolled && $faceCount >= 5)
                        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: 12.5px; font-weight: 600; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            Wajah Siap Absen
                        </span>
                    @else
                        <a href="{{ route('enrollment.show') }}" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: 12.5px; font-weight: 600; background: #fffbeb; color: #92400e; border: 1px solid #fde68a; text-decoration: none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            Perlu Daftarkan Wajah
                        </a>
                    @endif
                </div>
            </div>

            {{-- Filter Tanggal Form --}}
            <form method="GET" action="{{ route('murid.riwayat') }}" class="history-filter-bar">
                <div class="history-date-group">
                    <span class="history-date-label">Dari:</span>
                    <input class="history-date-input" type="date" id="from" name="from" value="{{ $from->toDateString() }}">
                    <span class="history-date-label">sampai:</span>
                    <input class="history-date-input" type="date" id="to" name="to" value="{{ $to->toDateString() }}">
                </div>

                <div style="display: flex; gap: 8px; align-items: center;">
                    <button class="btn btn-primary btn-small" type="submit">Terapkan Filter</button>
                    <a class="btn btn-outline btn-small" href="{{ route('murid.riwayat') }}">30 Hari Terakhir</a>
                </div>
            </form>
        </section>

        {{-- 3. Grid Statistik Ringkasan --}}
        <div data-live="riwayat">
            <section class="history-stats-grid" aria-label="Statistik Kehadiran">
                {{-- Hadir Tepat Waktu --}}
                <div class="history-stat-card">
                    <div class="history-stat-head">
                        <span class="history-stat-label">Hadir Tepat Waktu</span>
                        <div class="history-stat-icon green">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div class="history-stat-value" style="color: #166534;">{{ $present }}</div>
                        <div class="history-stat-sub">Sesuai rentang tanggal terpilih</div>
                    </div>
                </div>

                {{-- Terlambat --}}
                <div class="history-stat-card">
                    <div class="history-stat-head">
                        <span class="history-stat-label">Terlambat</span>
                        <div class="history-stat-icon amber">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div class="history-stat-value" style="color: #b45309;">{{ $late }}</div>
                        <div class="history-stat-sub">Melewati batas toleransi waktu</div>
                    </div>
                </div>

                {{-- Sesi Dibuka Saat Ini --}}
                <div class="history-stat-card">
                    <div class="history-stat-head">
                        <span class="history-stat-label">Sesi Solat Saat Ini</span>
                        <div class="history-stat-icon blue">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                                <path d="M2 12h20"></path>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div class="history-stat-value" style="font-size: 19px; color: #1e40af;">
                            {{ $activeSession ? $activeSession->prayerLabel() : 'Belum Ada' }}
                        </div>
                        <div class="history-stat-sub">
                            @if ($activeSession)
                                Sedang dibuka ({{ $activeSession->windowLabel() }})
                            @else
                                Belum ada sesi yang dibuka petugas
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Total Kehadiran --}}
                <div class="history-stat-card">
                    <div class="history-stat-head">
                        <span class="history-stat-label">Total Kehadiran</span>
                        <div class="history-stat-icon indigo">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div class="history-stat-value">{{ $present + $late }}</div>
                        <div class="history-stat-sub">Total presensi dalam rentang</div>
                    </div>
                </div>
            </section>

            {{-- 4. Statistik Per Waktu Solat --}}
            <section class="history-table-card" style="margin-top: 20px;">
                <div class="history-table-head">
                    <h2 class="history-section-title">Jumlah Absen Setiap Waktu Solat</h2>
                    <p class="history-section-subtitle">Menampilkan perbandingan kehadiran tepat waktu dan terlambat pada masing-masing jadwal sholat.</p>
                </div>

                @if ($perPrayer->isEmpty())
                    <p class="empty-state">Belum ada catatan presensi pada rentang tanggal ini.</p>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Waktu Sholat</th>
                                    <th>Hadir Tepat Waktu</th>
                                    <th>Terlambat</th>
                                    <th>Total Hadir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($perPrayer as $row)
                                    <tr>
                                        <td class="cell-strong" style="font-weight: 700; color: #0f172a;">{{ $row['label'] }}</td>
                                        <td>
                                            <span style="color: #166534; font-weight: 600;">{{ $row['total'] - $row['late'] }}</span>
                                        </td>
                                        <td>
                                            @if ($row['late'] > 0)
                                                <span style="color: #dc2626; font-weight: 600;">{{ $row['late'] }}</span>
                                            @else
                                                <span style="color: #94a3b8;">0</span>
                                            @endif
                                        </td>
                                        <td class="cell-strong" style="font-weight: 700;">{{ $row['total'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- 5. Riwayat Catatan Kehadiran Terbaru --}}
            <section class="history-table-card" style="margin-top: 20px;">
                <div class="history-table-head">
                    <h2 class="history-section-title">Riwayat Presensi Terbaru</h2>
                    <p class="history-section-subtitle">Catatan kehadiran terbaru Anda beserta bukti pemindaian kamera.</p>
                </div>

                @if ($logs->isEmpty())
                    <p class="empty-state">Belum ada catatan kehadiran. Lakukan absensi dengan berdiri tegak di depan kamera sekolah.</p>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Hari & Tanggal</th>
                                    <th>Waktu Sholat</th>
                                    <th>Waktu Pindai</th>
                                    <th>Status</th>
                                    <th>Foto Bukti</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    <tr>
                                        <td class="cell-strong" style="font-weight: 600; color: #0f172a;">
                                            {{ $log->scanned_at?->translatedFormat('l, d F Y') }}
                                        </td>
                                        <td>{{ $log->session?->prayerLabel() ?? 'Sesi Terhapus' }}</td>
                                        <td><code style="font-size: 13px;">{{ $log->scanned_at?->format('H:i:s') }}</code></td>
                                        <td>
                                            @if ($log->status === 'hadir')
                                                <span class="badge-status hadir">Hadir</span>
                                            @else
                                                <span class="badge-status terlambat">Terlambat</span>
                                            @endif
                                        </td>
                                        <td>
                                            @include('partials.proof-photo', [
                                                'log' => $log,
                                                'proofFallback' => 'Tanpa Bukti',
                                            ])
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 14px;">
                        {{ $logs->links('pagination.records') }}
                    </div>
                @endif
            </section>
        </div>

    </div>
@endsection
