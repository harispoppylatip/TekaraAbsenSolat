@extends('layouts.app')

@section('title', 'Data Wajah')
@section('note', 'Kelola pendaftaran sidik wajah jamaah, foto referensi AI, dan hak akses petugas pemindai')

@section('content')
    <style>
        /* ========================================================
           Desain Modern Halaman Data Wajah (/wajah)
           ======================================================== */
        .face-page-container {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* ---------- 1. Status Layanan AI Banner ---------- */
        .service-status-bar {
            border-radius: 14px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .service-status-bar.ready {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .service-status-bar.warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .service-status-bar.error {
            background: #fef2f2;
            border: 1px solid #fecdd3;
            color: #991b1b;
        }

        .service-status-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .service-status-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .service-status-text {
            font-size: 13.5px;
            font-weight: 600;
        }

        .service-status-sub {
            font-size: 12px;
            opacity: 0.85;
            margin-top: 1px;
        }

        /* ---------- 2. Top Metric Banner Cards ---------- */
        .face-metric-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .face-metric-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .face-metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 30, 64, 0.07);
        }

        .face-metric-left {
            display: flex;
            align-items: center;
            gap: 14px;
            position: relative;
            z-index: 2;
        }

        .face-metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #ffffff;
        }

        .face-metric-icon.green {
            background: #059669;
        }

        .face-metric-icon.blue {
            background: #0284c7;
        }

        .face-metric-icon.orange {
            background: #ea580c;
        }

        .face-metric-icon.indigo {
            background: #4f46e5;
        }

        .face-metric-label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 2px;
        }

        .face-metric-value {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            margin-bottom: 2px;
            letter-spacing: -0.5px;
        }

        .face-metric-note {
            font-size: 11.5px;
            color: #64748b;
        }

        .face-metric-watermark {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.12;
            z-index: 1;
            pointer-events: none;
        }

        /* ---------- 3. Modern Section Card ---------- */
        .modern-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
        }

        .modern-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .modern-header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .modern-header-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #ffffff;
        }

        .modern-header-icon.amber {
            background: #d97706;
        }

        .modern-header-icon.blue {
            background: #2563eb;
        }

        .modern-header-icon.indigo {
            background: #4f46e5;
        }

        .modern-header-icon.slate {
            background: #475569;
        }

        .modern-header-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .modern-header-sub {
            font-size: 13px;
            color: #64748b;
        }

        .badge-counter {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge-counter.amber {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-counter.blue {
            background: #e0f2fe;
            color: #0369a1;
        }

        /* ---------- 4. Tabel Menunggu Pendaftaran Wajah ---------- */
        .pending-table-wrap {
            overflow-x: auto;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
        }

        .pending-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        .pending-table th {
            background: #f8fafc;
            padding: 12px 16px;
            font-weight: 600;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .pending-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .pending-table tr:hover {
            background: #fafafa;
        }

        .member-info-col {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .member-avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #334155;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .member-name-text {
            font-weight: 600;
            color: #0f172a;
            font-size: 13.5px;
        }

        .member-meta-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-pill.warning {
            background: #fef3c7;
            color: #92400e;
        }

        .status-pill.danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-pill.info {
            background: #e0f2fe;
            color: #0369a1;
        }

        .status-pill.neutral {
            background: #f1f5f9;
            color: #475569;
        }

        .status-pill.success {
            background: #dcfce7;
            color: #166534;
        }

        /* Progress Mini */
        .mini-progress-wrap {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 130px;
        }

        .mini-progress-bar {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }

        .mini-progress-fill {
            height: 100%;
            background: #2563eb;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .mini-progress-fill.full {
            background: #16a34a;
        }

        .mini-progress-text {
            font-size: 11.5px;
            color: #64748b;
        }

        /* ---------- 5. Toolbar Pencarian & Aksi ---------- */
        .face-toolbar-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .face-search-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            max-width: 520px;
        }

        .face-search-input-wrap {
            position: relative;
            flex: 1;
        }

        .face-search-input-wrap svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        .face-search-input {
            width: 100%;
            height: 42px;
            padding: 0 16px 0 42px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s ease;
            background: #ffffff;
        }

        .face-search-input:focus {
            border-color: #2563eb;
        }

        /* ---------- 6. Kartu Anggota Terdaftar (Katalog Wajah) ---------- */
        .member-card-item {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            margin-bottom: 12px;
            overflow: hidden;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .member-card-item:hover {
            border-color: #cbd5e1;
            box-shadow: 0 3px 10px rgba(0, 30, 64, 0.05);
        }

        .member-card-summary {
            list-style: none;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            gap: 14px;
            flex-wrap: wrap;
        }

        .member-card-summary::-webkit-details-marker {
            display: none;
        }

        .member-summary-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .member-avatar-lg {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid #dbeafe;
        }

        .member-summary-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .member-card-toggle {
            font-size: 12.5px;
            font-weight: 600;
            color: #2563eb;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .member-card-toggle svg {
            transition: transform 0.2s ease;
        }

        details[open] .member-card-toggle svg {
            transform: rotate(180deg);
        }

        .member-card-body {
            padding: 0 20px 20px 20px;
            border-top: 1px solid #f1f5f9;
        }

        /* Grid Galeri Foto Wajah */
        .photo-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 14px;
            margin-top: 16px;
            margin-bottom: 20px;
        }

        .photo-gallery-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
        }

        .photo-gallery-thumb-wrap {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 8px;
            overflow: hidden;
            background: #e2e8f0;
            position: relative;
        }

        .photo-gallery-thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s ease;
        }

        .photo-gallery-thumb:hover {
            transform: scale(1.05);
        }

        .photo-gallery-label {
            font-size: 11px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .photo-gallery-date {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }

        .photo-del-btn {
            margin-top: 8px;
            width: 100%;
            padding: 4px 6px;
            font-size: 11px;
            font-weight: 600;
            background: #fee2e2;
            color: #991b1b;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .photo-del-btn:hover {
            background: #fecdd3;
        }

        /* ---------- 7. Responsif ---------- */
        @media (max-width: 1024px) {
            .face-metric-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .face-metric-grid {
                grid-template-columns: 1fr;
            }
            .modern-card {
                padding: 16px;
            }
            .face-search-form {
                max-width: 100%;
            }
            .face-toolbar-row {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>

    <div class="face-page-container">

        {{-- 1. Status Layanan AI (InsightFace Health) --}}
        @if ($serviceReady && $serviceModel !== null && $serviceModel !== $expectedModel)
            <div class="service-status-bar error">
                <div class="service-status-left">
                    <span class="service-status-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                    </span>
                    <div>
                        <div class="service-status-text">Perbedaan Versi Model Mesin Wajah</div>
                        <div class="service-status-sub">Aplikasi mengharapkan <code>{{ $expectedModel }}</code>, tetapi layanan wajah melaporkan <code>{{ $serviceModel }}</code>. Foto tidak dapat dicocokkan sampai keduanya sama.</div>
                    </div>
                </div>
            </div>
        @elseif ($serviceReady)
            <div class="service-status-bar ready">
                <div class="service-status-left">
                    <span class="service-status-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </span>
                    <div>
                        <div class="service-status-text">Layanan AI InsightFace Aktif & Terhubung</div>
                        <div class="service-status-sub">Model: <strong>{{ $serviceModel ?? 'InsightFace' }}</strong> · Endpoint: <code>{{ $serviceUrl }}</code></div>
                    </div>
                </div>
                <span class="status-pill success">Siap Pindai</span>
            </div>
        @else
            <div class="service-status-bar warning">
                <div class="service-status-left">
                    <span class="service-status-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </span>
                    <div>
                        <div class="service-status-text">Layanan AI Wajah Tidak Terhubung</div>
                        <div class="service-status-sub">Layanan wajah di <code>{{ $serviceUrl }}</code> tidak dapat dihubungi. Foto baru akan ditampung di antrean sampai layanan kembali aktif.</div>
                    </div>
                </div>
                <span class="status-pill warning">Perlu Diperiksa</span>
            </div>
        @endif

        {{-- 2. Top Metric Banner Cards (4 Kartu Utama) --}}
        <section class="face-metric-grid" aria-label="Statistik Data Wajah">
            {{-- Kartu 1: Kesiapan Absen --}}
            <div class="face-metric-card">
                <div class="face-metric-left">
                    <div class="face-metric-icon green">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div class="face-metric-label">Wajah Siap Absen</div>
                        <div class="face-metric-value">{{ $stats['berwajah'] }} <span style="font-size: 15px; color: #64748b; font-weight: 500;">/ {{ $stats['anggota'] }}</span></div>
                        <div class="face-metric-note">{{ $stats['selesai'] }} mandiri selesai</div>
                    </div>
                </div>
                <div class="face-metric-watermark" style="color: #059669;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                        <line x1="9" y1="9" x2="9.01" y2="9"></line>
                        <line x1="15" y1="9" x2="15.01" y2="9"></line>
                    </svg>
                </div>
            </div>

            {{-- Kartu 2: Foto Tersimpan --}}
            <div class="face-metric-card">
                <div class="face-metric-left">
                    <div class="face-metric-icon blue">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div class="face-metric-label">Foto Wajah AI</div>
                        <div class="face-metric-value">{{ $stats['foto'] }}</div>
                        <div class="face-metric-note">Maks. {{ $stats['batas'] }} foto/anggota</div>
                    </div>
                </div>
                <div class="face-metric-watermark" style="color: #0284c7;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                </div>
            </div>

            {{-- Kartu 3: Perlu Tindakan --}}
            <div class="face-metric-card">
                <div class="face-metric-left">
                    <div class="face-metric-icon orange">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div>
                        <div class="face-metric-label">Perlu Tindakan</div>
                        <div class="face-metric-value">{{ $stats['belum'] }}</div>
                        <div class="face-metric-note">{{ $stats['perlu_daftar_ulang'] }} daftar ulang &middot; {{ $stats['sedang'] }} proses</div>
                    </div>
                </div>
                <div class="face-metric-watermark" style="color: #ea580c;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
            </div>

            {{-- Kartu 4: Petugas Kamera --}}
            <div class="face-metric-card">
                <div class="face-metric-left">
                    <div class="face-metric-icon indigo">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                    </div>
                    <div>
                        <div class="face-metric-label">Petugas Kamera</div>
                        <div class="face-metric-value">{{ $operators->count() }}</div>
                        <div class="face-metric-note">Akses pemindai kamera web</div>
                    </div>
                </div>
                <div class="face-metric-watermark" style="color: #4f46e5;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                </div>
            </div>
        </section>

        {{-- 3. Kemajuan Proses Latar Belakang (Jika Ada Batch Berjalan) --}}
        <section class="modern-card" data-face-progress data-endpoint="{{ route('face.progress') }}"
            data-retry-template="{{ route('face.queue.retry', ['batch' => '__BATCH__']) }}"
            @if ($batches === []) hidden @endif>
            <div class="modern-card-header">
                <div class="modern-header-left">
                    <div class="modern-header-icon blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="2" x2="12" y2="6"></line>
                            <line x1="12" y1="18" x2="12" y2="22"></line>
                            <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line>
                            <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>
                            <line x1="2" y1="12" x2="6" y2="12"></line>
                            <line x1="18" y1="12" x2="22" y2="12"></line>
                            <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line>
                            <line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>
                        </svg>
                    </div>
                    <div>
                        <h2 class="modern-header-title">Proses Antrean Wajah di Latar Belakang</h2>
                        <p class="modern-header-sub">Foto diubah menjadi angka embedding AI satu per satu (~{{ $expectedSeconds }} detik per foto).</p>
                    </div>
                </div>
            </div>

            <div class="queue-list" data-face-batches>
                @foreach ($batches as $batch)
                    @include('face.partials.batch', ['batch' => $batch])
                @endforeach
            </div>
        </section>

        {{-- 4. BAGIAN PENTING DITONJOLKAN: Menunggu Pendaftaran Wajah (Belum Selesai / Perlu Tindakan) --}}
        <section class="modern-card" style="border-left: 4px solid #d97706;">
            <div class="modern-card-header" style="flex-wrap: wrap; gap: 12px;">
                <div class="modern-header-left">
                    <div class="modern-header-icon amber">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div>
                        <h2 class="modern-header-title">Menunggu Pendaftaran Wajah</h2>
                        <p class="modern-header-sub">Anggota berikut belum menyelesaikan pendaftaran wajah atau perlu melakukan pendaftaran ulang.</p>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <details class="tool-panel" style="margin: 0; border: none; padding: 0; position: relative;">
                        <summary style="list-style: none; display: inline-flex;">
                            <span class="btn btn-small btn-outline" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <line x1="17" y1="8" x2="23" y2="8"></line>
                                </svg>
                                + Bebaskan Anggota
                            </span>
                        </summary>
                        <div style="background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 16px; margin-top: 10px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); width: 330px; position: absolute; right: 0; z-index: 20;">
                            <form method="POST" action="{{ route('face.member.exempt-post') }}">
                                @csrf
                                <div style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">Bebaskan dari Pendaftaran Wajah</div>
                                <p style="font-size: 11.5px; color: #64748b; margin-bottom: 12px; line-height: 1.4;">
                                    Gunakan ini untuk Petugas, Admin, atau anggota yang tidak wajib diabsen menggunakan wajah.
                                </p>
                                <label for="exempt_user_id" style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 5px;">Pilih Anggota</label>
                                <select name="user_id" id="exempt_user_id" class="select-field" style="width: 100%; font-size: 13px; padding: 7px 10px; border-radius: 8px; border: 1px solid #cbd5e1; margin-bottom: 12px;" required>
                                    <option value="">-- Pilih anggota --</option>
                                    @foreach ($candidates as $cand)
                                        <option value="{{ $cand->id }}">
                                            {{ $cand->name }} ({{ config('solat.roles')[$cand->role] ?? $cand->role }})
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-small btn-primary" style="width: 100%;">Tandai Tidak Wajib Wajah</button>
                            </form>
                        </div>
                    </details>
                    <div class="badge-counter amber">
                        {{ $pending->total() }} Anggota Perlu Perhatian
                    </div>
                </div>
            </div>

            @if ($pending->isEmpty())
                <div style="background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 12px; padding: 20px; text-align: center; color: #166534;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 8px auto;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <p style="font-weight: 600; font-size: 14px; margin: 0;">Luar biasa! Seluruh anggota telah menyelesaikan pendaftaran wajah.</p>
                </div>
            @else
                <div class="pending-table-wrap">
                    <table class="pending-table">
                        <thead>
                            <tr>
                                <th>Anggota</th>
                                <th>Status Pendaftaran</th>
                                <th>Kemajuan Foto</th>
                                <th style="text-align: right; min-width: 260px;">Aksi Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending as $anggota)
                                @php
                                    $st = $anggota->enrollmentStatus();
                                    $cnt = $anggota->face_embeddings_count;
                                    $pct = min(100, (int) round(($cnt / max(1, $stats['wajib_foto'])) * 100));
                                @endphp
                                <tr>
                                    <td>
                                        <div class="member-info-col">
                                            <div class="member-avatar-circle">
                                                {{ strtoupper(substr($anggota->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="member-name-text">{{ $anggota->name }}</div>
                                                <div class="member-meta-text">
                                                    {{ $anggota->identifier_number ?: 'Tanpa NIS/NIP' }}
                                                    {{ $anggota->class_name ? ' · Kelas ' . $anggota->class_name : ' · ' . $anggota->roleLabel() }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($st === \App\Models\User::EnrollmentNeedsReenrollment)
                                            <span class="status-pill warning">Perlu Daftar Ulang</span>
                                        @elseif ($st === \App\Models\User::EnrollmentRejected)
                                            <span class="status-pill danger">Foto Ditolak</span>
                                        @elseif ($st === \App\Models\User::EnrollmentInProgress || $st === \App\Models\User::EnrollmentProcessing)
                                            <span class="status-pill info">Sedang Diproses</span>
                                        @else
                                            <span class="status-pill neutral">Belum Mulai</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="mini-progress-wrap">
                                            <div class="mini-progress-bar">
                                                <div class="mini-progress-fill {{ $pct >= 100 ? 'full' : '' }}" style="width: {{ $pct }}%;"></div>
                                            </div>
                                            <span class="mini-progress-text"><strong>{{ $cnt }}</strong> dari {{ $stats['wajib_foto'] }} foto wajib</span>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center; flex-wrap: nowrap;">
                                            @if ($st === \App\Models\User::EnrollmentNeedsReenrollment)
                                                <span style="font-size: 12px; color: #64748b; font-weight: 500; white-space: nowrap;">Sudah diminta daftar ulang</span>
                                            @else
                                                <form method="POST" action="{{ route('face.enrollment.reopen', $anggota) }}" style="display: inline-block;">
                                                    @csrf
                                                    @method('PUT')
                                                    <button class="btn btn-small btn-outline" type="submit" style="white-space: nowrap;"
                                                        data-confirm="Buka dan minta {{ $anggota->name }} mendaftarkan wajahnya kembali? Foto lamanya tetap tersimpan sampai foto barunya diverifikasi.">
                                                        Minta daftar ulang
                                                    </button>
                                                </form>
                                            @endif

                                            <form method="POST" action="{{ route('face.member.exempt', $anggota) }}" style="display: inline-block;">
                                                @csrf
                                                @method('PUT')
                                                <button class="btn btn-small btn-secondary" type="submit"
                                                    data-confirm="Tandai {{ $anggota->name }} ({{ $anggota->roleLabel() }}) sebagai tidak wajib mendaftarkan wajah? Akun ini tidak akan muncul di daftar antrean."
                                                    style="background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; white-space: nowrap;" title="Bebaskan anggota ini dari kewajiban mendaftarkan wajah">
                                                    Tidak Wajib Wajah
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 14px;">
                    {{ $pending->links('pagination.records') }}
                </div>
            @endif

            @if ($exempted->isNotEmpty())
                <details style="margin-top: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px;">
                    <summary style="font-size: 13px; font-weight: 600; color: #334155; cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
                        <span>Anggota yang Dibebaskan dari Wajah ({{ $exempted->count() }} orang)</span>
                        <span style="font-size: 11.5px; color: #0284c7; font-weight: 500;">Buka daftar</span>
                    </summary>
                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
                        <table class="pending-table" style="font-size: 12.5px;">
                            <thead>
                                <tr>
                                    <th>Nama Anggota</th>
                                    <th>Peran</th>
                                    <th>Status Wajah</th>
                                    <th style="text-align: right;">Aksi Tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($exempted as $ex)
                                    <tr>
                                        <td>
                                            <strong style="color: #0f172a;">{{ $ex->name }}</strong>
                                            <div style="font-size: 11px; color: #64748b;">{{ $ex->identifier_number ?: 'Tanpa NIS/NIP' }}</div>
                                        </td>
                                        <td>
                                            <span class="status-pill neutral">{{ $ex->roleLabel() }}</span>
                                        </td>
                                        <td>
                                            <span style="font-size: 12px; color: #64748b;">Tidak wajib ({{ $ex->face_embeddings_count }} foto tersimpan)</span>
                                        </td>
                                        <td style="text-align: right;">
                                            <form method="POST" action="{{ route('face.member.unexempt', $ex) }}" style="display: inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-small btn-outline" type="submit"
                                                    data-confirm="Aktifkan kembali kewajiban pendaftaran wajah untuk {{ $ex->name }}?">
                                                    Wajibkan Daftar Wajah
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
        </section>

        {{-- 5. Toolbar Pencarian & Panel Unggah Foto --}}
        <div class="modern-card" style="padding: 16px 20px;">
            <div class="face-toolbar-row">
                <form method="GET" action="{{ route('face.index') }}" class="face-search-form">
                    <div class="face-search-input-wrap">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input class="face-search-input" type="search" id="q" name="q" value="{{ $term }}"
                            placeholder="Cari nama anggota, nomor induk, atau kelas...">
                    </div>
                    <button class="btn btn-primary btn-small" type="submit">Cari</button>
                    @if(filled($term))
                        <a class="btn btn-text btn-small" href="{{ route('face.index') }}">Reset</a>
                    @endif
                </form>

                <details class="tool-panel" data-record="tool-unggah" @if ($errors->any()) open @endif style="margin: 0; border: none; padding: 0;">
                    <summary style="list-style: none; display: inline-flex;">
                        <span class="btn btn-primary" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            + Unggah Foto Wajah Manual
                        </span>
                    </summary>

                    {{-- Form Upload Modal / Dropdown --}}
                    <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 14px; padding: 20px; margin-top: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
                        <form method="POST" action="{{ route('face.photos.store') }}" enctype="multipart/form-data">
                            @csrf
                            <h3 style="font-size: 14.5px; font-weight: 700; color: #0f172a; margin-bottom: 12px;">Unggah Foto Wajah Anggota</h3>

                            <div class="form-grid" style="margin-bottom: 14px;">
                                <div class="field">
                                    <label class="field-label" for="user_id">Pilih Anggota</label>
                                    <select class="select-field" id="user_id" name="user_id" required>
                                        <option value="">-- Pilih anggota --</option>
                                        @foreach ($candidates as $candidate)
                                            <option value="{{ $candidate->id }}" @selected((int) old('user_id') === $candidate->id)>
                                                {{ $candidate->name }}{{ $candidate->identifier_number ? ' (' . $candidate->identifier_number . ')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_id')
                                        <p class="field-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="field">
                                    <label class="field-label" for="photos">Berkas Foto Wajah</label>
                                    <input class="input-field" type="file" id="photos" name="photos[]" accept="image/*" multiple required>
                                    <p class="field-hint">Maksimal {{ $maxSubmit }} foto sekaligus dari sudut dan pencahayaan berbeda.</p>
                                    @error('photos')
                                        <p class="field-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="check-row" style="margin-bottom: 14px;">
                                <input type="checkbox" id="replace" name="replace" value="1">
                                <label for="replace" style="font-size: 13px; color: #475569;">Ganti seluruh foto lama anggota ini dengan foto baru yang berhasil diproses.</label>
                            </div>

                            <button class="btn btn-primary" type="submit">Kirim Foto ke Antrean AI</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>

        {{-- 6. KATALOG DATA WAJAH TERDAFTAR (Katalog Anggota & Galeri Foto) --}}
        <div data-live="wajah">
            <section class="modern-card">
                <div class="modern-card-header">
                    <div class="modern-header-left">
                        <div class="modern-header-icon blue">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="modern-header-title">Katalog Wajah Anggota</h2>
                            <p class="modern-header-sub">Menampilkan {{ $members->firstItem() ?? 0 }} - {{ $members->lastItem() ?? 0 }} dari total {{ $members->total() }} anggota. Klik pada kartu untuk melihat galeri foto wajah.</p>
                        </div>
                    </div>
                </div>

                @if ($members->isEmpty())
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; text-align: center; color: #64748b;">
                        <p style="margin: 0; font-size: 14px;">Tidak ada anggota yang cocok dengan kata kunci pencarian tersebut.</p>
                    </div>
                @else
                    <div class="member-cards-container">
                        @foreach ($members as $member)
                            @php
                                $hasCompleted = $member->hasCompletedEnrollment();
                                $faceCount = $member->face_embeddings_count;
                            @endphp
                            <details class="member-card-item" data-record="{{ $member->id }}">
                                <summary class="member-card-summary">
                                    <div class="member-summary-left">
                                        <div class="member-avatar-lg">
                                            {{ strtoupper(substr($member->name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div style="font-size: 14.5px; font-weight: 700; color: #0f172a;">{{ $member->name }}</div>
                                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                                {{ $member->identifier_number ? 'Nomor Induk: ' . $member->identifier_number : $member->roleLabel() }}
                                                {{ $member->class_name ? ' · Kelas ' . $member->class_name : '' }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="member-summary-meta">
                                        @if ($member->isExemptFromEnrollment())
                                            <span class="status-pill neutral" style="background: #f1f5f9; color: #475569;">Tidak Wajib Wajah</span>
                                        @elseif ($hasCompleted)
                                            <span class="status-pill success">Mandiri Selesai</span>
                                        @endif

                                        @if ($faceCount >= $photosNeeded)
                                            <span class="status-pill info">{{ $faceCount }} Foto Tersimpan</span>
                                        @elseif ($faceCount > 0)
                                            <span class="status-pill warning">{{ $faceCount }} / {{ $photosNeeded }} Foto (Kurang)</span>
                                        @elseif (! $member->isExemptFromEnrollment())
                                            <span class="status-pill neutral">Belum Ada Foto</span>
                                        @endif

                                        <div class="member-card-toggle">
                                            <span>Detail Foto</span>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="6 9 12 15 18 9"></polyline>
                                            </svg>
                                        </div>
                                    </div>
                                </summary>

                                <div class="member-card-body">
                                    @if ($member->faceEmbeddings->isEmpty())
                                        <div style="padding: 16px 0; color: #64748b; font-size: 13px;">
                                            @if ($member->isExemptFromEnrollment())
                                                Anggota ini ditandai <strong>tidak wajib mendaftarkan wajah</strong> (misalnya untuk akun petugas / administrator).
                                            @else
                                                Anggota ini belum memiliki foto wajah tersimpan. Gunakan tombol <strong>+ Unggah Foto Wajah Manual</strong> di atas atau minta anggota melakukan pendaftaran wajah mandiri di akunnya.
                                            @endif
                                        </div>
                                    @else
                                        @if ($faceCount < $photosNeeded)
                                            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 14px; font-size: 12.5px; color: #92400e; margin-top: 14px;">
                                                Baru tersimpan <strong>{{ $faceCount }} foto</strong>. Disarankan memiliki minimal {{ $photosNeeded }} foto dari sudut dan pencahayaan bervariasi agar akurasi pengenalan kamera maksimal.
                                            </div>
                                        @endif

                                        {{-- Galeri Foto Grid --}}
                                        <div class="photo-gallery-grid">
                                            @foreach ($member->faceEmbeddings as $embedding)
                                                <div class="photo-gallery-item">
                                                    <div class="photo-gallery-thumb-wrap">
                                                        @if ($embedding->photo_path)
                                                            <a href="{{ route('enrollment.photos.show', $embedding) }}" target="_blank" rel="noopener" title="Buka foto resolusi penuh">
                                                                <img class="photo-gallery-thumb" src="{{ route('enrollment.photos.show', $embedding) }}" alt="Foto wajah" loading="lazy" decoding="async">
                                                            </a>
                                                        @else
                                                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #94a3b8;">
                                                                Tanpa Berkas
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="photo-gallery-label" title="{{ $embedding->label ?: 'Tanpa keterangan' }}">
                                                        {{ $embedding->label ?: 'Foto Wajah' }}
                                                    </div>
                                                    <div class="photo-gallery-date">
                                                        {{ $embedding->created_at?->translatedFormat('d/m/y H:i') }}
                                                    </div>

                                                    @isset($outlierPhotos[$embedding->id])
                                                        <div style="font-size: 10px; color: #dc2626; font-weight: 600; margin-top: 4px; line-height: 1.2;">
                                                            Penyimpangan: {{ number_format($outlierPhotos[$embedding->id], 2, ',', '.') }}
                                                        </div>
                                                    @endisset

                                                    <form method="POST" action="{{ route('face.photos.destroy', $embedding) }}"
                                                        data-confirm="Hapus satu foto wajah {{ $member->name }} ini?" style="width: 100%;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="photo-del-btn" type="submit">Hapus</button>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Aksi Kartu Anggota --}}
                                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 14px; margin-top: 14px;">
                                        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                                            @if (! $member->faceEmbeddings->isEmpty())
                                                {{-- Tombol 1-Klik: Hapus Semua Foto & Buka Pendaftaran Ulang --}}
                                                <form method="POST" action="{{ route('face.member.reset-reenroll', $member) }}"
                                                    data-confirm="Hapus seluruh foto wajah {{ $member->name }} dan langsung buka pendaftaran ulang agar anggota dapat mendaftar dari awal?">
                                                    @csrf
                                                    <button class="btn btn-small btn-danger" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <polyline points="3 6 5 6 21 6"></polyline>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                        </svg>
                                                        Hapus Semua Foto & Buka Pendaftaran Ulang
                                                    </button>
                                                </form>
                                            @endif

                                            <a class="btn btn-small btn-outline" href="{{ route('members.edit', $member) }}">Ubah Data Anggota</a>

                                            {{-- Tombol Toggle Bebas Wajah --}}
                                            @if ($member->isExemptFromEnrollment())
                                                <form method="POST" action="{{ route('face.member.unexempt', $member) }}" style="display: inline-block;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-small btn-outline" type="submit" style="color: #0284c7; border-color: #93c5fd;"
                                                        data-confirm="Wajibkan {{ $member->name }} mendaftarkan wajah?">
                                                        Wajibkan Daftar Wajah
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('face.member.exempt', $member) }}" style="display: inline-block;">
                                                    @csrf
                                                    @method('PUT')
                                                    <button class="btn btn-small btn-secondary" type="submit" style="background: #f8fafc; color: #475569; border: 1px solid #cbd5e1;"
                                                        data-confirm="Tandai {{ $member->name }} sebagai tidak wajib mendaftarkan wajah?">
                                                        Bebaskan dari Wajah
                                                    </button>
                                                </form>
                                            @endif
                                        </div>

                                        @if ($hasCompleted)
                                            {{-- Opsi Buka Pendaftaran Ulang Tetapi Tetap Simpan Foto Lama --}}
                                            <form method="POST" action="{{ route('face.enrollment.reopen', $member) }}"
                                                data-confirm="Buka pendaftaran ulang wajah {{ $member->name }} tanpa menghapus foto lamanya? (Foto lama tetap aktif sampai foto baru diverifikasi).">
                                                @csrf
                                                @method('PUT')
                                                <button class="btn btn-small btn-outline" type="submit" style="color: #2563eb; border-color: #93c5fd;" title="Buka pendaftaran ulang tetapi simpan foto lama">
                                                    Buka Pendaftaran Saja (Simpan Foto)
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </details>
                        @endforeach
                    </div>

                    <div style="margin-top: 16px;">
                        {{ $members->links('pagination.records') }}
                    </div>
                @endif
            </section>

            {{-- 7. PETUGAS KAMERA ABSEN --}}
            <section class="modern-card" style="margin-top: 24px;">
                <div class="modern-card-header">
                    <div class="modern-header-left">
                        <div class="modern-header-icon indigo">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                        </div>
                        <div>
                            <h2 class="modern-header-title">Hak Akses Petugas Kamera Absen</h2>
                            <p class="modern-header-sub">Guru atau petugas yang diberikan wewenang untuk membuka halaman pemindai absensi wajah mandiri.</p>
                        </div>
                    </div>
                </div>

                @if ($assignable->isEmpty())
                    <p style="color: #64748b; font-size: 13.5px;">Belum ada guru atau petugas yang dapat ditugaskan.</p>
                @else
                    <div class="pending-table-wrap">
                        <table class="pending-table">
                            <thead>
                                <tr>
                                    <th>Nama Petugas</th>
                                    <th>Peran</th>
                                    <th>Status Hak Akses</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assignable as $candidate)
                                    <tr data-member="{{ $candidate->id }}">
                                        <td style="font-weight: 600; color: #0f172a;">{{ $candidate->name }}</td>
                                        <td>{{ $candidate->roleLabel() }}</td>
                                        <td>
                                            @if ($candidate->can_scan_face)
                                                <span class="status-pill success" data-operator-status>Sedang Bertugas</span>
                                            @else
                                                <span class="status-pill neutral" data-operator-status>Tidak Bertugas</span>
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            <form method="POST" action="{{ route('face.operator.update', $candidate) }}" style="display: inline-block;">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="assigned" value="{{ $candidate->can_scan_face ? 0 : 1 }}">
                                                <button class="btn btn-small {{ $candidate->can_scan_face ? 'btn-danger' : 'btn-primary' }}" type="submit">
                                                    {{ $candidate->can_scan_face ? 'Cabut Tugas' : 'Tugaskan Petugas' }}
                                                </button>
                                            </form>
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
