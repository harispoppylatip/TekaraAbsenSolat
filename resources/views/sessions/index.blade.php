@extends('layouts.app')

@section('title', 'Sesi Absen')
@section('note', 'Sesi absen dibuka dan ditutup sendiri setiap waktu solat')

@php($sessionService = app(\App\Services\SolatSessionService::class))
@php($isAdmin = auth()->user()?->isAdmin() ?? false)

@section('content')
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Sesi tanggal {{ $date->translatedFormat('l, d F Y') }}</h2>
                <p class="section-note">
                    Absen hanya masuk selama sesi dibuka. Sistem menyiapkan sesi untuk hari ini dan besok sesudah
                    tengah malam, membuka sesi {{ $sessionService::earlyOpenMinutes() }} menit sebelum jam mulai, dan
                    menutupnya di akhir jendela waktu solat, asalkan penjadwal aplikasi hidup. Tombol buka dan tutup
                    tetap tersedia untuk keadaan yang perlu dibuka lebih dulu. Admin bebas memilih tanggal.
                </p>
            </div>

            <div class="section-actions">
                <a class="btn btn-outline btn-small" href="{{ route('attendance.wajah') }}">Halaman absen wajah</a>
                <a class="btn btn-outline btn-small"
                    href="{{ route('reports.index', ['from' => $date->toDateString(), 'to' => $date->toDateString()]) }}">Lihat
                    rekap harian</a>
            </div>
        </div>

        <div class="search-toolbar-row" style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--outline-variant);">
            <form method="GET" action="{{ route('sessions.index') }}" class="date-range-inline">
                <span class="date-range-label">Tanggal sesi:</span>
                <input class="input-field date-range-input" type="date" id="date" name="date"
                    value="{{ $date->toDateString() }}" data-auto-submit>
            </form>

            <form method="POST" action="{{ route('sessions.generate') }}" style="display: inline-flex; align-items: center;">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <button class="btn btn-primary btn-small" id="generate" type="submit">Buat sesi tanggal ini</button>
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

        @if ($isAdmin || $testSession !== null)
            <section class="card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Mode uji coba absen</h2>
                        <p class="section-note">
                            Uji kamera absen wajah kapan saja tanpa membuka sesi waktu solat (jeda {{ $testCooldownMinutes }} menit per orang).
                        </p>
                        <details class="fold-note" data-record="info-mode-uji-coba-sesi">
                            <summary>
                                <span class="fold-note-open">Lihat rincian</span>
                                <span class="fold-note-close">Tutup rincian</span>
                            </summary>
                            <div class="fold-note-body">
                                <p>
                                    Mode uji coba membuka satu sesi khusus untuk mencoba kamera absen wajah tanpa membuka sesi
                                    waktu solat yang sungguhan. Percobaan yang masuk tidak dihitung pada rekap, riwayat, maupun
                                    ringkasan, dan orang yang sama baru boleh dicoba lagi setelah
                                    {{ $testCooldownMinutes }} menit. Sengaja disediakan supaya absen boleh diuji kapan saja.
                                </p>
                            </div>
                        </details>
                    </div>

                    <div class="section-actions">
                        <a class="btn btn-outline btn-small" href="{{ route('attendance.wajah') }}">Halaman absen
                            wajah</a>
                    </div>
                </div>

                @if ($testSession === null)
                    <div class="alert alert-info">
                        <strong>Mode uji coba mati</strong>
                        Absen hanya masuk kalau ada sesi waktu solat yang sedang dibuka atau mode uji coba dinyalakan.
                    </div>

                    @if ($isAdmin)
                        <div class="form-actions">
                            <form method="POST" action="{{ route('sessions.test.start') }}">
                                @csrf
                                <button class="btn btn-primary" type="submit">Nyalakan mode uji coba</button>
                            </form>
                            @if ($activeSession !== null)
                                <form method="POST" action="{{ route('sessions.close', $activeSession) }}">
                                    @csrf
                                    @method('PUT')
                                    <button class="btn btn-secondary" type="submit">Tutup sesi {{ $activeSession->prayerLabel() }}</button>
                                </form>
                                <span class="field-hint">Sesi {{ $activeSession->label() }} sedang dibuka. Tutup dulu sesi tersebut sebelum menyalakan mode uji coba.</span>
                            @else
                                <span class="field-hint">Tidak ada sesi waktu solat yang sedang dibuka, jadi mode uji
                                    coba boleh dinyalakan sekarang.</span>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="alert alert-success">
                        <strong>Mode uji coba menyala</strong>
                        Sejak pukul {{ $testSession->opened_at?->format('H:i') }}
                        tanggal {{ $testSession->date?->translatedFormat('d F Y') }}. Petugas dan anggota yang ditugaskan
                        memindai wajah boleh ikut mencoba, tanpa perlu menunggu waktu solat.
                    </div>

                    @if ($isAdmin)
                        <div class="form-actions">
                            <form method="POST" action="{{ route('sessions.test.stop') }}">
                                @csrf
                                @method('PUT')
                                <button class="btn btn-secondary" type="submit">Matikan mode uji coba</button>
                            </form>

                            <form method="POST" action="{{ route('sessions.test.destroy') }}"
                                data-confirm="Hapus sesi uji coba beserta {{ $testCount }} catatan percobaan dan foto buktinya?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger" type="submit">Hapus sesi uji coba</button>
                            </form>

                            <span class="field-hint">{{ $testCount }} catatan percobaan tersimpan.</span>
                        </div>
                    @else
                        <p class="field-hint">Hanya admin yang bisa mematikan atau menghapus sesi uji coba.</p>
                    @endif

                    @if ($testLogs->isEmpty())
                        <p class="empty-state">Belum ada percobaan yang masuk. Buka halaman absen wajah lalu pindai satu
                            wajah untuk mencobanya.</p>
                    @else
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Kelas</th>
                                        <th>Pukul</th>
                                        <th>Keadaan</th>
                                        <th>Bukti</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($testLogs as $log)
                                        <tr>
                                            <td>
                                                <span class="cell-strong">{{ $log->user?->name ?? 'Anggota terhapus' }}</span>
                                                @if ($log->isFingerprint())
                                                    <span class="cell-note">{{ $log->summaryLine() }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $log->user?->class_name ?: '-' }}</td>
                                            <td>{{ $log->scanned_at?->format('H:i:s') }}</td>
                                            <td><span
                                                    class="status {{ \App\Support\Status::log($log->status) }}">{{ $log->statusLabel() }}</span>
                                            </td>
                                            <td>@include('partials.proof-photo', [
                                                'log' => $log,
                                                'proofFallback' => 'Tanpa bukti',
                                            ])</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($testCount > $testLogs->count())
                            <p class="cell-note">Menampilkan {{ $testLogs->count() }} percobaan terbaru dari
                                {{ $testCount }}.</p>
                        @endif
                    @endif
                @endif
            </section>
        @endif

        <div class="grid-2">
            <section class="card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Daftar sesi</h2>
                        <p class="section-note">Pilih satu sesi untuk melihat catatan kehadirannya.</p>
                    </div>

                    @if ($isAdmin)
                        <div class="section-actions">
                            <form id="hapus-sesi-terpilih" method="POST" action="{{ route('sessions.destroy-selected') }}"
                                data-confirm="Hapus sesi yang dicentang? Catatan kehadiran dan foto buktinya ikut terhapus.">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                                <button class="btn btn-small btn-danger" type="submit">Hapus yang dicentang</button>
                            </form>

                            <form method="POST" action="{{ route('sessions.destroy-day') }}"
                                data-confirm="Hapus semua sesi tanggal {{ $date->translatedFormat('d F Y') }} beserta catatan kehadirannya?">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                                <button class="btn btn-small btn-danger" type="submit">Hapus sesi tanggal ini</button>
                            </form>
                        </div>
                    @endif
                </div>

                @if ($sessions->isEmpty())
                    <p class="empty-state">Belum ada sesi pada tanggal ini. Sesi hari ini disiapkan sendiri sesudah
                        tengah malam. Tekan tombol Buat sesi tanggal ini bila ingin menyiapkannya sekarang.</p>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    @if ($isAdmin)
                                        <th>Pilih</th>
                                    @endif
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
                                        @if ($isAdmin)
                                            <td class="check-cell">
                                                <input type="checkbox" name="sessions[]" value="{{ $session->id }}"
                                                    form="hapus-sesi-terpilih"
                                                    aria-label="Pilih sesi {{ $session->prayerLabel() }}">
                                            </td>
                                        @endif
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

                                                @if ($isAdmin)
                                                    <form id="hapus-sesi-{{ $session->id }}" method="POST"
                                                        action="{{ route('sessions.destroy', $session) }}"
                                                        data-confirm="Hapus sesi {{ $session->prayerLabel() }} tanggal {{ $date->translatedFormat('d F Y') }} beserta {{ $session->logs_count }} catatan kehadirannya?">
                                                        @csrf
                                                        @method('DELETE')
                                                    </form>
                                                    <button class="btn btn-small btn-danger" type="submit"
                                                        form="hapus-sesi-{{ $session->id }}">Hapus</button>
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
                                @if ($selected->opened_at === null)
                                    Sesi belum dibuka.
                                @else
                                    Dibuka {{ $selected->opener?->name ?? 'secara otomatis' }}.
                                @endif
                                @if ($selected->closed_at !== null)
                                    Ditutup {{ $selected->closer?->name ?? 'secara otomatis' }}.
                                @endif
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
                                    <th>Bukti</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    <tr>
                                        <td>
                                            <span class="cell-strong">{{ $log->user?->name ?? 'Anggota terhapus' }}</span>
                                            @if ($log->isFingerprint())
                                                <span class="cell-note">{{ $log->summaryLine() }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $log->user?->class_name ?: '-' }}</td>
                                        <td>{{ $log->scanned_at?->format('H:i:s') }}</td>
                                        <td><span
                                                class="status {{ \App\Support\Status::log($log->status) }}">{{ $log->statusLabel() }}</span>
                                        </td>
                                        <td>@include('partials.proof-photo', [
                                            'log' => $log,
                                            'proofFallback' => 'Tanpa bukti',
                                        ])</td>
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
