@extends('layouts.app')

@section('title', 'Anggota')
@section('note', 'Daftar murid, guru, dan petugas absen')

@section('content')
    @php
        $secondaryCount = 0;
        if (filled($filters['role'])) $secondaryCount++;
        if (filled($filters['class_name'])) $secondaryCount++;
        if (filled($filters['major'] ?? '')) $secondaryCount++;
        if (filled($filters['face'])) $secondaryCount++;
        if ($filters['sort'] !== 'name') $secondaryCount++;
        $hasActiveSecondary = $secondaryCount > 0;
        $isFiltered = $hasActiveSecondary || filled($filters['q']);
    @endphp

    <style>
        /* ========================================================
           Desain Modern Halaman Data Anggota
           Sesuai Pedoman Rules.md (No dot indicators, clean UI)
           ======================================================== */
        .members-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* 1. Toolbar Card */
        .members-toolbar-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
        }

        .members-toolbar-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }

        .members-toolbar-title h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 3px 0;
        }

        .members-toolbar-title p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .members-top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-member-action {
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

        .btn-member-primary {
            background: #0284c7;
            color: #ffffff;
        }

        .btn-member-primary:hover {
            background: #0369a1;
            color: #ffffff;
        }

        .btn-member-ghost {
            background: #f8fafc;
            color: #334155;
            border-color: #cbd5e1;
        }

        .btn-member-ghost:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* Search Controls Row */
        .members-search-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }

        .search-field-box {
            position: relative;
            flex: 1;
            min-width: 240px;
        }

        .search-field-box svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-field-input {
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

        .search-field-input:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        /* Filter Disclosure */
        .members-filter-box {
            margin-top: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
        }

        .members-filter-box summary {
            font-size: 12.5px;
            font-weight: 600;
            color: #0284c7;
            cursor: pointer;
            outline: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .members-filter-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }

        @media (max-width: 1100px) {
            .members-filter-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .members-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 500px) {
            .members-filter-grid {
                grid-template-columns: 1fr;
            }
        }

        .members-filter-col label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }

        .members-filter-col select {
            width: 100%;
            padding: 7px 10px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            color: #1e293b;
            background: #ffffff;
            outline: none;
        }

        .members-filter-col select:focus {
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
        }

        /* 2. Top Metric Stats */
        .members-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        @media (max-width: 992px) {
            .members-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 576px) {
            .members-stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .members-stat-card {
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

        .members-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 30, 64, 0.06);
        }

        .members-stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .members-stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .members-stat-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .members-stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 4px;
        }

        .members-stat-subtext {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }

        /* 3. Panel & Table */
        .members-panel-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
            overflow: hidden;
        }

        .members-panel-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .members-panel-title h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2px 0;
        }

        .members-panel-title p {
            font-size: 12.5px;
            color: #64748b;
            margin: 0;
        }

        .members-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .members-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .members-table th {
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

        .members-table td {
            padding: 13px 18px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }

        .members-table tr:hover td {
            background: #fafafa;
        }

        .member-avatar-chip {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #0284c7;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }

        .member-user-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .member-name {
            font-weight: 700;
            color: #0f172a;
            display: block;
        }

        .member-meta {
            font-size: 11.5px;
            color: #64748b;
            display: block;
        }

        .member-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 7px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
        }

        .member-badge.status-open {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .member-badge.status-closed {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .member-badge.status-wait {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .member-actions-row {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-table-action {
            padding: 5px 10px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-table-edit {
            background: #f8fafc;
            color: #334155;
            border-color: #cbd5e1;
        }

        .btn-table-edit:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .btn-table-reset {
            background: #f8fafc;
            color: #b45309;
            border-color: #fde68a;
        }

        .btn-table-reset:hover {
            background: #fffbeb;
            color: #92400e;
        }

        .btn-table-del {
            background: #ffffff;
            color: #dc2626;
            border-color: #fecaca;
        }

        .btn-table-del:hover {
            background: #fef2f2;
            color: #b91c1c;
        }

        .members-empty {
            padding: 36px 18px;
            text-align: center;
            color: #64748b;
            font-size: 13.5px;
        }
    </style>

    <div class="members-container">
        {{-- 1. SEARCH & FILTER TOOLBAR --}}
        <section class="members-toolbar-card">
            <form id="members-filter-form" method="GET" action="{{ route('members.index') }}">
                <div class="members-toolbar-top">
                    <div class="members-toolbar-title">
                        <h2>Kelola Data Anggota</h2>
                        <p>Daftar murid, guru, dan petugas presensi SMK Istiqomah Muhammadiyah 4.</p>
                    </div>

                    <div class="members-top-actions">
                        <a id="members-export-btn" class="btn-member-action btn-member-ghost" href="{{ route('members.export', request()->query()) }}" title="Ekspor seluruh data anggota, foto wajah, embedding AI, dan sidik jari (.zip)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Ekspor Data
                        </a>
                        <a class="btn-member-action btn-member-ghost" href="{{ route('members.import') }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            Impor Data
                        </a>
                        <a class="btn-member-action btn-member-primary" href="{{ route('members.create') }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Tambah Anggota
                        </a>
                    </div>
                </div>

                {{-- Search Controls Row --}}
                <div class="members-search-row">
                    <div class="search-field-box">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input class="search-field-input" type="search" id="q" name="q" value="{{ $filters['q'] }}" placeholder="Cari nama, nomor induk, kelas, jurusan, atau email..." autocomplete="off">
                    </div>

                    <button class="btn-member-action btn-member-primary" type="submit">
                        Cari
                    </button>

                    <a id="members-clear-btn" class="btn-member-action btn-member-ghost" href="{{ route('members.index') }}" @if (! $isFiltered) style="display: none;" @endif>
                        Bersihkan
                    </a>
                </div>

                {{-- Saringan Lanjutan --}}
                <details class="members-filter-box" open>
                    <summary>
                        <span>Opsi Saringan & Pengurutan</span>
                        <span id="members-filter-badge" class="filter-badge" style="background: #e0f2fe; color: #0369a1; font-size: 11px; padding: 2px 7px; border-radius: 6px; @if(! $hasActiveSecondary) display: none; @endif">{{ $secondaryCount }} Aktif</span>
                    </summary>

                    <div class="members-filter-grid">
                        <div class="members-filter-col">
                            <label for="role">Peran</label>
                            <select id="role" name="role" data-auto-submit>
                                <option value="">Semua Peran</option>
                                @foreach (\App\Models\User::roles() as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="members-filter-col">
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

                        <div class="members-filter-col">
                            <label for="major">Jurusan</label>
                            <select id="major" name="major" data-auto-submit>
                                <option value="">Semua Jurusan</option>
                                @foreach ($majors as $major)
                                    <option value="{{ $major }}" @selected(($filters['major'] ?? '') === $major)>{{ $major }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="members-filter-col">
                            <label for="face">Data Wajah</label>
                            <select id="face" name="face" data-auto-submit>
                                <option value="">Semua Status Wajah</option>
                                <option value="ada" @selected($filters['face'] === 'ada')>Sudah Terdaftar</option>
                                <option value="kosong" @selected($filters['face'] === 'kosong')>Belum Terdaftar</option>
                                <option value="bebas" @selected($filters['face'] === 'bebas')>Tidak Wajib Daftar</option>
                            </select>
                        </div>

                        <div class="members-filter-col">
                            <label for="sort">Urutkan</label>
                            <select id="sort" name="sort" data-auto-submit>
                                <option value="name" @selected($filters['sort'] === 'name')>Nama (A-Z)</option>
                                <option value="identifier" @selected($filters['sort'] === 'identifier')>Nomor Induk</option>
                                <option value="role" @selected($filters['sort'] === 'role')>Peran</option>
                                <option value="recent" @selected($filters['sort'] === 'recent')>Login Terakhir</option>
                            </select>
                        </div>
                    </div>
                </details>
            </form>
        </section>

        {{-- 2. DYNAMIC CONTENT (Live Polling Target) --}}
        <div id="members-live-region" data-live="anggota" style="display: flex; flex-direction: column; gap: 20px;">
            {{-- Metric Stats --}}
            <section class="members-stats-grid">
                {{-- Stat 1: Total Anggota --}}
                <div class="members-stat-card">
                    <div class="members-stat-top">
                        <span class="members-stat-label">Total Anggota</span>
                        <span class="members-stat-pill">SEMUA</span>
                    </div>
                    <div class="members-stat-value">
                        {{ $stats['total'] }}
                    </div>
                    <div class="members-stat-subtext">
                        Murid, guru, dan staf petugas
                    </div>
                </div>

                {{-- Stat 2: Murid --}}
                <div class="members-stat-card">
                    <div class="members-stat-top">
                        <span class="members-stat-label">Murid / Jamaah</span>
                        <span class="members-stat-pill">SISWA</span>
                    </div>
                    <div class="members-stat-value">
                        {{ $stats['murid'] }}
                    </div>
                    <div class="members-stat-subtext">
                        Jamaah aktif terdaftar di kelas
                    </div>
                </div>

                {{-- Stat 3: Petugas & Guru --}}
                <div class="members-stat-card">
                    <div class="members-stat-top">
                        <span class="members-stat-label">Guru & Petugas</span>
                        <span class="members-stat-pill">STAFF</span>
                    </div>
                    <div class="members-stat-value">
                        {{ $stats['petugas'] }}
                    </div>
                    <div class="members-stat-subtext">
                        Dapat membuka & mengelola sesi
                    </div>
                </div>

                {{-- Stat 4: Siap Presensi Kamera --}}
                <div class="members-stat-card">
                    <div class="members-stat-top">
                        <span class="members-stat-label">Wajah Terdaftar</span>
                        <span class="members-stat-pill">SIAP ABSEN</span>
                    </div>
                    <div class="members-stat-value">
                        {{ $stats['berwajah'] }}
                    </div>
                    <div class="members-stat-subtext">
                        Siap memindai di kamera absen
                    </div>
                </div>
            </section>

            {{-- 3. DAFTAR ANGGOTA PANEL --}}
            <section class="members-panel-card">
                <div class="members-panel-header">
                    <div class="members-panel-title">
                        <h3>Daftar Anggota</h3>
                        <p>
                            Menampilkan {{ $members->firstItem() ?? 0 }} sampai {{ $members->lastItem() ?? 0 }}
                            dari {{ $members->total() }} anggota terdaftar.
                        </p>
                    </div>
                </div>

                @if ($members->isEmpty())
                    <div class="members-empty">
                        Tidak ada data anggota yang cocok dengan kriteria pencarian tersebut.
                    </div>
                @else
                    <div class="members-table-wrap">
                        <table class="members-table">
                            <thead>
                                <tr>
                                    <th>Anggota</th>
                                    <th>Nomor Induk</th>
                                    <th>Peran</th>
                                    <th>Kelas</th>
                                    <th>Jurusan</th>
                                    <th>Status Wajah</th>
                                    <th>Total Hadir</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $member)
                                    <tr>
                                        <td>
                                            <div class="member-user-row">
                                                <div class="member-avatar-chip">
                                                    {{ strtoupper(substr($member->name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <span class="member-name">{{ $member->name }}</span>
                                                    <span class="member-meta">{{ $member->email ?: 'Tanpa email' }}</span>
                                                    @if ($member->can_scan_face)
                                                        <span class="member-meta" style="color: #0284c7; font-weight: 600;">Dapat memindai wajah</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $member->identifier_number ?: '-' }}</td>
                                        <td>
                                            <span class="member-badge status-closed">{{ $member->roleLabel() }}</span>
                                        </td>
                                        <td>{{ $member->class_name ?: '-' }}</td>
                                        <td>{{ $member->major ?: '-' }}</td>
                                        <td>
                                            @if ($member->face_embeddings_count > 0)
                                                <span class="member-badge status-open">
                                                    Sudah Terdaftar
                                                </span>
                                            @elseif ($member->isExemptFromEnrollment())
                                                <span class="member-badge status-closed">
                                                    Tidak Wajib Daftar
                                                </span>
                                            @else
                                                <span class="member-badge status-wait">
                                                    Belum Terdaftar
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <span style="font-weight: 700; color: #0284c7;">{{ $member->attendance_logs_count }}</span>
                                        </td>
                                        <td>
                                            <div class="member-actions-row">
                                                <a class="btn-table-action btn-table-edit" href="{{ route('members.edit', $member) }}">
                                                    Ubah
                                                </a>

                                                <form method="POST" action="{{ route('members.password', $member) }}"
                                                    data-confirm="Kembalikan kata sandi {{ $member->name }} ke sandi awal?">
                                                    @csrf
                                                    <button class="btn-table-action btn-table-reset" type="submit">
                                                        Reset Sandi
                                                    </button>
                                                </form>

                                                @if ($member->id !== auth()->id())
                                                    <form method="POST" action="{{ route('members.destroy', $member) }}"
                                                        data-confirm="Hapus anggota {{ $member->name }} beserta data wajah dan riwayat absennya?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn-table-action btn-table-del" type="submit">
                                                            Hapus
                                                        </button>
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
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('members-filter-form');
            const liveRegion = document.getElementById('members-live-region');
            const searchInput = document.getElementById('q');
            const clearBtn = document.getElementById('members-clear-btn');
            const filterBadge = document.getElementById('members-filter-badge');
            const exportBtn = document.getElementById('members-export-btn');
            const exportBaseUrl = @json(route('members.export'));

            if (!form || !liveRegion) return;

            let debounceTimer = null;
            let activeController = null;

            function updateFilterIndicators() {
                const role = (form.querySelector('#role')?.value || '').trim();
                const className = (form.querySelector('#class_name')?.value || '').trim();
                const major = (form.querySelector('#major')?.value || '').trim();
                const face = (form.querySelector('#face')?.value || '').trim();
                const sort = (form.querySelector('#sort')?.value || 'name').trim();
                const q = (searchInput?.value || '').trim();

                let count = 0;
                if (role !== '') count++;
                if (className !== '') count++;
                if (major !== '') count++;
                if (face !== '') count++;
                if (sort !== 'name') count++;

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

                if (exportBtn) {
                    const params = new URLSearchParams(new FormData(form));
                    const qs = params.toString();
                    exportBtn.href = qs ? (exportBaseUrl + '?' + qs) : exportBaseUrl;
                }
            }

            async function applyFiltersAjax() {
                updateFilterIndicators();

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
                    const incoming = parsed.querySelector('[data-live="anggota"]');

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

            // Tangani perubahan dropdown filter secara instan tanpa reload halaman penuh
            form.querySelectorAll('select[data-auto-submit]').forEach(function (select) {
                select.addEventListener('change', function (e) {
                    e.stopImmediatePropagation();
                    applyFiltersAjax();
                }, true);
            });

            // Tangani ketikan di kotak pencarian secara otomatis (debounce 350ms)
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(applyFiltersAjax, 350);
                });
            }
        })();
    </script>
@endsection
