@extends('layouts.app')

@section('title', 'Anggota')
@section('note', 'Daftar murid, guru, dan petugas absen')

@section('content')
    @php
        $secondaryCount = 0;
        if (filled($filters['role'])) $secondaryCount++;
        if (filled($filters['class_name'])) $secondaryCount++;
        if (filled($filters['face'])) $secondaryCount++;
        if ($filters['sort'] !== 'name') $secondaryCount++;
        $hasActiveSecondary = $secondaryCount > 0;
        $isFiltered = $hasActiveSecondary || filled($filters['q']);
    @endphp

    <div class="search-toolbar card">
        <form method="GET" action="{{ route('members.index') }}" class="search-toolbar-form">
            <div class="search-toolbar-row">
                <div class="search-input-box">
                    <svg class="search-box-icon" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input class="input-field search-box-input" type="search" id="q" name="q" value="{{ $filters['q'] }}"
                        placeholder="Cari nama, nomor induk, atau surel...">
                </div>

                <div class="search-toolbar-buttons">
                    <button class="btn btn-primary btn-small" type="submit">Cari</button>
                    @if($isFiltered)
                        <a class="btn btn-text btn-small" href="{{ route('members.index') }}">Bersihkan</a>
                    @endif
                </div>

                <div class="search-toolbar-aside">
                    <a class="btn btn-primary btn-small" href="{{ route('members.create') }}">+ Tambah anggota</a>
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
                            <label class="field-label" for="face">Data wajah</label>
                            <select class="select-field" id="face" name="face">
                                <option value="">Semua status wajah</option>
                                <option value="ada" @selected($filters['face'] === 'ada')>Sudah terdaftar</option>
                                <option value="kosong" @selected($filters['face'] === 'kosong')>Belum terdaftar</option>
                            </select>
                        </div>

                        <div class="field">
                            <label class="field-label" for="sort">Urutkan</label>
                            <select class="select-field" id="sort" name="sort">
                                <option value="name" @selected($filters['sort'] === 'name')>Nama (A-Z)</option>
                                <option value="identifier" @selected($filters['sort'] === 'identifier')>Nomor induk</option>
                                <option value="role" @selected($filters['sort'] === 'role')>Peran</option>
                                <option value="recent" @selected($filters['sort'] === 'recent')>Login terakhir</option>
                            </select>
                        </div>
                    </div>

                    <div class="search-filter-actions">
                        <button class="btn btn-primary btn-small" type="submit">Terapkan saringan</button>
                        @if($hasActiveSecondary)
                            <a class="btn btn-text btn-small" href="{{ route('members.index', ['q' => $filters['q']]) }}">Atur ulang saringan</a>
                        @endif
                    </div>
                </div>
            </details>
        </form>
    </div>

    <div data-live="anggota">
        <section class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Seluruh anggota</p>
                <p class="stat-value">{{ $stats['total'] }}</p>
                <p class="stat-note">Murid, guru, dan petugas</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Murid</p>
                <p class="stat-value">{{ $stats['murid'] }}</p>
                <p class="stat-note">Memiliki kelas</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Guru dan petugas</p>
                <p class="stat-value">{{ $stats['petugas'] }}</p>
                <p class="stat-note">Dapat membuka sesi absen</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Sudah punya wajah</p>
                <p class="stat-value">{{ $stats['berwajah'] }}</p>
                <p class="stat-note">Siap absen lewat kamera</p>
            </div>
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Daftar anggota</h2>
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
                                <th>Wajah</th>
                                <th>Absen</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($members as $member)
                                <tr>
                                    <td>
                                        <span class="cell-strong">{{ $member->name }}</span>
                                        <span class="cell-note">{{ $member->email }}</span>
                                        @if ($member->can_scan_face)
                                            <span class="cell-note">Boleh memindai wajah di halaman absen wajah</span>
                                        @endif
                                    </td>
                                    <td>{{ $member->identifier_number ?: '-' }}</td>
                                    <td>{{ $member->roleLabel() }}</td>
                                    <td>{{ $member->class_name ?: '-' }}</td>
                                    <td>
                                        @if ($member->face_embeddings_count > 0)
                                            <span class="status status-open">{{ $member->face_embeddings_count }}
                                                foto</span>
                                        @else
                                            <span class="status status-wait">Belum ada</span>
                                        @endif
                                    </td>
                                    <td>{{ $member->attendance_logs_count }}</td>
                                    <td>
                                        <div class="cell-actions">
                                            <a class="btn btn-small btn-outline"
                                                href="{{ route('members.edit', $member) }}">Ubah</a>

                                            <form method="POST" action="{{ route('members.password', $member) }}"
                                                data-confirm="Kembalikan sandi {{ $member->name }} ke sandi awal?">
                                                @csrf
                                                <button class="btn btn-small btn-secondary" type="submit">Reset
                                                    sandi</button>
                                            </form>

                                            @if ($member->id !== auth()->id())
                                                <form method="POST" action="{{ route('members.destroy', $member) }}"
                                                    data-confirm="Hapus anggota {{ $member->name }} beserta data wajah dan riwayat absennya?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-small btn-danger" type="submit">Hapus</button>
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
    </div>
@endsection
