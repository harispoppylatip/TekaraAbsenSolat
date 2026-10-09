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
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Rentang dan saringan</h2>
                <p class="section-note">
                    Menampilkan {{ $from->translatedFormat('d F Y') }} sampai {{ $to->translatedFormat('d F Y') }}.
                </p>
            </div>

            <div class="section-actions">
                <button class="btn btn-outline btn-small" type="button" data-print>Cetak halaman</button>
                <a class="btn btn-primary btn-small" href="{{ route('reports.export', $exportQuery) }}">Unduh berkas CSV</a>
            </div>
        </div>

        <form method="GET" action="{{ route('reports.index') }}">
            <div class="filter-bar">
                <div class="field">
                    <label class="field-label" for="from">Dari tanggal</label>
                    <input class="input-field" type="date" id="from" name="from"
                        value="{{ $from->toDateString() }}">
                </div>

                <div class="field">
                    <label class="field-label" for="to">Sampai tanggal</label>
                    <input class="input-field" type="date" id="to" name="to"
                        value="{{ $to->toDateString() }}">
                </div>

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

                <div class="field">
                    <label class="field-label" for="q">Cari nama atau nomor induk</label>
                    <input class="input-field" type="search" id="q" name="q" value="{{ $filters['term'] }}">
                </div>

                <div class="filter-actions">
                    <button class="btn btn-primary" type="submit">Terapkan</button>
                    <a class="btn btn-text" href="{{ route('reports.index') }}">Bersihkan</a>
                </div>
            </div>
        </form>
    </section>

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
