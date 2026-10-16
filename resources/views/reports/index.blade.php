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
@endphp

@section('content')
@php
    $secondaryCount = 0;
    if (filled($filters['prayer'])) $secondaryCount++;
    if (filled($filters['role'])) $secondaryCount++;
    if (filled($filters['class_name'])) $secondaryCount++;
    $hasActiveSecondary = $secondaryCount > 0;
    $isFiltered = $hasActiveSecondary || filled($filters['term']);
@endphp

    <div class="search-toolbar card">
        <form method="GET" action="{{ route('reports.index') }}" class="search-toolbar-form">
            <div class="search-toolbar-row">
                <div class="date-range-inline">
                    <span class="date-range-label">Dari</span>
                    <input class="input-field date-range-input" type="date" id="from" name="from"
                        value="{{ $from->toDateString() }}">
                    <span class="date-range-label">sampai</span>
                    <input class="input-field date-range-input" type="date" id="to" name="to"
                        value="{{ $to->toDateString() }}">
                </div>

                <div class="search-input-box">
                    <svg class="search-box-icon" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input class="input-field search-box-input" type="search" id="q" name="q" value="{{ $filters['term'] }}"
                        placeholder="Cari nama atau nomor induk...">
                </div>

                <div class="search-toolbar-buttons">
                    <button class="btn btn-primary btn-small" type="submit">Terapkan</button>
                    @if($isFiltered)
                        <a class="btn btn-text btn-small" href="{{ route('reports.index') }}">Bersihkan</a>
                    @endif
                </div>

                <div class="search-toolbar-aside">
                    <button class="btn btn-outline btn-small" type="button" data-print>Cetak halaman</button>
                    <a class="btn btn-primary btn-small" href="{{ route('reports.export', $exportQuery) }}">Unduh berkas CSV</a>
                </div>
            </div>

            <details class="search-filter-drawer" @if($hasActiveSecondary) open @endif>
                <summary class="search-filter-toggle">
                    <span>Opsi saringan lanjutan</span>
                    @if($hasActiveSecondary)
                        <span class="filter-count-badge">({{ $secondaryCount }} aktif)</span>
                    @endif
                </summary>

                <div class="search-filter-panel">
                    <div class="form-grid">
                        <div class="field">
                            <label class="field-label" for="prayer">Waktu solat</label>
                            <select class="select-field" id="prayer" name="prayer">
                                <option value="">Semua waktu</option>
                                @foreach ($prayers as $prayer)
                                    <option value="{{ $prayer->prayer_name }}" @selected($filters['prayer'] === $prayer->prayer_name)>{{ $prayer->label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label class="field-label" for="role">Peran</label>
                            <select class="select-field" id="role" name="role">
                                <option value="">Semua peran</option>
                                @foreach (\App\Models\User::roles() as $value => $label)
                                    <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label class="field-label" for="class_name">Kelas</label>
                            <select class="select-field" id="class_name" name="class_name">
                                <option value="">Semua kelas</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class }}" @selected($filters['class_name'] === $class)>{{ $class }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="search-filter-actions">
                        <button class="btn btn-primary btn-small" type="submit">Terapkan saringan</button>
                        @if($hasActiveSecondary)
                            <a class="btn btn-text btn-small" href="{{ route('reports.index', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'q' => $filters['term']]) }}">Atur ulang saringan</a>
                        @endif
                    </div>
                </div>
            </details>
        </form>
    </div>

    <div data-live="rekap">
        <section class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Catatan kehadiran</p>
                <p class="stat-value">{{ $summary['logs'] }}</p>
                <p class="stat-note">{{ $summary['present'] }} hadir dan {{ $summary['late'] }} terlambat</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Sesi dalam rentang</p>
                <p class="stat-value">{{ $summary['sessions'] }}</p>
                <p class="stat-note">{{ $summary['closed_sessions'] }} sudah ditutup</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Sesi masih dibuka</p>
                <p class="stat-value">{{ $summary['open_sessions'] }}</p>
                <p class="stat-note">Belum ditutup petugas</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Anggota terdaftar</p>
                <p class="stat-value">{{ $summary['members'] }}</p>
                <p class="stat-note">{{ $summary['with_face'] }} sudah punya data wajah</p>
            </div>
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Rekap per anggota</h2>
                    <p class="section-note">
                        Menampilkan {{ $members->firstItem() ?? 0 }} sampai {{ $members->lastItem() ?? 0 }}
                        dari {{ $members->total() }} anggota.
                    </p>
                </div>
            </div>

            @if ($members->isEmpty())
                <p class="empty-state">Tidak ada anggota yang cocok dengan saringan tersebut.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Nomor induk</th>
                                <th>Peran</th>
                                <th>Kelas</th>
                                <th>Hadir</th>
                                <th>Terlambat</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($members as $member)
                                <tr>
                                    <td class="cell-strong">{{ $member->name }}</td>
                                    <td>{{ $member->identifier_number ?: '-' }}</td>
                                    <td>{{ $member->roleLabel() }}</td>
                                    <td>{{ $member->class_name ?: '-' }}</td>
                                    <td>{{ $member->present_count }}</td>
                                    <td>{{ $member->late_count }}</td>
                                    <td class="cell-strong">{{ $member->log_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $members->links('pagination.records') }}
            @endif
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Rincian tiap sesi</h2>
                    <p class="section-note">Memperlihatkan waktu solat mana yang paling banyak kosong.</p>
                </div>
            </div>

            @if ($breakdown->isEmpty())
                <p class="empty-state">Belum ada sesi pada rentang tanggal ini.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sesi</th>
                                <th>Keadaan</th>
                                <th>Hadir</th>
                                <th>Terlambat</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($breakdown as $session)
                                <tr>
                                    <td class="cell-strong">{{ $session->label() }}</td>
                                    <td><span
                                            class="status {{ \App\Support\Status::session($session) }}">{{ $session->statusLabel() }}</span>
                                    </td>
                                    <td>{{ $session->present_count }}</td>
                                    <td>{{ $session->late_count }}</td>
                                    <td class="cell-strong">{{ $session->log_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
