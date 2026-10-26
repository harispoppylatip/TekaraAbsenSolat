@extends('layouts.app')

@section('title', 'Rekap Kehadiran')
@section('note', 'Rekap absen solat per rentang tanggal')

@php
    $exportQuery = array_filter(
        [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'prayer' => $filters['prayer'],
            'role' => $filters['role'],
            'class_name' => $filters['class_name'],
            'q' => $filters['term'],
        ],
        fn($value) => filled($value),
    );

    $secondaryCount = 0;
    if (filled($filters['prayer'])) $secondaryCount++;
    if (filled($filters['role'])) $secondaryCount++;
    if (filled($filters['class_name'])) $secondaryCount++;
    $hasActiveSecondary = $secondaryCount > 0;
    $isFiltered = $hasActiveSecondary || filled($filters['term']);
@endphp

@section('content')
    <style>
        /* ========================================================
           Desain Modern Halaman Rekap Kehadiran
           Sesuai Pedoman Rules.md (No dot indicators, clean UI)
           ======================================================== */
        .report-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* 1. Filter Toolbar Card */
        .report-toolbar-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
        }

        .report-toolbar-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }

        .report-toolbar-title h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px 0;
        }

        .report-toolbar-title p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .report-export-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-report-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 13px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .btn-report-primary {
            background: #0284c7;
            color: #ffffff;
        }

        .btn-report-primary:hover {
            background: #0369a1;
            color: #ffffff;
        }

        .btn-report-ghost {
            background: #f8fafc;
            color: #334155;
            border-color: #cbd5e1;
        }

        .btn-report-ghost:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* Main Form Controls */
        .report-controls-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }

        .date-range-box {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 5px 12px;
            border-radius: 10px;
        }

        .date-range-box span {
            font-size: 12.5px;
            font-weight: 600;
            color: #475569;
        }

        .date-input-clean {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            border-radius: 6px;
            padding: 5px 8px;
            font-size: 12.5px;
            color: #1e293b;
            outline: none;
        }

        .date-input-clean:focus {
            border-color: #0284c7;
        }

        .search-box-wrap {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .search-box-wrap svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-input-field {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            color: #1e293b;
            background: #ffffff;
            outline: none;
            transition: border-color 0.15s ease;
        }

        .search-input-field:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        /* Filter Drawer */
        .filter-disclosure {
            margin-top: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
        }

        .filter-disclosure summary {
            font-size: 12.5px;
            font-weight: 600;
            color: #0284c7;
            cursor: pointer;
            outline: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-badge {
            background: #e0f2fe;
            color: #0369a1;
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 6px;
        }

        .filter-grid-fields {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }

        @media (max-width: 768px) {
            .filter-grid-fields {
                grid-template-columns: 1fr;
            }
        }

        .filter-field label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }

        .filter-field select {
            width: 100%;
            padding: 7px 10px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            color: #1e293b;
            background: #ffffff;
            outline: none;
        }

        /* 2. Top Metric Stats */
        .report-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        @media (max-width: 992px) {
            .report-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .report-stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .report-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 1px 4px rgba(0, 30, 64, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .report-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 30, 64, 0.06);
        }

        .report-stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .report-stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .report-stat-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .report-stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 4px;
        }

        .report-stat-subtext {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }

        /* 3. Panel Cards & Tables */
        .report-panel-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
            overflow: hidden;
        }

        .report-panel-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .report-panel-title h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2px 0;
        }

        .report-panel-title p {
            font-size: 12.5px;
            color: #64748b;
            margin: 0;
        }

        .report-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .report-table th {
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

        .report-table td {
            padding: 13px 18px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }

        .report-table tr:hover td {
            background: #fafafa;
        }

        .report-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 7px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .report-badge.status-open {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .report-badge.status-closed {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .report-badge.status-late {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .report-empty {
            padding: 36px 18px;
            text-align: center;
            color: #64748b;
            font-size: 13.5px;
        }
    </style>

    <div class="report-container">
        {{-- 1. SEARCH & FILTER TOOLBAR --}}
        <section class="report-toolbar-card">
            <form method="GET" action="{{ route('reports.index') }}">
                <div class="report-toolbar-top">
                    <div class="report-toolbar-title">
                        <h2>Saringan Rekap Presensi</h2>
                        <p>Pilih rentang tanggal dan saring data sesuai kebutuhan pelaporan.</p>
                    </div>

                    <div class="report-export-group">
                        <button class="btn-report-action btn-report-ghost" type="button" data-print>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                <rect x="6" y="14" width="12" height="8"></rect>
                            </svg>
                            Cetak
                        </button>
                        <a class="btn-report-action btn-report-primary" href="{{ route('reports.export', array_merge($exportQuery, ['delimiter' => 'semicolon'])) }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Unduh Excel (CSV)
                        </a>
                        <a class="btn-report-action btn-report-ghost" href="{{ route('reports.export', array_merge($exportQuery, ['delimiter' => 'comma'])) }}">
                            CSV Koma
                        </a>
                    </div>
                </div>

                {{-- Toolbar Controls Row --}}
                <div class="report-controls-row">
                    <div class="date-range-box">
                        <span>Dari:</span>
                        <input class="date-input-clean" type="date" id="from" name="from" value="{{ $from->toDateString() }}">
                        <span>s/d:</span>
                        <input class="date-input-clean" type="date" id="to" name="to" value="{{ $to->toDateString() }}">
                    </div>

                    <div class="search-box-wrap">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input class="search-input-field" type="search" id="q" name="q" value="{{ $filters['term'] }}" placeholder="Cari nama atau nomor induk...">
                    </div>

                    <button class="btn-report-action btn-report-primary" type="submit">
                        Terapkan
                    </button>

                    @if ($isFiltered)
                        <a class="btn-report-action btn-report-ghost" href="{{ route('reports.index') }}">
                            Reset Saringan
                        </a>
                    @endif
                </div>

                {{-- Saringan Lanjutan Drawer --}}
                <details class="filter-disclosure" @if($hasActiveSecondary) open @endif>
                    <summary>
                        <span>Saringan Lanjutan (Waktu Solat, Peran, Kelas)</span>
                        @if($hasActiveSecondary)
                            <span class="filter-badge">{{ $secondaryCount }} Aktif</span>
                        @endif
                    </summary>

                    <div class="filter-grid-fields">
                        <div class="filter-field">
                            <label for="prayer">Waktu Solat</label>
                            <select id="prayer" name="prayer">
                                <option value="">Semua Waktu Solat</option>
                                @foreach ($prayers as $prayer)
                                    <option value="{{ $prayer->prayer_name }}" @selected($filters['prayer'] === $prayer->prayer_name)>
                                        {{ $prayer->label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="filter-field">
                            <label for="role">Peran Anggota</label>
                            <select id="role" name="role">
                                <option value="">Semua Peran</option>
                                @foreach (\App\Models\User::roles() as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['role'] === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="filter-field">
                            <label for="class_name">Kelas</label>
                            <select id="class_name" name="class_name">
                                <option value="">Semua Kelas</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class }}" @selected($filters['class_name'] === $class)>
                                        {{ $class }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </details>
            </form>
        </section>

        {{-- 2. DYNAMIC CONTENT (Live Polling Target) --}}
        <div data-live="rekap" style="display: flex; flex-direction: column; gap: 20px;">
            {{-- Metric Stats --}}
            <section class="report-stats-grid">
                {{-- Stat 1: Total Presensi --}}
                <div class="report-stat-card">
                    <div class="report-stat-top">
                        <span class="report-stat-label">Total Presensi</span>
                        <span class="report-stat-pill">REKAP</span>
                    </div>
                    <div class="report-stat-value">
                        {{ $summary['logs'] }}
                    </div>
                    <div class="report-stat-subtext">
                        <strong style="color: #15803d;">{{ $summary['present'] }}</strong> tepat waktu &bull;
                        <strong style="color: #dc2626;">{{ $summary['late'] }}</strong> terlambat
                    </div>
                </div>

                {{-- Stat 2: Sesi Solat --}}
                <div class="report-stat-card">
                    <div class="report-stat-top">
                        <span class="report-stat-label">Sesi Solat</span>
                        <span class="report-stat-pill">RENTANG</span>
                    </div>
                    <div class="report-stat-value">
                        {{ $summary['sessions'] }}
                    </div>
                    <div class="report-stat-subtext">
                        {{ $summary['closed_sessions'] }} ditutup &bull; {{ $summary['open_sessions'] }} dibuka
                    </div>
                </div>

                {{-- Stat 3: Jamaah Terdaftar --}}
                <div class="report-stat-card">
                    <div class="report-stat-top">
                        <span class="report-stat-label">Jamaah Terdata</span>
                        <span class="report-stat-pill">ANGGOTA</span>
                    </div>
                    <div class="report-stat-value">
                        {{ $summary['members'] }}
                    </div>
                    <div class="report-stat-subtext">
                        {{ $summary['with_face'] }} anggota telah memiliki data wajah
                    </div>
                </div>

                {{-- Stat 4: Rentang Hari --}}
                <div class="report-stat-card">
                    <div class="report-stat-top">
                        <span class="report-stat-label">Rentang Waktu</span>
                        <span class="report-stat-pill">PERIODE</span>
                    </div>
                    <div class="report-stat-value" style="font-size: 20px;">
                        {{ $from->diffInDays($to) + 1 }} Hari
                    </div>
                    <div class="report-stat-subtext">
                        {{ $from->translatedFormat('d M') }} &ndash; {{ $to->translatedFormat('d M Y') }}
                    </div>
                </div>
            </section>

            {{-- 3. REKAP PER ANGGOTA --}}
            <section class="report-panel-card">
                <div class="report-panel-header">
                    <div class="report-panel-title">
                        <h3>Rekap Kehadiran per Anggota</h3>
                        <p>
                            Menampilkan data {{ $members->firstItem() ?? 0 }} sampai {{ $members->lastItem() ?? 0 }}
                            dari {{ $members->total() }} anggota terdaftar.
                        </p>
                    </div>
                </div>

                @if ($members->isEmpty())
                    <div class="report-empty">
                        Tidak ada data anggota yang cocok dengan filter yang dipilih.
                    </div>
                @else
                    <div class="report-table-wrap">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Nama Jamaah</th>
                                    <th>Nomor Induk</th>
                                    <th>Peran</th>
                                    <th>Kelas</th>
                                    <th>Tepat Waktu</th>
                                    <th>Terlambat</th>
                                    <th>Total Hadir</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $member)
                                    <tr>
                                        <td>
                                            <strong style="color: #0f172a;">{{ $member->name }}</strong>
                                        </td>
                                        <td>{{ $member->identifier_number ?: '-' }}</td>
                                        <td>
                                            <span class="report-badge status-closed">{{ $member->roleLabel() }}</span>
                                        </td>
                                        <td>{{ $member->class_name ?: '-' }}</td>
                                        <td>
                                            <strong style="color: #15803d;">{{ $member->present_count }}</strong>
                                        </td>
                                        <td>
                                            @if ($member->late_count > 0)
                                                <strong style="color: #dc2626;">{{ $member->late_count }}</strong>
                                            @else
                                                <span style="color: #94a3b8;">0</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span style="font-size: 14px; font-weight: 800; color: #0284c7;">{{ $member->log_count }}</span>
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

            {{-- 4. RINCIAN PER SESI SOLAT --}}
            <section class="report-panel-card">
                <div class="report-panel-header">
                    <div class="report-panel-title">
                        <h3>Rincian Tiap Sesi Solat</h3>
                        <p>Memperlihatkan perbandingan kehadiran pada masing-masing waktu solat dalam periode ini.</p>
                    </div>
                </div>

                @if ($breakdown->isEmpty())
                    <div class="report-empty">
                        Belum ada sesi solat pada rentang tanggal ini.
                    </div>
                @else
                    <div class="report-table-wrap">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Sesi Solat</th>
                                    <th>Status Sesi</th>
                                    <th>Hadir Tepat Waktu</th>
                                    <th>Terlambat</th>
                                    <th>Total Presensi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($breakdown as $session)
                                    <tr>
                                        <td>
                                            <strong style="color: #0f172a;">{{ $session->label() }}</strong>
                                        </td>
                                        <td>
                                            <span class="report-badge {{ $session->isOpen() ? 'status-open' : 'status-closed' }}">
                                                {{ $session->statusLabel() }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color: #15803d;">{{ $session->present_count }}</strong>
                                        </td>
                                        <td>
                                            @if ($session->late_count > 0)
                                                <strong style="color: #dc2626;">{{ $session->late_count }}</strong>
                                            @else
                                                <span style="color: #94a3b8;">0</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span style="font-size: 14px; font-weight: 800; color: #0284c7;">{{ $session->log_count }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
