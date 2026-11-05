@extends('layouts.app')

@section('title', 'Detail History: ' . $member->name)
@section('note', 'Rincian lengkap tanggal, jam, waktu solat, serta bukti foto wajah atau sidik jari')

@section('content')
    @php
        $proofService = app(\App\Services\SolatProofService::class);
    @endphp

    <style>
        .member-detail-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .member-detail-head-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
        }

        .member-detail-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }

        .member-detail-identity {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .member-detail-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #eff6ff;
            color: #0284c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
            border: 1px solid #dbeafe;
        }

        .member-detail-name {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 3px 0;
        }

        .member-detail-meta {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .member-detail-filter-row {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }

        .member-detail-filter-col label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }

        .member-detail-filter-col select,
        .member-detail-filter-col input {
            padding: 7px 11px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            color: #1e293b;
            background: #ffffff;
        }

        .btn-detail-nav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            background: #f8fafc;
            color: #334155;
            border: 1px solid #cbd5e1;
            cursor: pointer;
        }

        .btn-detail-nav:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .btn-detail-primary {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
        }

        .btn-detail-primary:hover {
            background: #0369a1;
            color: #ffffff;
        }

        .member-logs-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
            overflow: hidden;
        }

        .member-logs-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f1f5f9;
        }

        .member-logs-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 2px 0;
        }

        .member-logs-header p {
            font-size: 12.5px;
            color: #64748b;
            margin: 0;
        }

        .member-logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .member-logs-table th {
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

        .member-logs-table td {
            padding: 13px 18px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
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

        .proof-photo-card {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 5px 10px 5px 6px;
            border-radius: 10px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .proof-photo-card:hover {
            background: #dcfce7;
            border-color: #86efac;
        }

        .proof-photo-card img {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 7px;
            border: 1px solid #86efac;
            background: #0f172a;
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
        }

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

    <div class="member-detail-container">
        <section class="member-detail-head-card">
            <div class="member-detail-top">
                <div class="member-detail-identity">
                    <div class="member-detail-avatar">
                        {{ strtoupper(substr($member->name, 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="member-detail-name">{{ $member->name }}</h2>
                        <p class="member-detail-meta">
                            Nomor Induk: <strong>{{ $member->identifier_number ?: '-' }}</strong>
                            @if ($member->class_name)
                                · Kelas: <strong>{{ $member->class_name }}</strong>
                            @endif
                            @if ($member->major)
                                · Jurusan: <strong>{{ $member->major }}</strong>
                            @endif
                            · {{ $member->roleLabel() }}
                        </p>
                    </div>
                </div>

                <a class="btn-detail-nav" href="{{ route('history.index') }}">
                    &larr; Kembali ke Daftar History
                </a>
            </div>

            <form method="GET" action="{{ route('history.show', $member) }}" class="member-detail-filter-row">
                <div class="member-detail-filter-col">
                    <label for="method">Metode / Bukti Absen</label>
                    <select id="method" name="method" onchange="this.form.submit()">
                        <option value="">Semua Metode</option>
                        <option value="wajah" @selected($filters['method'] === 'wajah')>Kamera Wajah (Foto)</option>
                        <option value="fingerprint" @selected($filters['method'] === 'fingerprint')>Sidik Jari (Fingerprint)</option>
                    </select>
                </div>

                <div class="member-detail-filter-col">
                    <label for="date_from">Dari Tanggal</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] }}" onchange="this.form.submit()">
                </div>

                <div class="member-detail-filter-col">
                    <label for="date_to">Sampai Tanggal</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}" onchange="this.form.submit()">
                </div>

                @if ($filters['method'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== '')
                    <a class="btn-detail-nav" href="{{ route('history.show', $member) }}">Bersihkan Filter</a>
                @endif
            </form>
        </section>

        <section class="member-logs-card">
            <div class="member-logs-header">
                <h3>Rincian Tanggal, Jam, Waktu Solat & Bukti Absen</h3>
                <p>Diurutkan otomatis dari tanggal dan jam absen paling baru di paling atas ({{ $logs->total() }} catatan).</p>
            </div>

            @if ($logs->isEmpty())
                <div style="padding: 36px 18px; text-align: center; color: #64748b; font-size: 13.5px;">
                    Tidak ada catatan kehadiran untuk filter ini.
                </div>
            @else
                <div style="overflow-x: auto;">
                    <table class="member-logs-table">
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
                            @foreach ($logs as $log)
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
                                                    <span style="font-size: 12px; font-weight: 700; color: #1e3a8a; display: block;">Bukti Sidik Jari (Fingerprint)</span>
                                                    <span style="font-size: 11px; color: #3b82f6; display: block;">
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
                                                <div style="display: flex; flex-direction: column; gap: 2px; text-align: left;">
                                                    <span style="font-size: 12px; font-weight: 700; color: #15803d;">Bukti Foto Wajah</span>
                                                    <span style="font-size: 11px; color: #166534;">Klik untuk perbesar foto</span>
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

                <div style="padding: 14px 22px; border-top: 1px solid #f1f5f9;">
                    {{ $logs->links('pagination.records') }}
                </div>
            @endif
        </section>
    </div>

    <div id="proof-lightbox-modal" class="proof-lightbox-backdrop" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="proof-lightbox-box">
            <div class="proof-lightbox-head">
                <h4 id="proof-lightbox-title">Bukti Foto Absen Wajah</h4>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <a id="proof-lightbox-open-tab" class="btn-detail-nav" style="padding: 5px 10px; font-size: 12px;" href="#" target="_blank" rel="noopener">
                        Buka di Tab Baru
                    </a>
                    <button type="button" id="proof-lightbox-close" class="btn-detail-nav btn-detail-primary" style="padding: 5px 12px; font-size: 12px;">
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
        })();
    </script>
@endsection
