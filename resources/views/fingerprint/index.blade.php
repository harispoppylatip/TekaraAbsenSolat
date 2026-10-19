@extends('layouts.app')

@section('title', 'Data Sidik Jari')
@section('note', 'Pilih dan daftarkan anggota yang diizinkan memakai mode absen sidik jari')

@section('content')
    <div data-live="sidik-jari">
        <section class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Anggota sidik jari</p>
                <p class="stat-value">{{ $stats['terdaftar'] }}</p>
                <p class="stat-note">Dari {{ $stats['total_anggota'] }} anggota terdaftar</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Template tersimpan</p>
                <p class="stat-value">{{ $stats['template'] }}</p>
                <p class="stat-note">Mode absen kedua selain wajah</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Sensor aktif</p>
                <p class="stat-value">{{ $stats['perangkat_aktif'] }}</p>
                <p class="stat-note">Perangkat yang siap menerima pindai</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Absen sidik jari hari ini</p>
                <p class="stat-value">{{ $stats['absen_hari_ini'] }}</p>
                <p class="stat-note">Tercatat lewat sensor sidik jari</p>
            </div>
        </section>

        @if (is_array($pendingEnroll))
            <div class="alert alert-info" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
                <div>
                    <strong>Menunggu perekaman jari di sensor untuk {{ $pendingEnroll['name'] ?? '-' }} ({{ $pendingEnroll['user_id'] ?? '-' }})</strong>
                    <p>
                        Posisi jari: {{ $pendingEnroll['finger_position'] ?? 'Telunjuk Kanan' }}.
                        Target alat: {{ $pendingEnroll['device_id'] ?? 'Semua sensor aktif' }}.
                        Tempelkan jari 2 kali pada sensor AS608 sebelum pukul {{ $pendingEnroll['expires_at'] ?? '-' }}.
                    </p>
                </div>

                <form method="POST" action="{{ route('fingerprint.command.cancel') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-secondary btn-small" type="submit">Batalkan perintah</button>
                </form>
            </div>
        @endif

        <div class="grid-2">
            <section class="card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Pilih dan daftarkan anggota</h2>
                        <p class="section-note">
                            Sidik jari adalah mode absen kedua. Hanya anggota yang dipilih dan didaftarkan jarinya di sini yang bisa absen menggunakan sensor sidik jari.
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('fingerprint.enroll') }}">
                    @csrf

                    <div class="form-grid">
                        <div class="field">
                            <label class="field-label" for="enroll-user-id">Pilih anggota</label>
                            <select class="select-field" id="enroll-user-id" name="user_id" required>
                                <option value="">Pilih nama anggota</option>
                                @foreach ($allMembers as $item)
                                    <option value="{{ $item->id }}" @selected((int) old('user_id') === $item->id)>
                                        {{ $item->name }} ({{ $item->identifier_number }}{{ $item->class_name ? ' / ' . $item->class_name : '' }})
                                        {{ $item->fingerprints_count > 0 ? '[Sudah terdaftar]' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label class="field-label" for="enroll-finger-position">Posisi jari</label>
                            <select class="select-field" id="enroll-finger-position" name="finger_position">
                                @foreach ($fingerPositions as $pos)
                                    <option value="{{ $pos }}" @selected(old('finger_position', 'Telunjuk Kanan') === $pos)>
                                        {{ $pos }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label class="field-label" for="enroll-device-id">Sensor tujuan</label>
                            <select class="select-field" id="enroll-device-id" name="device_id">
                                <option value="">Semua sensor aktif (otomatis)</option>
                                @foreach ($activeDevices as $device)
                                    <option value="{{ $device->device_id }}" @selected(old('device_id') === $device->device_id)>
                                        {{ $device->name }} ({{ $device->device_id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Kirim perintah rekam ke sensor</button>
                        <a class="btn btn-outline" href="{{ route('devices.index') }}">Kelola perangkat sensor</a>
                    </div>
                </form>
            </section>

            <section class="card">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">Alur kerja absen sidik jari</h2>
                        <p class="section-note">Cara mendaftarkan jari dan cara kerja absen saat waktu solat</p>
                    </div>
                </div>

                <div class="fold-note-body" style="margin-top: 0;">
                    <p>
                        1. Pastikan perangkat ESP32 sudah terdaftar dan berstatus aktif di halaman <a href="{{ route('devices.index') }}">Perangkat</a>.
                    </p>
                    <p style="margin-top: 8px;">
                        2. Pilih nama anggota pada formulir di samping lalu tekan tombol <strong>Kirim perintah rekam ke sensor</strong>.
                    </p>
                    <p style="margin-top: 8px;">
                        3. Layar LCD alat akan menampilkan tulisan <strong>Daftar: Nomor Induk</strong>. Minta anggota menempelkan jarinya 2 kali sampai muncul <strong>Daftar berhasil</strong>.
                    </p>
                    <p style="margin-top: 8px;">
                        4. Saat sesi waktu solat (atau mode uji coba) dibuka, anggota yang sudah terdaftar cukup menempelkan jarinya pada sensor. Kehadiran langsung masuk ke log dengan keterangan <strong>absen via sidik jari</strong>.
                    </p>
                </div>

                <details class="tool-panel" data-record="konfigurasi-api-iot" style="margin-top: 14px;">
                    <summary>
                        <span class="tool-name">Alamat API Mesin IoT (Offline dan Online)</span>
                        <span class="tool-note">Mode aktif di .env: {{ strtoupper($apiMode) }}</span>
                        <span class="tool-hint">
                            <span class="tool-hint-open">Lihat alamat</span>
                            <span class="tool-hint-close">Tutup</span>
                        </span>
                    </summary>

                    <div class="tool-body">
                        <p class="field-hint">
                            Alamat di bawah ini digunakan oleh firmware ESP32 pada folder proyek PlatformIO <code>tekara v2</code> (komunikasi HTTP REST API, bukan MQTT):
                        </p>
                        <div class="table-wrap" style="margin-top: 10px;">
                            <table class="data-table">
                                <tbody>
                                    <tr>
                                        <td class="cell-strong">API Offline / Lokal</td>
                                        <td><code>{{ $apiUrlOffline }}</code></td>
                                    </tr>
                                    <tr>
                                        <td class="cell-strong">API Online / Domain</td>
                                        <td><code>{{ $apiUrlOnline }}</code></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            </section>
        </div>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Daftar anggota pengguna sidik jari</h2>
                    <p class="section-note">
                        Kelola anggota yang diizinkan absen menggunakan sidik jari, rekam ulang jari, atau cabut aksesnya.
                    </p>
                </div>
            </div>

            <form method="GET" action="{{ route('fingerprint.index') }}" class="search-toolbar-form" style="margin-bottom: 16px;">
                <div class="search-toolbar-row">
                    <input class="input-field search-toolbar-input" type="search" name="q" value="{{ $search }}"
                        placeholder="Cari nama, nomor induk, atau kelas">

                    <select class="select-field search-toolbar-select" name="filter" aria-label="Filter status sidik jari">
                        <option value="terdaftar" @selected($filter === 'terdaftar')>Sudah terdaftar sidik jari</option>
                        <option value="belum" @selected($filter === 'belum')>Belum terdaftar sidik jari</option>
                        <option value="semua" @selected($filter === 'semua')>Semua anggota</option>
                    </select>

                    <div class="search-toolbar-buttons">
                        <button class="btn btn-primary btn-small" type="submit">Cari</button>
                        @if ($search !== '' || $filter !== 'terdaftar')
                            <a class="btn btn-text btn-small" href="{{ route('fingerprint.index') }}">Reset</a>
                        @endif
                    </div>
                </div>
            </form>

            @if ($members->isEmpty())
                <p class="empty-state">
                    @if ($filter === 'terdaftar')
                        Belum ada anggota yang didaftarkan sidik jarinya. Pilih anggota pada formulir di atas atau ubah filter menjadi Semua anggota.
                    @else
                        Tidak ada anggota yang cocok dengan pencarian ini.
                    @endif
                </p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nama anggota</th>
                                <th>Nomor induk</th>
                                <th>Kelas / Peran</th>
                                <th>Posisi jari</th>
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
                                        <span class="cell-strong">{{ $member->name }}</span>
                                    </td>
                                    <td>{{ $member->identifier_number ?: '-' }}</td>
                                    <td>{{ $member->class_name ?: $member->roleLabel() }}</td>
                                    <td>
                                        @if ($latestFp)
                                            <span>{{ $latestFp->finger_position ?: 'Telunjuk Kanan' }}</span>
                                            <span class="cell-note">Diperbarui {{ $latestFp->updated_at?->translatedFormat('d M Y H:i') }}</span>
                                        @else
                                            <span class="cell-note">Belum ada template</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($member->fingerprints_count > 0)
                                            <span class="status status-success">Bisa absen sidik jari</span>
                                        @else
                                            <span class="status status-neutral">Belum didaftarkan</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="cell-actions">
                                            @if ($member->fingerprints_count > 0)
                                                <form method="POST" action="{{ route('fingerprint.replace', $member) }}"
                                                    data-confirm="Hapus sidik jari lama {{ $member->name }} lalu kirim perintah rekam ulang ke sensor?">
                                                    @csrf
                                                    <button class="btn btn-small btn-outline" type="submit">Rekam ulang</button>
                                                </form>

                                                <form method="POST" action="{{ route('fingerprint.member.destroy', $member) }}"
                                                    data-confirm="Hapus data sidik jari {{ $member->name }}? Anggota ini tidak akan bisa absen via sidik jari lagi.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-small btn-danger" type="submit">Hapus</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('fingerprint.enroll') }}">
                                                    @csrf
                                                    <input type="hidden" name="user_id" value="{{ $member->id }}">
                                                    <input type="hidden" name="finger_position" value="Telunjuk Kanan">
                                                    <button class="btn btn-small btn-primary" type="submit">Pilih dan rekam jari</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
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
                    <h2 class="section-title">Log absen via sidik jari terbaru</h2>
                    <p class="section-note">
                        Lima belas catatan kehadiran terakhir yang dipindai dari mesin sensor sidik jari.
                    </p>
                </div>
            </div>

            @if ($recentLogs->isEmpty())
                <p class="empty-state">Belum ada catatan kehadiran dari sensor sidik jari.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Catatan log</th>
                                <th>Kelas</th>
                                <th>Waktu solat</th>
                                <th>Tanggal &amp; jam</th>
                                <th>Perangkat</th>
                                <th>Keadaan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentLogs as $log)
                                <tr>
                                    <td>
                                        <span class="cell-strong">{{ $log->user?->name ?? 'Anggota terhapus' }} absen via sidik jari</span>
                                        <span class="cell-note">{{ $log->summaryLine() }}</span>
                                    </td>
                                    <td>{{ $log->user?->class_name ?: '-' }}</td>
                                    <td>{{ $log->session?->prayerLabel() ?? '-' }}</td>
                                    <td>{{ $log->scanned_at?->translatedFormat('d M Y') }} pukul {{ $log->scanned_at?->format('H:i:s') }}</td>
                                    <td><code>{{ $log->device_id }}</code></td>
                                    <td>
                                        <span class="status {{ \App\Support\Status::log($log->status) }}">{{ $log->statusLabel() }}</span>
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

