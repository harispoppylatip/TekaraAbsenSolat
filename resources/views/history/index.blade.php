@extends('layouts.app')

@section('title', 'History Absen Solat')
@section('note', 'Daftar anggota yang sudah absen solat beserta rincian waktu dan bukti foto wajah / sidik jari')

@section('content')
    @php
        $secondaryCount = 0;
        if (filled($filters['class_name'])) $secondaryCount++;
        if (filled($filters['major'])) $secondaryCount++;
        if (filled($filters['method'])) $secondaryCount++;
        if (filled($filters['date_from'])) $secondaryCount++;
        if (filled($filters['date_to'])) $secondaryCount++;
        if ($filters['sort'] !== 'latest') $secondaryCount++;
        $hasActiveSecondary = $secondaryCount > 0;
        $isFiltered = $hasActiveSecondary || filled($filters['q']);
        $proofService = app(\App\Services\SolatProofService::class);
    @endphp

    <style>
        .history-page-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .history-toolbar-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
        }

        .history-toolbar-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }

        .history-toolbar-title h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px 0;
        }

        .history-toolbar-title p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .history-search-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }

        .history-search-box {
            position: relative;
            flex: 1;
            min-width: 240px;
        }

        .history-search-box svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .history-search-input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            color: #1e293b;
            background: #ffffff;
            outline: none;
        }

        .history-search-input:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        .btn-history-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }

        .btn-history-primary {
            background: #0284c7;
            color: #ffffff;
        }

        .btn-history-primary:hover {
            background: #0369a1;
            color: #ffffff;
        }

        .btn-history-ghost {
            background: #f8fafc;
            color: #334155;
            border-color: #cbd5e1;
        }

        .btn-history-ghost:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .history-filter-box {
            margin-top: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
        }

        .history-filter-box summary {
            font-size: 12.5px;
            font-weight: 600;
            color: #0284c7;
            cursor: pointer;
            outline: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .history-filter-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }

        @media (max-width: 1200px) {
            .history-filter-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .history-filter-grid {
                grid-template-columns: 1fr;
            }
        }

        .history-filter-col label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }

        .history-filter-col select,
        .history-filter-col input {
            width: 100%;
            padding: 7px 10px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            color: #1e293b;
            background: #ffffff;
            outline: none;
        }

        .history-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        @media (max-width: 992px) {
            .history-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .history-stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .history-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px 20px;
            box-shadow: 0 1px 4px rgba(0, 30, 64, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .history-stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .history-stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .history-stat-tag {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .history-stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 4px;
        }

        .history-stat-sub {
            font-size: 12px;
            color: #64748b;
        }

        .history-list-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
            overflow: hidden;
        }

        .history-list-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .history-list-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2px 0;
        }

        .history-list-header p {
            font-size: 12.5px;
            color: #64748b;
            margin: 0;
        }

        .history-members-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 18px 22px;
        }

        .history-member-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .history-member-item[open] {
            border-color: #94a3b8;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }

        .history-member-summary {
            list-style: none;
            padding: 16px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            background: #ffffff;
            user-select: none;
            transition: background 0.15s ease;
        }

        .history-member-summary::-webkit-details-marker {
            display: none;
        }

        .history-member-summary:hover {
            background: #f8fafc;
        }

        .history-member-item[open] .history-member-summary {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .history-member-identity {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 240px;
            flex: 1;
        }

        .history-avatar {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            background: #eff6ff;
            color: #0284c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 15px;
            flex-shrink: 0;
            border: 1px solid #dbeafe;
        }

        .history-member-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            display: block;
            margin-bottom: 2px;
        }

        .history-member-meta {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .history-member-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .history-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .history-chip.total {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .history-chip.latest {
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-lihat-detail {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 9px;
            font-size: 12.5px;
            font-weight: 700;
            color: #0284c7;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            transition: all 0.15s ease;
        }

        .history-member-item:hover .btn-lihat-detail {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
        }

        .btn-lihat-detail svg {
            transition: transform 0.2s ease;
        }

        .history-member-item[open] .btn-lihat-detail svg {
            transform: rotate(180deg);
        }

        .history-detail-body {
            padding: 18px 20px;
            background: #ffffff;
        }

        .history-detail-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }

        .history-detail-top-title {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        .history-detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .history-detail-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-align: left;
            padding: 11px 14px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .history-detail-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }

        .history-detail-table tr:last-child td {
            border-bottom: none;
        }

        .badge-solat-status {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
        }

        .badge-solat-status.hadir {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .badge-solat-status.terlambat {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        /* Bukti Wajah & Sidik Jari */
        .proof-evidence-box {
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .proof-photo-card {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 5px 10px 5px 6px;
            border-radius: 10px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            text-decoration: none;
            color: #166534;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .proof-photo-card:hover {
            background: #dcfce7;
            border-color: #86efac;
        }

        .proof-photo-card img {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 7px;
            border: 1px solid #86efac;
            background: #0f172a;
            flex-shrink: 0;
        }

        .proof-photo-label {
            display: flex;
            flex-direction: column;
            gap: 2px;
            text-align: left;
        }

        .proof-photo-title {
            font-size: 12px;
            font-weight: 700;
            color: #15803d;
        }

        .proof-photo-sub {
            font-size: 11px;
            color: #166534;
        }

        .proof-fingerprint-card {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 7px 12px;
            border-radius: 10px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        .proof-fingerprint-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #dbeafe;
            color: #1d4ed8;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .proof-fingerprint-title {
            font-size: 12px;
            font-weight: 700;
            color: #1e3a8a;
            display: block;
        }

        .proof-fingerprint-sub {
            font-size: 11px;
            color: #3b82f6;
            display: block;
        }

        /* Lightbox Modal */
        .proof-lightbox-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.82);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .proof-lightbox-backdrop.active {
            display: flex;
        }

        .proof-lightbox-box {
            background: #ffffff;
            border-radius: 16px;
            max-width: 720px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
        }

        .proof-lightbox-head {
            padding: 14px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .proof-lightbox-head h4 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .proof-lightbox-body {
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            max-height: 75vh;
        }

        .proof-lightbox-body img {
            max-width: 100%;
            max-height: 75vh;
            object-fit: contain;
        }
    </style>

    <div class="history-page-container">
        {{-- 1. SEARCH & FILTER --}}
        <section class="history-toolbar-card">
            <form id="history-filter-form" method="GET" action="{{ route('history.index') }}">
                <div class="history-toolbar-top">
                    <div class="history-toolbar-title">
                        <h2>History Kehadiran Anggota</h2>
                        <p>Daftar anggota yang sudah absen solat beserta rincian tanggal, jam, waktu solat, dan bukti foto wajah atau sidik jari.</p>
                    </div>
                </div>

                <div class="history-search-row">
                    <div class="history-search-box">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input class="history-search-input" type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Cari nama anggota, nomor induk, kelas, atau jurusan..." autocomplete="off">
                    </div>

                    <button class="btn-history-action btn-history-primary" type="submit">Cari</button>

                    <a id="history-clear-btn" class="btn-history-action btn-history-ghost" href="{{ route('history.index') }}" @if (! $isFiltered) style="display: none;" @endif>
                        Bersihkan
                    </a>
                </div>

                <details class="history-filter-box" open>
                    <summary>
                        <span>Saringan Kelas, Jurusan, Metode Bukti & Tanggal</span>
                        <span id="history-filter-badge" style="background: #e0f2fe; color: #0369a1; font-size: 11px; padding: 2px 7px; border-radius: 6px; @if(! $hasActiveSecondary) display: none; @endif">{{ $secondaryCount }} Aktif</span>
                    </summary>

                    <div class="history-filter-grid">
                        <div class="history-filter-col">
                            <label for="class_name">Kelas</label>
                            <select id="class_name" name="class_name" data-auto-submit>
                                <option value="">Semua Kelas</option>
                                <optgroup label="Tingkat Kelas">
                                    <option value="10" @selected($filters['class_name'] === '10')>Kelas 10 (X)</option>
                                    <option value="11" @selected($filters['class_name'] === '11')>Kelas 11 (XI)</option>
                                    <option value="12" @selected($filters['class_name'] === '12')>Kelas 12 (XII)</option>
                                </optgroup>
                                @if ($classes->isNotEmpty())
                                    <optgroup label="Kelas Spesifik">
                                        @foreach ($classes as $class)
                                            <option value="{{ $class }}" @selected($filters['class_name'] === $class)>{{ $class }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                        </div>

                        <div class="history-filter-col">
                            <label for="major">Jurusan</label>
                            <select id="major" name="major" data-auto-submit>
                                <option value="">Semua Jurusan</option>
                                @foreach ($majors as $major)
                                    <option value="{{ $major }}" @selected($filters['major'] === $major)>{{ $major }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="history-filter-col">
                            <label for="method">Metode / Bukti Absen</label>
                            <select id="method" name="method" data-auto-submit>
                                <option value="">Semua Metode</option>
                                <option value="wajah" @selected($filters['method'] === 'wajah')>Kamera Wajah (Foto)</option>
                                <option value="fingerprint" @selected($filters['method'] === 'fingerprint')>Sidik Jari (Fingerprint)</option>
                            </select>
                        </div>

                        <div class="history-filter-col">
                            <label for="date_from">Dari Tanggal</label>
                            <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] }}" data-auto-submit>
                        </div>

                        <div class="history-filter-col">
                            <label for="date_to">Sampai Tanggal</label>
                            <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}" data-auto-submit>
                        </div>

                        <div class="history-filter-col">
                            <label for="sort">Urutkan Anggota</label>
                            <select id="sort" name="sort" data-auto-submit>
                                <option value="latest" @selected($filters['sort'] === 'latest')>Absen Terbaru</option>
                                <option value="most" @selected($filters['sort'] === 'most')>Kehadiran Terbanyak</option>
                                <option value="name" @selected($filters['sort'] === 'name')>Nama (A-Z)</option>
                            </select>
                        </div>
                    </div>
                </details>
            </form>
        </section>

        {{-- 2. LIVE REGION --}}
        <div id="history-live-region" data-live="history-anggota" style="display: flex; flex-direction: column; gap: 20px;">
            <section class="history-stats-grid">
                <div class="history-stat-card">
                    <div class="history-stat-top">
                        <span class="history-stat-label">Anggota Sudah Absen</span>
                        <span class="history-stat-tag">JAMAAH</span>
                    </div>
                    <div class="history-stat-value">{{ $stats['members_attended'] }}</div>
                    <div class="history-stat-sub">Memiliki riwayat presensi solat</div>
                </div>

                <div class="history-stat-card">
                    <div class="history-stat-top">
                        <span class="history-stat-label">Total Catatan Absen</span>
                        <span class="history-stat-tag">LOG</span>
                    </div>
                    <div class="history-stat-value">{{ $stats['total_logs'] }}</div>
                    <div class="history-stat-sub">Seluruh kehadiran tercatat</div>
                </div>

                <div class="history-stat-card">
                    <div class="history-stat-top">
                        <span class="history-stat-label">Bukti Foto Wajah</span>
                        <span class="history-stat-tag">KAMERA</span>
                    </div>
                    <div class="history-stat-value" style="color: #15803d;">{{ $stats['face_logs'] }}</div>
                    <div class="history-stat-sub">Dilengkapi foto tangkapan wajah</div>
                </div>

                <div class="history-stat-card">
                    <div class="history-stat-top">
                        <span class="history-stat-label">Bukti Sidik Jari</span>
                        <span class="history-stat-tag">FINGERPRINT</span>
                    </div>
                    <div class="history-stat-value" style="color: #1d4ed8;">{{ $stats['fingerprint_logs'] }}</div>
                    <div class="history-stat-sub">Tercatat melalui sensor sidik jari</div>
                </div>
            </section>

            {{-- 3. DAFTAR ANGGOTA YANG SUDAH ABSEN --}}
            <section class="history-list-panel">
                <div class="history-list-header">
                    <div>
                        <h3>Daftar Anggota yang Sudah Absen Solat</h3>
                        <p>
                            Menampilkan {{ $members->firstItem() ?? 0 }} sampai {{ $members->lastItem() ?? 0 }}
                            dari {{ $members->total() }} anggota yang sudah memiliki riwayat absen. Klik <strong>Lihat Detail</strong> untuk melihat tanggal, jam, solat, serta bukti foto wajah / sidik jari (terbaru paling atas).
                        </p>
                    </div>
                </div>

                @if ($members->isEmpty())
                    <div style="padding: 40px 20px; text-align: center; color: #64748b; font-size: 13.5px;">
                        Belum ada anggota yang tercatat melakukan absen solat sesuai kriteria pencarian ini.
                    </div>
                @else
                    <div class="history-members-stack">
                        @foreach ($members as $member)
                            @php
                                $latestLog = $member->attendanceLogs->first();
                            @endphp
                            <details class="history-member-item" data-record="history-member-{{ $member->id }}">
                                <summary class="history-member-summary">
                                    <div class="history-member-identity">
                                        <div class="history-avatar">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <span class="history-member-name">{{ $member->name }}</span>
                                            <div class="history-member-meta">
                                                <span>NISN/NIP: <strong>{{ $member->identifier_number ?: '-' }}</strong></span>
                                                @if ($member->class_name)
                                                    <span>· Kelas: <strong>{{ $member->class_name }}</strong></span>
                                                @endif
                                                @if ($member->major)
                                                    <span>· Jurusan: <strong>{{ $member->major }}</strong></span>
                                                @endif
                                                <span>· {{ $member->roleLabel() }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="history-member-right">
                                        @if ($latestLog)
                                            <span class="history-chip latest" title="Waktu absen paling baru">
                                                Terakhir: {{ $latestLog->scanned_at?->translatedFormat('d M Y, H:i') }}
                                                ({{ $latestLog->session?->prayerLabel() ?? 'Solat' }})
                                            </span>
                                        @endif

                                        <span class="history-chip total">
                                            {{ $member->total_hadir_count }}x Absen
                                            ({{ $member->tepat_waktu_count }} hadir{{ $member->terlambat_count > 0 ? ', ' . $member->terlambat_count . ' terlambat' : '' }})
                                        </span>

                                        <span class="btn-lihat-detail">
                                            <span>Lihat Detail</span>
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="6 9 12 15 18 9"></polyline>
                                            </svg>
                                        </span>
                                    </div>
                                </summary>

                                <div class="history-detail-body">
                                    <div class="history-detail-top">
                                        <span class="history-detail-top-title">
                                            Rincian Riwayat Absen {{ $member->name }} (Urutan Tanggal & Jam Terbaru di Paling Atas)
                                        </span>
                                        <a class="btn-history-action btn-history-ghost" style="padding: 5px 11px; font-size: 12px;" href="{{ route('history.show', $member) }}">
                                            Buka Halaman Detail Penuh
                                        </a>
                                    </div>

                                    <div style="overflow-x: auto;">
                                        <table class="history-detail-table">
                                            <thead>
                                                <tr>
                                                    <th>Tanggal Absen</th>
                                                    <th>Jam Absen</th>
                                                    <th>Waktu Solat</th>
                                                    <th>Status</th>
                                                    <th>Metode & Bukti Absen (Foto Wajah / Sidik Jari)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($member->attendanceLogs as $log)
                                                    @php
                                                        $isFingerprint = $log->isFingerprint();
                                                        $proofUrl = ! $isFingerprint ? $proofService->url($log) : null;
                                                        $prayerLabel = $log->session?->prayerLabel() ?? 'Sholat';
                                                        $dateLabel = $log->scanned_at?->translatedFormat('l, d F Y') ?? '-';
                                                        $timeLabel = $log->scanned_at?->format('H:i:s') ?? '-';
                                                    @endphp
                                                    <tr>
                                                        <td style="font-weight: 700; color: #0f172a; white-space: nowrap;">
                                                            {{ $dateLabel }}
                                                        </td>
                                                        <td style="white-space: nowrap;">
                                                            <code style="font-size: 13px; font-weight: 700; color: #0284c7; background: #f0f9ff; padding: 3px 8px; border-radius: 6px;">
                                                                {{ $timeLabel }}
                                                            </code>
                                                        </td>
                                                        <td style="font-weight: 700; color: #1e293b;">
                                                            {{ $prayerLabel }}
                                                        </td>
                                                        <td>
                                                            <span class="badge-solat-status {{ $log->status === 'terlambat' ? 'terlambat' : 'hadir' }}">
                                                                {{ $log->statusLabel() }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            @if ($isFingerprint)
                                                                <div class="proof-fingerprint-card">
                                                                    <div class="proof-fingerprint-icon">
                                                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                            <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 3"></path>
                                                                            <path d="M14 13.12c0 2.38 0 6.38-1 8.88"></path>
                                                                            <path d="M2 16h.01"></path>
                                                                            <path d="M21.8 16c.2-2 .131-5.354 0-6"></path>
                                                                            <path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2"></path>
                                                                            <path d="M8.65 22c.21-.66.45-1.32.57-2"></path>
                                                                            <path d="M9 6.8a6 6 0 0 1 9 5.2v2"></path>
                                                                        </svg>
                                                                    </div>
                                                                    <div>
                                                                        <span class="proof-fingerprint-title">Bukti Sidik Jari (Fingerprint)</span>
                                                                        <span class="proof-fingerprint-sub">
                                                                            Terverifikasi sensor sidik jari{{ $log->device_id ? ' · Alat: ' . $log->device_id : '' }}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            @elseif ($proofUrl !== null)
                                                                <button type="button"
                                                                    class="proof-photo-card"
                                                                    data-proof-lightbox="{{ $proofUrl }}"
                                                                    data-proof-caption="{{ $member->name }} — {{ $prayerLabel }} ({{ $dateLabel }} pukul {{ $timeLabel }})">
                                                                    <img src="{{ $proofUrl }}" alt="Bukti Foto Wajah {{ $member->name }}" loading="lazy" decoding="async">
                                                                    <div class="proof-photo-label">
                                                                        <span class="proof-photo-title">Bukti Foto Wajah</span>
                                                                        <span class="proof-photo-sub">Klik untuk perbesar foto</span>
                                                                    </div>
                                                                </button>
                                                            @else
                                                                <span style="font-size: 12px; color: #64748b;">
                                                                    Kamera Wajah (Foto tidak tersedia)
                                                                </span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </details>
                        @endforeach
                    </div>

                    <div style="padding: 14px 22px; border-top: 1px solid #f1f5f9;">
                        {{ $members->links('pagination.records') }}
                    </div>
                @endif
            </section>
        </div>
    </div>

    {{-- Modal Lightbox Bukti Foto Wajah --}}
    <div id="proof-lightbox-modal" class="proof-lightbox-backdrop" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="proof-lightbox-box">
            <div class="proof-lightbox-head">
                <h4 id="proof-lightbox-title">Bukti Foto Absen Wajah</h4>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <a id="proof-lightbox-open-tab" class="btn-history-action btn-history-ghost" style="padding: 5px 10px; font-size: 12px;" href="#" target="_blank" rel="noopener">
                        Buka di Tab Baru
                    </a>
                    <button type="button" id="proof-lightbox-close" class="btn-history-action btn-history-primary" style="padding: 5px 12px; font-size: 12px;">
                        Tutup
                    </button>
                </div>
            </div>
            <div class="proof-lightbox-body">
                <img id="proof-lightbox-img" src="" alt="Bukti Foto Absen">
            </div>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('history-filter-form');
            const liveRegion = document.getElementById('history-live-region');
            const searchInput = document.getElementById('q');
            const clearBtn = document.getElementById('history-clear-btn');
            const filterBadge = document.getElementById('history-filter-badge');

            const modal = document.getElementById('proof-lightbox-modal');
            const modalImg = document.getElementById('proof-lightbox-img');
            const modalTitle = document.getElementById('proof-lightbox-title');
            const modalOpenTab = document.getElementById('proof-lightbox-open-tab');
            const modalClose = document.getElementById('proof-lightbox-close');

            document.addEventListener('click', function (e) {
                const trigger = e.target.closest('[data-proof-lightbox]');
                if (trigger && modal && modalImg) {
                    e.preventDefault();
                    const url = trigger.getAttribute('data-proof-lightbox');
                    const caption = trigger.getAttribute('data-proof-caption') || 'Bukti Foto Absen Wajah';
                    modalImg.src = url;
                    if (modalTitle) modalTitle.textContent = caption;
                    if (modalOpenTab) modalOpenTab.href = url;
                    modal.classList.add('active');
                    modal.setAttribute('aria-hidden', 'false');
                    return;
                }

                if (modal && modal.classList.contains('active')) {
                    if (e.target === modal || e.target === modalClose) {
                        modal.classList.remove('active');
                        modal.setAttribute('aria-hidden', 'true');
                        modalImg.src = '';
                    }
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
                    modal.classList.remove('active');
                    modal.setAttribute('aria-hidden', 'true');
                    modalImg.src = '';
                }
            });

            if (!form || !liveRegion) return;

            let debounceTimer = null;
            let activeController = null;

            function updateIndicators() {
                const className = (form.querySelector('#class_name')?.value || '').trim();
                const major = (form.querySelector('#major')?.value || '').trim();
                const method = (form.querySelector('#method')?.value || '').trim();
                const dateFrom = (form.querySelector('#date_from')?.value || '').trim();
                const dateTo = (form.querySelector('#date_to')?.value || '').trim();
                const sort = (form.querySelector('#sort')?.value || 'latest').trim();
                const q = (searchInput?.value || '').trim();

                let count = 0;
                if (className !== '') count++;
                if (major !== '') count++;
                if (method !== '') count++;
                if (dateFrom !== '') count++;
                if (dateTo !== '') count++;
                if (sort !== 'latest') count++;

                if (filterBadge) {
                    if (count > 0) {
                        filterBadge.textContent = count + ' Aktif';
                        filterBadge.style.display = 'inline-block';
                    } else {
                        filterBadge.style.display = 'none';
                    }
                }

                if (clearBtn) {
                    clearBtn.style.display = (count > 0 || q !== '') ? 'inline-flex' : 'none';
                }
            }

            async function applyFiltersAjax() {
                updateIndicators();

                const params = new URLSearchParams(new FormData(form));
                const targetUrl = form.action + '?' + params.toString();

                window.history.replaceState({}, '', targetUrl);

                if (activeController) {
                    activeController.abort();
                }
                activeController = new AbortController();

                liveRegion.style.opacity = '0.6';
                liveRegion.style.transition = 'opacity 0.15s ease';

                try {
                    const response = await fetch(targetUrl, {
                        headers: {
                            'X-Live-Refresh': '1',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        signal: activeController.signal
                    });

                    if (!response.ok) {
                        form.submit();
                        return;
                    }

                    const html = await response.text();
                    const parsed = new DOMParser().parseFromString(html, 'text/html');
                    const incoming = parsed.querySelector('[data-live="history-anggota"]');

                    if (incoming) {
                        liveRegion.innerHTML = incoming.innerHTML;
                    }
                } catch (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    form.submit();
                } finally {
                    liveRegion.style.opacity = '1';
                }
            }

            form.querySelectorAll('[data-auto-submit]').forEach(function (el) {
                el.addEventListener('change', function (e) {
                    e.stopImmediatePropagation();
                    applyFiltersAjax();
                }, true);
            });

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(applyFiltersAjax, 350);
                });
            }
        })();
    </script>
@endsection
