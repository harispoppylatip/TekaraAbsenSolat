@extends('layouts.app')

@section('title', 'Perangkat')
@section('note', 'Pusat pengaturan sensor sidik jari dan perangkat absen')

@section('content')
    <div data-live="perangkat">
        <section class="stats-grid">
            <div class="stat-card">
                <p class="stat-label">Perangkat terdaftar</p>
                <p class="stat-value">{{ $stats['total'] }}</p>
                <p class="stat-note">{{ $stats['aktif'] }} sedang dinyalakan</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Menunggu didaftarkan</p>
                <p class="stat-value">{{ $stats['menunggu'] }}</p>
                <p class="stat-note">Perangkat baru yang belum dipakai</p>
            </div>

            <div class="stat-card">
                <p class="stat-label">Absen dari perangkat</p>
                <p class="stat-value">{{ $stats['absen'] }}</p>
                <p class="stat-note">Catatan yang menyertakan kode alat</p>
            </div>
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Perangkat baru</h2>
                    <p class="section-note">
                        Perangkat yang baru terhubung belum bisa dipakai. Beri nama, pilih jenisnya, lalu daftarkan.
                    </p>
                </div>
            </div>

            @if ($pending->isEmpty())
                <p class="empty-state">Belum ada perangkat baru yang menunggu pendaftaran.</p>
            @else
                <div class="grid-2">
                    @foreach ($pending as $device)
                        <div class="record-card">
                            <div class="record-card-body" style="padding-top: 16px;">
                                <p class="record-card-name">{{ $device->name }}</p>
                                <p class="record-card-note">
                                    Kode alat {{ $device->device_id }}.
                                    Terakhir terlihat
                                    {{ $device->last_seen_at?->translatedFormat('d F Y H:i') ?? 'belum pernah' }}.
                                </p>

                                <form method="POST" action="{{ route('devices.update', $device) }}"
                                    style="margin-top: 12px;">
                                    @csrf
                                    @method('PUT')

                                    <div class="form-grid">
                                        <div class="field">
                                            <label class="field-label" for="name-{{ $device->id }}">Nama perangkat</label>
                                            <input class="input-field" type="text" id="name-{{ $device->id }}"
                                                name="name" value="{{ $device->name }}" required>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="type-{{ $device->id }}">Jenis
                                                perangkat</label>
                                            <select class="select-field" id="type-{{ $device->id }}" name="type"
                                                required>
                                                @foreach ($types as $value => $label)
                                                    <option value="{{ $value }}" @selected($device->type === $value)>
                                                        {{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="location-{{ $device->id }}">Letak
                                                perangkat</label>
                                            <input class="input-field" type="text" id="location-{{ $device->id }}"
                                                name="location" value="{{ $device->location }}"
                                                placeholder="Musala lantai satu">
                                        </div>
                                    </div>

                                    <div class="form-actions">
                                        <button class="btn btn-small btn-outline" type="submit">Simpan data</button>
                                    </div>
                                </form>

                                <div class="form-actions">
                                    <form method="POST" action="{{ route('devices.register', $device) }}"
                                        data-confirm="Daftarkan perangkat {{ $device->name }}?">
                                        @csrf
                                        @method('PUT')
                                        <button class="btn btn-small btn-primary" type="submit">Daftarkan</button>
                                    </form>

                                    <form method="POST" action="{{ route('devices.destroy', $device) }}"
                                        data-confirm="Hapus perangkat {{ $device->name }} dari daftar?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-small btn-danger" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="card">
            <div class="section-head">
                <div>
                    <h2 class="section-title">Perangkat terdaftar</h2>
                    <p class="section-note">
                        Mematikan perangkat membuat absen dari alat itu ditolak sampai dinyalakan lagi.
                    </p>
                </div>
            </div>

            @if ($registered->isEmpty())
                <p class="empty-state">Belum ada perangkat terdaftar.</p>
            @else
                <div class="grid-2">
                    @foreach ($registered as $device)
                        <div class="record-card">
                            <div class="record-card-body" style="padding-top: 16px;">
                                <p class="record-card-name">{{ $device->name }}</p>
                                <p class="record-card-note">
                                    {{ $device->typeLabel() }}{{ $device->location ? ', ' . $device->location : '' }}.
                                    Kode alat {{ $device->device_id }}.
                                </p>
                                <p class="record-card-note">
                                    <span
                                        class="status {{ \App\Support\Status::device($device) }}">{{ $device->statusLabel() }}</span>
                                    Terakhir terlihat
                                    {{ $device->last_seen_at?->translatedFormat('d F Y H:i') ?? 'belum pernah' }}.
                                </p>

                                <form method="POST" action="{{ route('devices.update', $device) }}"
                                    style="margin-top: 12px;">
                                    @csrf
                                    @method('PUT')

                                    <div class="form-grid">
                                        <div class="field">
                                            <label class="field-label" for="name-{{ $device->id }}">Nama
                                                perangkat</label>
                                            <input class="input-field" type="text" id="name-{{ $device->id }}"
                                                name="name" value="{{ $device->name }}" required>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="type-{{ $device->id }}">Jenis
                                                perangkat</label>
                                            <select class="select-field" id="type-{{ $device->id }}" name="type"
                                                required>
                                                @foreach ($types as $value => $label)
                                                    <option value="{{ $value }}" @selected($device->type === $value)>
                                                        {{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="location-{{ $device->id }}">Letak
                                                perangkat</label>
                                            <input class="input-field" type="text" id="location-{{ $device->id }}"
                                                name="location" value="{{ $device->location }}">
                                        </div>
                                    </div>

                                    <div class="form-actions">
                                        <button class="btn btn-small btn-outline" type="submit">Simpan data</button>
                                    </div>
                                </form>

                                <div class="form-actions">
                                    <form method="POST" action="{{ route('devices.status', $device) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="active" value="{{ $device->isActive() ? 0 : 1 }}">
                                        <button
                                            class="btn btn-small {{ $device->isActive() ? 'btn-secondary' : 'btn-primary' }}"
                                            type="submit">
                                            {{ $device->isActive() ? 'Matikan' : 'Nyalakan' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('devices.destroy', $device) }}"
                                        data-confirm="Hapus perangkat {{ $device->name }}? Riwayat absen tetap tersimpan.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-small btn-danger" type="submit">Hapus</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
