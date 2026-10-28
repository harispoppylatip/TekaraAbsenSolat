@extends('layouts.app')

@section('title', 'Pengaturan, Cadangan & Pembersihan Data')
@section('note', 'Kelola cadangan lengkap (database & seluruh foto), pulihkan data, atau bersihkan data sistem')

@section('content')
    @php
        $formattedSize = $photoTotalBytes >= 1048576
            ? number_format($photoTotalBytes / 1048576, 2, ',', '.').' MB'
            : number_format(max($photoTotalBytes / 1024, 0), 1, ',', '.').' KB';
    @endphp

    <style>
        /* ========================================================
           Desain Modern Halaman Pengaturan (Sesuai Referensi Pengguna)
           ======================================================== */
        .settings-container {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* ---------- 1. Kartu Statistik Utama (Top 3 Cards) ---------- */
        .stat-banner-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .stat-banner-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-banner-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 30, 64, 0.07);
        }

        .stat-banner-left {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            z-index: 2;
        }

        .stat-banner-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #ffffff;
        }

        .stat-banner-icon.green {
            background: #059669;
        }

        .stat-banner-icon.blue {
            background: #0284c7;
        }

        .stat-banner-icon.orange {
            background: #ea580c;
        }

        .stat-banner-label {
            font-size: 13.5px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 2px;
        }

        .stat-banner-value {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
            margin-bottom: 3px;
            letter-spacing: -0.5px;
        }

        .stat-banner-note {
            font-size: 12px;
            color: #64748b;
        }

        .stat-banner-watermark {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.12;
            z-index: 1;
            pointer-events: none;
        }

        /* ---------- 2. Panel Pembersihan Data per Bagian (5 Cards) ---------- */
        .clean-section-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
        }

        .clean-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .clean-header-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .clean-header-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .clean-header-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .clean-header-sub {
            font-size: 13px;
            color: #64748b;
        }

        .clean-cards-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
        }

        .clean-item-card {
            border-radius: 14px;
            padding: 18px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 250px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .clean-item-card:hover {
            transform: translateY(-2px);
        }

        /* Varian warna 5 kartu */
        .clean-item-card.blue {
            background: #f0f7ff;
            border: 1px solid #dbeafe;
        }

        .clean-item-card.purple {
            background: #fbf7ff;
            border: 1px solid #ede9fe;
        }

        .clean-item-card.teal {
            background: #f0fdf4;
            border: 1px solid #d1fae5;
        }

        .clean-item-card.red {
            background: #fef2f2;
            border: 1px solid #fee2e2;
        }

        .clean-item-card.indigo {
            background: #eef2ff;
            border: 1px solid #e0e7ff;
        }

        .clean-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .clean-item-badge {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
        }

        .clean-item-badge.blue {
            background: #2563eb;
        }

        .clean-item-badge.purple {
            background: #7c3aed;
        }

        .clean-item-badge.teal {
            background: #059669;
        }

        .clean-item-badge.red {
            background: #dc2626;
        }

        .clean-item-badge.indigo {
            background: #4f46e5;
        }

        .clean-item-arrow {
            font-size: 16px;
            font-weight: 700;
        }

        .clean-item-arrow.blue {
            color: #2563eb;
        }

        .clean-item-arrow.purple {
            color: #7c3aed;
        }

        .clean-item-arrow.teal {
            color: #059669;
        }

        .clean-item-arrow.red {
            color: #dc2626;
        }

        .clean-item-arrow.indigo {
            color: #4f46e5;
        }

        .clean-item-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
            line-height: 1.35;
        }

        .clean-item-desc {
            font-size: 12px;
            color: #475569;
            line-height: 1.5;
            margin-bottom: 16px;
            flex-grow: 1;
        }

        .clean-item-btn {
            width: 100%;
            height: 38px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            transition: opacity 0.2s ease, filter 0.2s ease;
        }

        .clean-item-btn:hover:not(:disabled) {
            filter: brightness(0.92);
        }

        .clean-item-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .clean-item-btn.blue {
            background: #2563eb;
        }

        .clean-item-btn.purple {
            background: #7c3aed;
        }

        .clean-item-btn.teal {
            background: #059669;
        }

        .clean-item-btn.red {
            background: #dc2626;
        }

        .clean-item-btn.indigo {
            background: #4f46e5;
        }

        /* ---------- 3. Panel Reset Total Sistem (Accordion) ---------- */
        .reset-section-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 30, 64, 0.04);
            overflow: hidden;
        }

        .reset-summary {
            list-style: none;
            padding: 22px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            background: #ffffff;
            transition: background-color 0.2s ease;
        }

        .reset-summary::-webkit-details-marker {
            display: none;
        }

        .reset-summary:hover {
            background: #fafafa;
        }

        .reset-summary-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .reset-icon-badge {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .reset-title {
            font-size: 15.5px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .reset-subtitle {
            font-size: 13px;
            color: #64748b;
        }

        .reset-chevron {
            color: #64748b;
            transition: transform 0.25s ease;
        }

        details[open] .reset-chevron {
            transform: rotate(180deg);
        }

        .reset-body {
            padding: 0 24px 24px 24px;
            border-top: 1px solid #f1f5f9;
        }

        .reset-warning-box {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 20px;
            margin-bottom: 18px;
        }

        .reset-warning-icon {
            color: #e11d48;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .reset-warning-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #be123c;
            margin-bottom: 3px;
        }

        .reset-warning-text {
            font-size: 12.5px;
            color: #be123c;
            line-height: 1.5;
            margin: 0;
        }

        .reset-form-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .reset-input {
            flex: 1;
            height: 42px;
            padding: 0 16px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13.5px;
            color: #0f172a;
            background: #ffffff;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .reset-input:focus {
            border-color: #e11d48;
        }

        .reset-submit-btn {
            height: 42px;
            padding: 0 22px;
            background: #dc2626;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            white-space: nowrap;
            transition: background-color 0.2s ease;
        }

        .reset-submit-btn:hover {
            background: #b91c1c;
        }

        /* ---------- 4. Panel Cadangan & Pemulihan (.zip) ---------- */
        .backup-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-top: 18px;
        }

        .backup-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .backup-card-title {
            font-size: 14.5px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .backup-card-hint {
            font-size: 12.5px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 16px;
            flex-grow: 1;
        }

        /* ---------- 5. Responsif Tablet & Ponsel ---------- */
        @media (max-width: 1100px) {
            .stat-banner-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
            .clean-cards-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 820px) {
            .stat-banner-grid {
                grid-template-columns: 1fr;
            }
            .clean-cards-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .backup-grid {
                grid-template-columns: 1fr;
            }
            .reset-form-row {
                flex-direction: column;
                align-items: stretch;
            }
            .reset-submit-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 540px) {
            .clean-cards-grid {
                grid-template-columns: 1fr;
            }
            .clean-section-card,
            .reset-body {
                padding: 16px;
            }
            .reset-summary {
                padding: 16px;
            }
        }
    </style>

    <div class="settings-container">

        {{-- 1. Kartu Ringkasan Statistik (3 Banner Cards) --}}
        <section class="stat-banner-grid" aria-label="Ringkasan Data Operasional">
            {{-- Kartu Sesi Absen (Hijau) --}}
            <div class="stat-banner-card">
                <div class="stat-banner-left">
                    <div class="stat-banner-icon green">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-banner-label">Sesi Absen</div>
                        <div class="stat-banner-value">{{ $sessionCount }}</div>
                        <div class="stat-banner-note">Sesi solat & uji coba</div>
                    </div>
                </div>
                <div class="stat-banner-watermark" style="color: #059669;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
            </div>

            {{-- Kartu Data & Berkas Foto (Biru) --}}
            <div class="stat-banner-card">
                <div class="stat-banner-left">
                    <div class="stat-banner-icon blue">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-banner-label">Data & Berkas Foto</div>
                        <div class="stat-banner-value">{{ $photoFileCount }}</div>
                        <div class="stat-banner-note">{{ $faceCount }} wajah terdaftar ({{ $formattedSize }})</div>
                    </div>
                </div>
                <div class="stat-banner-watermark" style="color: #0284c7;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                </div>
            </div>

            {{-- Kartu Anggota (Oranye) --}}
            <div class="stat-banner-card">
                <div class="stat-banner-left">
                    <div class="stat-banner-icon orange">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-banner-label">Anggota</div>
                        <div class="stat-banner-value">{{ $memberCount }}</div>
                        <div class="stat-banner-note">Murid & petugas ({{ $adminCount }} admin aman)</div>
                    </div>
                </div>
                <div class="stat-banner-watermark" style="color: #ea580c;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
            </div>
        </section>

        {{-- 2. Pembersihan Data per Bagian (5 Cards) --}}
        <section class="clean-section-card">
            <div class="clean-section-header">
                <div class="clean-header-title-row">
                    <div class="clean-header-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="clean-header-title">Pembersihan Data per Bagian</h2>
                        <p class="clean-header-sub">Pilih bagian data tertentu yang ingin dibersihkan tanpa mengganggu data lainnya.</p>
                    </div>
                </div>
                <div style="color: #94a3b8;" title="Operasi penghapusan data bersifat permanen">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </div>
            </div>

            <div class="clean-cards-grid">
                {{-- 1. Hapus Riwayat Absen (Biru) --}}
                <div class="clean-item-card blue">
                    <div>
                        <div class="clean-item-top">
                            <div class="clean-item-badge blue">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <span class="clean-item-arrow blue">&rarr;</span>
                        </div>
                        <h3 class="clean-item-title">Hapus Seluruh Riwayat Absen</h3>
                        <p class="clean-item-desc">
                            Menghapus {{ $attendanceCount }} catatan kehadiran beserta foto bukti absennya. Sesi absen, anggota, dan data wajah tetap utuh.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('settings.clear-attendance') }}"
                        data-confirm="PERINGATAN: Seluruh riwayat kehadiran ({{ $attendanceCount }} catatan) beserta foto bukti absen akan dihapus permanen dan tidak bisa dikembalikan. Lanjutkan?">
                        @csrf
                        @method('DELETE')
                        <button class="clean-item-btn blue" type="submit" @disabled($attendanceCount === 0)>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Hapus semua riwayat absen
                        </button>
                    </form>
                </div>

                {{-- 2. Hapus Sesi & Riwayat (Ungu) --}}
                <div class="clean-item-card purple">
                    <div>
                        <div class="clean-item-top">
                            <div class="clean-item-badge purple">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </div>
                            <span class="clean-item-arrow purple">&rarr;</span>
                        </div>
                        <h3 class="clean-item-title">Hapus Seluruh Sesi & Riwayat Absen</h3>
                        <p class="clean-item-desc">
                            Menghapus {{ $sessionCount }} sesi absen dan {{ $attendanceCount }} catatan kehadiran beserta foto buktinya. Jadwal waktu sholat tetap tersimpan.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('settings.clear-sessions') }}"
                        data-confirm="PERINGATAN: Seluruh sesi absen ({{ $sessionCount }} sesi) dan riwayat kehadiran ({{ $attendanceCount }} catatan) akan dihapus permanen. Jadwal waktu solat tetap aman. Lanjutkan?">
                        @csrf
                        @method('DELETE')
                        <button class="clean-item-btn purple" type="submit" @disabled($sessionCount === 0 && $attendanceCount === 0)>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Hapus semua sesi & riwayat
                        </button>
                    </form>
                </div>

                {{-- 3. Hapus Data Wajah (Teal/Emerald) --}}
                <div class="clean-item-card teal">
                    <div>
                        <div class="clean-item-top">
                            <div class="clean-item-badge teal">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                            </div>
                            <span class="clean-item-arrow teal">&rarr;</span>
                        </div>
                        <h3 class="clean-item-title">Hapus Seluruh Data Wajah</h3>
                        <p class="clean-item-desc">
                            Menghapus {{ $faceCount }} foto wajah terdaftar dan {{ $queueCount }} antrean foto, lalu membuka ulang status pendaftaran wajah semua akun.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('settings.clear-faces') }}"
                        data-confirm="PERINGATAN: Seluruh data dan foto wajah ({{ $faceCount }} foto) akan dihapus permanen. Semua anggota harus mendaftarkan wajah ulang. Lanjutkan?">
                        @csrf
                        @method('DELETE')
                        <button class="clean-item-btn teal" type="submit" @disabled($faceCount === 0 && $queueCount === 0)>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                            Hapus semua data wajah
                        </button>
                    </form>
                </div>

                {{-- 4. Hapus Murid & Petugas (Merah) --}}
                <div class="clean-item-card red">
                    <div>
                        <div class="clean-item-top">
                            <div class="clean-item-badge red">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="18" y1="8" x2="23" y2="13"></line>
                                    <line x1="23" y1="8" x2="18" y2="13"></line>
                                </svg>
                            </div>
                            <span class="clean-item-arrow red">&rarr;</span>
                        </div>
                        <h3 class="clean-item-title">Hapus Seluruh Murid & Petugas</h3>
                        <p class="clean-item-desc">
                            Menghapus {{ $memberCount }} akun murid dan petugas beserta foto wajah dan riwayat absen mereka. {{ $adminCount }} akun Administrator tidak ikut dihapus.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('settings.clear-members') }}"
                        data-confirm="PERINGATAN: Seluruh {{ $memberCount }} akun Murid dan Petugas beserta data wajah dan riwayat absennya akan dihapus permanen (akun Admin tetap aman). Lanjutkan?">
                        @csrf
                        @method('DELETE')
                        <button class="clean-item-btn red" type="submit" @disabled($memberCount === 0)>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Hapus semua murid & petugas
                        </button>
                    </form>
                </div>

                {{-- 5. Hapus Perangkat (Indigo) --}}
                <div class="clean-item-card indigo">
                    <div>
                        <div class="clean-item-top">
                            <div class="clean-item-badge indigo">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                    <line x1="8" y1="21" x2="16" y2="21"></line>
                                    <line x1="12" y1="17" x2="12" y2="21"></line>
                                </svg>
                            </div>
                            <span class="clean-item-arrow indigo">&rarr;</span>
                        </div>
                        <h3 class="clean-item-title">Hapus Seluruh Perangkat</h3>
                        <p class="clean-item-desc">
                            Menghapus {{ $deviceCount }} perangkat terdaftar sehingga perangkat harus didaftarkan ulang sebelum mengirim absen.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('settings.clear-devices') }}"
                        data-confirm="PERINGATAN: Seluruh {{ $deviceCount }} perangkat terdaftar akan dihapus permanen. Lanjutkan?">
                        @csrf
                        @method('DELETE')
                        <button class="clean-item-btn indigo" type="submit" @disabled($deviceCount === 0)>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Hapus semua perangkat
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- 3. Hapus Seluruh Data (Reset Total Sistem) --}}
        <section class="reset-section-card">
            <details open>
                <summary class="reset-summary">
                    <div class="reset-summary-left">
                        <div class="reset-icon-badge">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <polyline points="1 20 1 14 7 14"></polyline>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="reset-title">Hapus Seluruh Data (Reset Total Sistem)</h2>
                            <p class="reset-subtitle">Mengosongkan seluruh data operasional aplikasi sekaligus untuk memulai dari awal (misalnya pergantian tahun ajaran baru).</p>
                        </div>
                    </div>
                    <div class="reset-chevron">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="18 15 12 9 6 15"></polyline>
                        </svg>
                    </div>
                </summary>

                <div class="reset-body">
                    <div class="reset-warning-box">
                        <div class="reset-warning-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                <line x1="12" y1="9" x2="12" y2="13"></line>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                        </div>
                        <div>
                            <div class="reset-warning-title">Peringatan Keras: Tindakan Tidak Dapat Dibatalkan</div>
                            <p class="reset-warning-text">
                                Tindakan ini akan menghapus bersih seluruh riwayat kehadiran, seluruh sesi absen, seluruh foto dan sidik wajah, seluruh perangkat, serta seluruh akun murid dan petugas. Hanya akun Administrator dan jadwal waktu solat yang tetap dipertahankan.
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('settings.clear-all') }}"
                        data-confirm="PERINGATAN KERAS: Seluruh data operasional (riwayat absen, sesi, wajah, perangkat, serta akun murid & petugas) akan dihapus bersih secara permanen! Yakin ingin melanjutkan?">
                        @csrf
                        @method('DELETE')

                        <label for="confirm_text" style="display: block; font-size: 13px; font-weight: 600; color: #1e293b; margin-bottom: 8px;">
                            Ketik <strong>HAPUS SEMUA</strong> (huruf kapital) untuk membuka pengaman penghapusan total
                        </label>

                        <div class="reset-form-row">
                            <input class="reset-input" type="text" id="confirm_text" name="confirm_text"
                                placeholder="HAPUS SEMUA" autocomplete="off" required>

                            <button class="reset-submit-btn" type="submit">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                                Hapus seluruh data sekarang
                            </button>
                        </div>
                        @error('confirm_text')
                            <p class="field-error" style="margin-top: 6px;">{{ $message }}</p>
                        @enderror
                    </form>
                </div>
            </details>
        </section>

        {{-- 4. Cadangan & Pemulihan Sistem (Backup & Restore) --}}
        <section class="clean-section-card">
            <div class="clean-section-header">
                <div class="clean-header-title-row">
                    <div class="clean-header-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </div>
                    <div>
                        <h2 class="clean-header-title">Cadangan & Pemulihan Sistem (Backup & Restore)</h2>
                        <p class="clean-header-sub">Simpan seluruh data website ke dalam berkas .zip atau pulihkan data dari berkas cadangan.</p>
                    </div>
                </div>
            </div>

            <div class="backup-grid">
                {{-- Unduh Backup --}}
                <div class="backup-card">
                    <div>
                        <h3 class="backup-card-title">1. Simpan Cadangan Lengkap (Download Backup .zip)</h3>
                        <p class="backup-card-hint">
                            Satu klik untuk mengunduh seluruh isi basis data ({{ $memberCount + $adminCount }} akun, {{ $sessionCount }} sesi, {{ $attendanceCount }} riwayat absen, {{ $faceCount }} sidik wajah) sekaligus {{ $photoFileCount }} berkas foto anak-anak ({{ $formattedSize }}) dalam satu berkas <strong>.zip</strong>.
                        </p>
                    </div>
                    <div>
                        <a class="btn btn-primary" href="{{ route('settings.backup.download') }}" style="display: inline-flex; align-items: center; gap: 8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Unduh Backup Lengkap (.zip)
                        </a>
                    </div>
                </div>

                {{-- Pulihkan Backup --}}
                <div class="backup-card">
                    <div>
                        <h3 class="backup-card-title">2. Kembalikan Data dari Cadangan (Restore Backup .zip)</h3>
                        <p class="backup-card-hint">
                            Pindah server atau tidak sengaja menghapus data? Pilih berkas <strong>.zip</strong> hasil unduhan cadangan untuk mengembalikan seluruh database dan folder foto anak-anak persis seperti semula.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('settings.backup.restore') }}" enctype="multipart/form-data"
                        data-confirm="PERINGATAN: Memulihkan cadangan akan mengganti data dan foto saat ini dengan isi dari berkas .zip yang Anda pilih. Lanjutkan pemulihan data?">
                        @csrf

                        <div class="field" style="margin-bottom: 12px;">
                            <label class="field-label" for="backup_file" style="font-size: 13px;">Pilih Berkas Cadangan (.zip)</label>
                            <input class="input-field" type="file" id="backup_file" name="backup_file" accept=".zip,application/zip" required style="padding: 8px 12px;">
                            @error('backup_file')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field" style="margin-bottom: 14px;">
                            <label class="checkbox-row" for="confirm_restore" style="font-size: 12.5px; cursor: pointer; align-items: flex-start; gap: 8px;">
                                <input type="checkbox" id="confirm_restore" name="confirm_restore" value="1" required style="margin-top: 2px;">
                                <span>Saya mengerti data saat ini akan digantikan oleh data dari berkas cadangan ini.</span>
                            </label>
                            @error('confirm_restore')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <button class="btn btn-secondary" type="submit" style="display: inline-flex; align-items: center; gap: 8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="16 16 12 12 8 16"></polyline>
                                <line x1="12" y1="12" x2="12" y2="21"></line>
                                <path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"></path>
                                <polyline points="16 16 12 12 8 16"></polyline>
                            </svg>
                            Pulihkan Data Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </section>

        {{-- 5. Akun & Pengaturan Layar Monitor / Kiosk Masjid (/layar) --}}
        <section class="clean-section-card">
            <div class="clean-section-header" style="flex-wrap: wrap; gap: 12px;">
                <div class="clean-header-title-row">
                    <div class="clean-header-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                    </div>
                    <div>
                        <h2 class="clean-header-title">Akun & Akses Layar Monitor Masjid (/layar)</h2>
                        <p class="clean-header-sub">
                            Atur akun khusus peran <strong>Layar Monitor (Kiosk)</strong> untuk PC/TV di depan masjid. Akun ini hanya bisa membuka halaman <code>/layar</code> dan tidak memiliki akses ke menu admin.
                        </p>
                    </div>
                </div>

                <div>
                    <a class="btn btn-outline btn-small" href="{{ route('kiosk.index') }}" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                            <polyline points="15 3 21 3 21 9"></polyline>
                            <line x1="10" y1="14" x2="21" y2="3"></line>
                        </svg>
                        Buka Halaman /layar
                    </a>
                </div>
            </div>

            <form method="POST" action="{{ route('settings.kiosk-account') }}">
                @csrf
                @method('PUT')

                <div class="form-grid" style="margin-bottom: 16px;">
                    <div class="field">
                        <label class="field-label" for="kiosk_identifier">
                            Kode Login / Username Kiosk
                        </label>
                        <input class="input-field" type="text" id="kiosk_identifier" name="kiosk_identifier"
                            value="{{ old('kiosk_identifier', $kioskUser?->identifier_number ?? 'layar-masjid') }}" required>
                        <p class="field-hint">Dipakai pada kolom NISN / Email saat login di PC Masjid.</p>
                        @error('kiosk_identifier')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="kiosk_password">
                            {{ $kioskUser ? 'Sandi Baru Kiosk (Kosongkan jika tidak diubah)' : 'Sandi Awal Kiosk' }}
                        </label>
                        <input class="input-field" type="password" id="kiosk_password" name="kiosk_password"
                            placeholder="{{ $kioskUser ? 'Ketik sandi baru bila ingin mengganti...' : 'Minimal 6 karakter' }}"
                            @if (!$kioskUser) required @endif autocomplete="new-password">
                        <p class="field-hint">
                            @if ($kioskUser)
                                Status akun: <strong>Aktif ({{ $kioskUser->identifier_number }})</strong>. Isi hanya bila ingin mengganti sandi.
                            @else
                                Akun layar monitor belum dibuat. Isi sandi lalu klik Simpan untuk membuatnya.
                            @endif
                        </p>
                        @error('kiosk_password')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="kiosk_name">
                            Nama Tampilan Akun
                        </label>
                        <input class="input-field" type="text" id="kiosk_name" name="kiosk_name"
                            value="{{ old('kiosk_name', $kioskUser?->name ?? 'Layar Monitor Masjid') }}" required>
                        <p class="field-hint">Nama identitas untuk layar pemantau.</p>
                        @error('kiosk_name')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="kiosk_default_source">
                            Sumber Kamera Bawaan di /layar
                        </label>
                        <select class="select-field" id="kiosk_default_source" name="kiosk_default_source">
                            <option value="webcam" @selected(old('kiosk_default_source', $kioskDefaultSource) === 'webcam')>
                                Webcam PC / Monitor Itu Sendiri
                            </option>
                            @foreach ($kioskCameras as $cam)
                                <option value="cctv:{{ $cam->id }}" @selected(old('kiosk_default_source', $kioskDefaultSource) === 'cctv:' . $cam->id)>
                                    Kamera CCTV: {{ $cam->name }}{{ $cam->location ? ' (' . $cam->location . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="field-hint">Sumber kamera juga tetap bisa diganti langsung dari bar atas halaman <code>/layar</code>.</p>
                        @error('kiosk_default_source')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="form-actions" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <button class="btn btn-primary" type="submit">
                        {{ $kioskUser ? 'Simpan Pengaturan & Sandi Kiosk' : 'Buat Akun Layar Monitor Sekarang' }}
                    </button>
                </div>
            </form>
        </section>

        {{-- 6. Sensitivitas & Pengenalan Wajah AI --}}
        <section class="clean-section-card">
            <div class="clean-section-header">
                <div class="clean-header-title-row">
                    <div class="clean-header-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="clean-header-title">Sensitivitas & Pengenalan Wajah AI</h2>
                        <p class="clean-header-sub">Atur toleransi kecocokan wajah dan ukuran minimum piksel deteksi untuk CCTV dan kamera pemindai langsung.</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('settings.face-sensitivity') }}">
                @csrf
                @method('PUT')

                <div class="form-grid" style="margin-bottom: 16px;">
                    <div class="field">
                        <label class="field-label" for="face_cctv_tolerance">
                            Toleransi CCTV (Jarak Jauh)
                        </label>
                        <input class="input-field" type="number" step="0.01" min="0.5" max="2.0" id="face_cctv_tolerance" name="face_cctv_tolerance"
                            value="{{ old('face_cctv_tolerance', $faceCctvTolerance) }}" required>
                        <p class="field-hint">Standar rekomendasi: <strong>1.15</strong></p>
                        @error('face_cctv_tolerance')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="face_tolerance">
                            Toleransi Kamera Web / Petugas
                        </label>
                        <input class="input-field" type="number" step="0.01" min="0.5" max="2.0" id="face_tolerance" name="face_tolerance"
                            value="{{ old('face_tolerance', $faceTolerance) }}" required>
                        <p class="field-hint">Standar rekomendasi: <strong>1.15</strong></p>
                        @error('face_tolerance')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="face_cctv_min_face_size">
                            Ukuran Min. Wajah CCTV (px)
                        </label>
                        <input class="input-field" type="number" step="1" min="0" max="500" id="face_cctv_min_face_size" name="face_cctv_min_face_size"
                            value="{{ old('face_cctv_min_face_size', $faceCctvMinFaceSize) }}" required>
                        <p class="field-hint">Standar: <strong>40</strong> px (wajah kecil di kejauhan)</p>
                        @error('face_cctv_min_face_size')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="face_min_face_size">
                            Ukuran Min. Wajah Kamera Web (px)
                        </label>
                        <input class="input-field" type="number" step="1" min="0" max="500" id="face_min_face_size" name="face_min_face_size"
                            value="{{ old('face_min_face_size', $faceMinFaceSize) }}" required>
                        <p class="field-hint">Standar: <strong>80</strong> px (kamera jarak dekat)</p>
                        @error('face_min_face_size')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Dropdown Detail Panduan Sensitivitas --}}
                <details style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;">
                    <summary style="cursor: pointer; font-size: 13.5px; font-weight: 600; color: #2563eb; user-select: none;">
                        Lihat detail panduan & cara kerja angka sensitivitas
                    </summary>
                    <div style="margin-top: 10px; font-size: 13px; color: #475569; line-height: 1.6;">
                        <p style="margin-bottom: 8px;">
                            <strong>Cara kerja toleransi pencocokan:</strong> Sistem mengenali wajah dengan mengukur jarak Euclidean antara pola wajah jamaah di basis data dengan wajah yang ditangkap kamera. Semakin kecil angkanya, semakin identik wajah tersebut.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px;">
                            <div>
                                <strong>Angka lebih besar (misal 1.20 - 1.30):</strong> Pengenalan lebih fleksibel/mudah cocok. Cocok untuk CCTV sudut tinggi, pencahayaan redup, atau jamaah bergerak cepat.
                            </div>
                            <div>
                                <strong>Angka lebih kecil (misal 0.90 - 1.05):</strong> Pengenalan sangat ketat dan presisi tinggi guna menghindari salah deteksi.
                            </div>
                            <div>
                                <strong>Ukuran Minimum Wajah:</strong> Membatasi ukuran kotak wajah terkecil (piksel) yang akan diproses AI. Angka 40 px pada CCTV membuat jamaah di saf belakang tetap dapat terdeteksi.
                            </div>
                        </div>
                        <p style="margin: 0; color: #0f172a;">
                            Pengaturan yang disimpan di sini langsung dimuat oleh pemindai latar belakang (background worker) pada putaran berikutnya secara otomatis tanpa perlu restart container.
                        </p>
                    </div>
                </details>

                <div class="form-actions" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <button class="btn btn-primary" type="submit">
                        Simpan Sensitivitas Wajah
                    </button>
                    <button class="btn btn-outline" type="submit" name="reset_defaults" value="1" formnovalidate
                        data-confirm="Kembalikan semua nilai sensitivitas wajah ke rekomendasi standar bawaan sistem?">
                        Kembalikan ke Rekomendasi Bawaan
                    </button>
                </div>
            </form>
        </section>

        {{-- 6. Pengaturan Foto Bukti Absensi --}}
        <section class="clean-section-card">
            <div class="clean-section-header">
                <div class="clean-header-title-row">
                    <div class="clean-header-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                    </div>
                    <div>
                        <h2 class="clean-header-title">Foto Bukti Pemindaian Wajah</h2>
                        <p class="clean-header-sub">Atur apakah foto bukti absensi yang disimpan menyertakan kotak pendeteksi wajah dan label nama jamaah.</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('settings.proof-overlay') }}">
                @csrf
                @method('PUT')

                <div class="field" style="margin-bottom: 18px;">
                    <label class="checkbox-row" for="proof_box_overlay" style="cursor: pointer; align-items: flex-start; gap: 12px;">
                        <input type="checkbox" id="proof_box_overlay" name="proof_box_overlay" value="1" @checked($proofOverlayEnabled) style="margin-top: 3px; width: 18px; height: 18px; cursor: pointer;">
                        <div>
                            <strong style="display: block; font-size: 14px; color: #0f172a;">Sertakan kotak dan label nama pada foto bukti absen</strong>
                            <p class="field-hint" style="margin-top: 4px; color: #64748b; font-size: 12.5px; line-height: 1.5;">
                                Saat opsi ini aktif, setiap foto bukti yang disimpan akan dibubuhi kotak hijau serta label nama anggota terdaftar. Wajah orang lain yang belum terdaftar tidak akan digambari kotak agar nama jamaah tidak terhalang.
                            </p>
                        </div>
                    </label>
                </div>

                <div class="form-actions" style="margin-top: 14px;">
                    <button class="btn btn-primary" type="submit">
                        Simpan Pengaturan Foto Bukti
                    </button>
                </div>
            </form>
        </section>

    </div>
@endsection
