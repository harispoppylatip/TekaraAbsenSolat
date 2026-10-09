@extends('layouts.app')

@section('title', 'Riwayat Absen')
@section('note', 'Catatan kehadiran solat Anda')

@section('content')
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Ringkasan {{ $user->name }}</h2>
                <p class="section-note">
                    {{ $user->identifier_number ? 'Nomor induk ' . $user->identifier_number : $user->roleLabel() }}
                    @if ($user->class_name)
                        , kelas {{ $user->class_name }}
                    @endif.
                    {{ $faceCount > 0
                        ? 'Wajah Anda sudah terdaftar, absen cukup dengan berdiri di depan kamera.'
                        : 'Wajah Anda belum terdaftar di sekolah, hubungi petugas absen.' }}
                </p>
            </div>

            <div class="section-actions">
                <a class="btn btn-outline btn-small" href="{{ route('password.edit') }}">Ganti sandi</a>
            </div>
        </div>

        <form method="GET" action="{{ route('murid.riwayat') }}">
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

                <div class="filter-actions">
                    <button class="btn btn-primary" type="submit">Terapkan</button>
                    <a class="btn btn-text" href="{{ route('murid.riwayat') }}">Tiga puluh hari terakhir</a>
                </div>
            </div>
        </form>
    </section>

    <div data-live="riwayat">
        <section class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Hadir tepat waktu</p>
                <p class="stat-value">{{ $present }}</p>
                <p class="stat-note">Tercatat pada rentang tanggal ini</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Terlambat</p>
                <p class="stat-value">{{ $late }}</p>
                <p class="stat-note">Datang setelah toleransi waktu solat</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Sesi dibuka sekolah</p>
                <p class="stat-value">{{ $openedSessions }}</p>
                <p class="stat-note">Jumlah sesi yang dibuka petugas</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Total catatan Anda</p>
                <p class="stat-value">{{ $present + $late }}</p>
                <p class="stat-note">Seluruh absen dalam rentang ini</p>
            </div>
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Jumlah absen setiap waktu solat</h2>
                    <p class="section-note">Terlambat dihitung terpisah supaya kelihatan waktu solat yang sering terlewat.
                    </p>
                </div>
            </div>

            @if ($perPrayer->isEmpty())
                <p class="empty-state">Belum ada absen pada rentang tanggal ini.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Waktu solat</th>
                                <th>Hadir</th>
                                <th>Terlambat</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($perPrayer as $row)
                                <tr>
                                    <td class="cell-strong">{{ $row['label'] }}</td>
                                    <td>{{ $row['total'] - $row['late'] }}</td>
                                    <td>{{ $row['late'] }}</td>
                                    <td class="cell-strong">{{ $row['total'] }}</td>
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
                    <h2 class="section-title">Riwayat terbaru</h2>
                    <p class="section-note">Lima belas catatan paling baru pada rentang tanggal ini.</p>
                </div>
            </div>

            @if ($logs->isEmpty())
                <p class="empty-state">Belum ada catatan kehadiran. Absen dilakukan di depan kamera sekolah.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Waktu solat</th>
                                <th>Pukul</th>
                                <th>Keadaan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td class="cell-strong">{{ $log->scanned_at?->translatedFormat('l, d F Y') }}</td>
                                    <td>{{ $log->session?->prayerLabel() ?? 'Sesi terhapus' }}</td>
                                    <td>{{ $log->scanned_at?->format('H:i:s') }}</td>
                                    <td><span
                                            class="status {{ \App\Support\Status::log($log->status) }}">{{ $log->statusLabel() }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $logs->links('pagination.records') }}
            @endif
        </section>
    </div>
@endsection
