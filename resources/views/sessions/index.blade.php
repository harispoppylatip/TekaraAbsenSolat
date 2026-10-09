@extends('layouts.app')

@section('title', 'Sesi Absen')
@section('note', 'Buka dan tutup sesi absen setiap waktu solat')

@php($sessionService = app(\App\Services\SolatSessionService::class))

@section('content')
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Sesi tanggal {{ $date->translatedFormat('l, d F Y') }}</h2>
                <p class="section-note">
                    Absen hanya masuk selama sesi dibuka. Petugas bisa membuka {{ $sessionService::EarlyOpenMinutes }}
                    menit sebelum jam mulai sampai jam selesai. Admin bebas memilih tanggal.
                </p>
            </div>

            <div class="section-actions">
                <a class="btn btn-outline btn-small" href="{{ route('attendance.wajah') }}">Halaman absen wajah</a>
                <a class="btn btn-outline btn-small"
                    href="{{ route('reports.index', ['from' => $date->toDateString(), 'to' => $date->toDateString()]) }}">Lihat
                    rekap harian</a>
            </div>
        </div>

        <div class="filter-bar">
            <form method="GET" action="{{ route('sessions.index') }}">
                <div class="field">
                    <label class="field-label" for="date">Tanggal sesi</label>
                    <input class="input-field" type="date" id="date" name="date"
                        value="{{ $date->toDateString() }}" data-auto-submit>
                </div>
            </form>

            <form method="POST" action="{{ route('sessions.generate') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                <div class="field">
                    <label class="field-label" for="generate">Buat sesi dari daftar waktu solat</label>
                    <button class="btn btn-primary" id="generate" type="submit">Buat sesi tanggal ini</button>
                </div>
            </form>
        </div>
    </section>

    <div data-live="sesi-absen">
        <section class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Sesi pada tanggal ini</p>
                <p class="stat-value">{{ $sessions->count() }}</p>
                <p class="stat-note">{{ $prayerOptions->where('is_active', true)->count() }} waktu solat aktif</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Catatan kehadiran</p>
                <p class="stat-value">{{ $sessions->sum('logs_count') }}</p>
                <p class="stat-note">{{ $sessions->sum('late_count') }} di antaranya terlambat</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Sesi sedang dibuka</p>
                <p class="stat-value">{{ $activeSession ? $activeSession->prayerLabel() : 'Tidak ada' }}</p>
                <p class="stat-note">
                    {{ $activeSession ? $activeSession->date?->translatedFormat('d F Y') . ', jam ' . $activeSession->windowLabel() : 'Absen tidak menerima catatan baru' }}
                </p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Murid terdaftar</p>
                <p class="stat-value">{{ $memberTotal }}</p>
                <p class="stat-note">Perkiraan jumlah jamaah yang bisa absen</p>
            </div>
        </section>

        <div class="grid-2">
            <section class="card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Daftar sesi</h2>
                        <p class="section-note">Pilih satu sesi untuk melihat catatan kehadirannya.</p>
                    </div>
                </div>

                @if ($sessions->isEmpty())
                    <p class="empty-state">Belum ada sesi pada tanggal ini. Tekan tombol Buat sesi tanggal ini.</p>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Waktu solat</th>
                                    <th>Jam</th>
                                    <th>Keadaan</th>
                                    <th>Absen</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sessions as $session)
                                    <tr>
                                        <td>
                                            <span class="cell-strong">{{ $session->prayerLabel() }}</span>
                                            @if ($selected && $selected->is($session))
                                                <span class="cell-note">Sedang ditampilkan</span>
                                            @endif
                                        </td>
                                        <td>{{ $session->windowLabel() }}</td>
                                        <td>
                                            <span
                                                class="status {{ \App\Support\Status::session($session) }}">{{ $session->statusLabel() }}</span>
                                        </td>
                                        <td>
                                            {{ $session->logs_count }}
                                            @if ($session->late_count > 0)
                                                <span class="cell-note">{{ $session->late_count }} terlambat</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="cell-actions">
                                                <a class="btn btn-small btn-outline"
                                                    href="{{ route('sessions.index', ['date' => $date->toDateString(), 'session' => $session->id]) }}">Lihat</a>

                                                @if ($session->isOpen())
                                                    <form method="POST" action="{{ route('sessions.close', $session) }}"
                                                        data-confirm="Tutup sesi {{ $session->prayerLabel() }}? Absen tidak akan menerima catatan baru.">
                                                        @csrf
                                                        @method('PUT')
                                                        <button class="btn btn-small btn-secondary"
                                                            type="submit">Tutup</button>
                                                    </form>
                                                @elseif ($sessionService->canOpen($session, auth()->user()))
                                                    <form method="POST" action="{{ route('sessions.open', $session) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <button class="btn btn-small btn-primary"
                                                            type="submit">Buka</button>
                                                    </form>
                                                @else
                                                    <span
                                                        class="cell-note">{{ $sessionService->openGuard($session, auth()->user()) }}</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">
                            {{ $selected ? 'Catatan ' . $selected->prayerLabel() : 'Catatan kehadiran' }}
                        </h2>
                        <p class="section-note">
                            @if ($selected)
                                {{ $selected->date?->translatedFormat('d F Y') }} jam {{ $selected->windowLabel() }}.
                                Dibuka {{ $selected->opener?->name ?? 'belum' }}@if ($selected->closer)
                                    , ditutup {{ $selected->closer->name }}
                                @endif.
                            @else
                                Belum ada sesi yang bisa ditampilkan.
                            @endif
                        </p>
                    </div>
                </div>

                @if ($selected === null)
                    <p class="empty-state">Pilih sesi terlebih dahulu.</p>
                @elseif ($logs->isEmpty())
                    <p class="empty-state">Belum ada catatan kehadiran pada sesi ini.</p>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Kelas</th>
                                    <th>Pukul</th>
                                    <th>Keadaan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    <tr>
                                        <td class="cell-strong">{{ $log->user?->name ?? 'Anggota terhapus' }}</td>
                                        <td>{{ $log->user?->class_name ?: '-' }}</td>
                                        <td>{{ $log->scanned_at?->format('H:i:s') }}</td>
                                        <td><span
                                                class="status {{ \App\Support\Status::log($log->status) }}">{{ $log->statusLabel() }}</span>
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
