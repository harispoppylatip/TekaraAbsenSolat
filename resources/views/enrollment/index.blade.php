@extends('layouts.app')

@section('title', 'Pendaftaran Wajah')
@section('note', 'Daftarkan wajah Anda supaya bisa absen tanpa menempelkan jari')

@push('scripts')
    @vite('resources/js/enrollment.js')
@endpush

@section('content')
    <style>
        /* ========================================================
           Desain Modern Halaman Pendaftaran Wajah Jamaah / Siswa
           Sesuai Pedoman Rules.md (No dot indicators, clean UI)
           ======================================================== */
        .enroll-page-container {
            display: flex;
            flex-direction: column;
            gap: 22px;
            max-width: 940px;
            margin: 0 auto;
        }

        /* 1. Profil & Progress Banner */
        .enroll-profile-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .enroll-profile-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .enroll-user-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .enroll-user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 2px solid #dbeafe;
        }

        .enroll-user-name {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }

        .enroll-user-meta {
            font-size: 13px;
            color: #64748b;
            margin-top: 3px;
        }

        .enroll-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 600;
        }

        .enroll-status-badge.success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .enroll-status-badge.warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .enroll-status-badge.info {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        /* Bar Progress Wajah */
        .enroll-progress-wrap {
            margin-top: 20px;
        }

        .enroll-progress-labels {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 8px;
        }

        .enroll-progress-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .enroll-progress-percent {
            font-size: 14px;
            font-weight: 700;
            color: #2563eb;
        }

        .enroll-progress-bar-track {
            height: 10px;
            background: #f1f5f9;
            border-radius: 999px;
            overflow: hidden;
            position: relative;
        }

        .enroll-progress-bar-fill {
            height: 100%;
            background: #2563eb;
            border-radius: 999px;
            transition: width 0.4s ease;
        }

        .enroll-progress-note {
            font-size: 13px;
            color: #64748b;
            margin-top: 10px;
            line-height: 1.45;
        }

        /* 5 Kartu Status Pose */
        .enroll-poses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 16px;
        }

        .enroll-pose-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
        }

        .enroll-pose-card.saved {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .enroll-pose-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 6px;
            flex-shrink: 0;
            background: #e2e8f0;
            color: #64748b;
        }

        .enroll-pose-card.saved .enroll-pose-icon {
            background: #dcfce7;
            color: #16a34a;
        }

        .enroll-pose-info {
            min-width: 0;
            flex: 1;
        }

        .enroll-pose-num {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

        .enroll-pose-card.saved .enroll-pose-num {
            color: #15803d;
        }

        .enroll-pose-title {
            font-size: 12.5px;
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .enroll-pose-card.saved .enroll-pose-title {
            color: #166534;
        }

        /* 2. Kartu Panduan Terbuka/Tutup */
        .enroll-guide-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .enroll-guide-card summary {
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            cursor: pointer;
            list-style: none;
            user-select: none;
            background: #ffffff;
            transition: background 0.15s ease;
        }

        .enroll-guide-card summary::-webkit-details-marker {
            display: none;
        }

        .enroll-guide-card summary:hover {
            background: #f8fafc;
        }

        .enroll-guide-summary-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .enroll-guide-body {
            padding: 0 24px 22px 24px;
            border-top: 1px solid #f1f5f9;
            margin-top: 4px;
        }

        .enroll-tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .enroll-tip-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .enroll-tip-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #eff6ff;
            color: #2563eb;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .enroll-tip-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .enroll-tip-desc {
            font-size: 12.5px;
            color: #64748b;
            line-height: 1.4;
            margin: 0;
        }

        /* 3. Kartu Kamera Utama */
        .enroll-camera-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .enroll-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .enroll-card-subtitle {
            font-size: 13px;
            color: #64748b;
            margin-top: 3px;
        }

        .camera-viewport-box {
            max-width: 580px;
            margin: 18px auto 0 auto;
        }

        .camera-viewport-frame {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            background: #090d16;
            aspect-ratio: 4 / 3;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #cbd5e1;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        }

        .camera-viewport-frame video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .camera-viewport-frame .camera-placeholder {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 14px;
            gap: 10px;
            padding: 20px;
            text-align: center;
            pointer-events: none;
        }

        /* Kontrol Kamera */
        .camera-action-panel {
            max-width: 580px;
            margin: 16px auto 0 auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .camera-device-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 6px 14px;
        }

        .camera-device-label {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .camera-device-select {
            flex: 1;
            background: transparent;
            border: none;
            font-size: 13.5px;
            font-weight: 600;
            color: #0f172a;
            outline: none;
            cursor: pointer;
            padding: 4px 0;
        }

        .camera-buttons-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .camera-status-callout {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13px;
            color: #475569;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.45;
        }

        .camera-status-callout strong {
            color: #0f172a;
        }

        /* 4. Area Pratinjau Foto Siap Kirim */
        .enroll-staging-box {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .enroll-staging-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .enroll-staging-title {
            font-size: 14.5px;
            font-weight: 700;
            color: #0f172a;
        }

        .enroll-staging-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 12px;
        }

        .shot-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.05);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .shot-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }

        .shot-image {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 8px;
            display: block;
            background: #f1f5f9;
        }

        .enroll-staging-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        /* Spinner animasi */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .spin-icon {
            animation: spin 1s linear infinite;
        }

        /* Responsive Breakpoints */
        @media (max-width: 640px) {
            .enroll-profile-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .enroll-buttons-bar {
                flex-direction: column;
                width: 100%;
            }

            .enroll-buttons-bar .btn {
                width: 100%;
                justify-content: center;
            }

            .enroll-staging-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>

    @php
        $kurang = max(0, $state['required'] - $state['usable']);
        $sisaJatah = max(0, $state['max'] - $state['total']);
        $terkunci = $state['locked'];
        $savedByPose = $state['saved_by_pose'] ?? [];
        $missingPoses = $state['missing_poses'] ?? [];
        $missingLabels = collect($missingPoses)->pluck('short_label')->join(', ');
    @endphp

    <div class="enroll-page-container">

        {{-- 1. Kartu Profil & Kemajuan Pendaftaran Wajah --}}
        <div data-live="enrollment-header">
            <section class="enroll-profile-card">
                <div class="enroll-profile-header">
                    <div class="enroll-user-info">
                        <div class="enroll-user-avatar">
                            {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="enroll-user-name">{{ $user->name }}</div>
                            <div class="enroll-user-meta">
                                {{ $user->identifier_number ? 'Nomor Induk: ' . $user->identifier_number : 'Tanpa NIS/NIP' }}
                                {{ $user->class_name ? ' · Kelas ' . $user->class_name : '' }}
                                · {{ $user->roleLabel() }}
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        @if ($terkunci)
                            <span class="enroll-status-badge success">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                Pendaftaran Selesai & Terkunci
                            </span>
                        @elseif ($state['finished'])
                            <span class="enroll-status-badge success">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                5 Sudut Wajah Lengkap
                            </span>
                        @elseif ($state['pending'])
                            <span class="enroll-status-badge info">
                                <svg class="spin-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                Memeriksa Foto...
                            </span>
                        @else
                            <span class="enroll-status-badge warning">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                Kurang {{ $kurang }} Foto
                            </span>
                        @endif

                        <a class="btn btn-outline btn-small" href="{{ route($user->homeRoute()) }}">Dashboard</a>
                    </div>
                </div>

                {{-- Bar Progress --}}
                <div class="enroll-progress-wrap">
                    <div class="enroll-progress-labels">
                        <span class="enroll-progress-title">{{ $state['usable'] }} dari {{ $state['required'] }} Sudut Wajah Terverifikasi</span>
                        <span class="enroll-progress-percent">{{ $state['percent'] }}%</span>
                    </div>
                    <div class="enroll-progress-bar-track">
                        <div class="enroll-progress-bar-fill" style="width: {{ $state['percent'] }}%"></div>
                    </div>
                    <p class="enroll-progress-note">
                        @if ($terkunci)
                            Semua foto yang dibutuhkan sudah terkumpul dan pendaftaran Anda sudah aktif untuk presensi kehadiran.
                        @elseif ($state['finished'])
                            Semua sudut foto wajah sudah terverifikasi. Periksa kembali galeri foto Anda di bawah, lalu kunci pendaftaran.
                        @else
                            Dibutuhkan {{ $kurang }} foto lagi agar pendaftaran wajah Anda lengkap dan siap dipakai absen mandiri.
                        @endif
                    </p>
                </div>

                {{-- Status 5 Pose Terpandu --}}
                <div class="enroll-poses-grid">
                    @foreach ($variations as $index => $variasi)
                        @php
                            $poseNumber = $index + 1;
                            $isSaved = isset($savedByPose[$poseNumber]);
                        @endphp
                        <div class="enroll-pose-card @if($isSaved) saved @endif">
                            <span class="enroll-pose-icon">
                                @if ($isSaved)
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                @else
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="12"></line>
                                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                    </svg>
                                @endif
                            </span>
                            <div class="enroll-pose-info">
                                <div class="enroll-pose-num">Pose {{ $poseNumber }} · {{ $isSaved ? 'Tersimpan' : 'Belum' }}</div>
                                <div class="enroll-pose-title">{{ $variasi['label'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($state['unusable'] > 0)
                    <div class="alert alert-warning" style="margin-top: 16px;">
                        <strong>Ada {{ $state['unusable'] }} foto lama yang tidak dapat dipakai</strong>
                        <p>Foto tersebut direkam dengan mesin model AI versi sebelumnya. Silakan ambil foto baru hingga lengkap 5 pose.</p>
                    </div>
                @endif

                @if ($state['pending'])
                    <div class="alert alert-info" style="margin-top: 16px;" data-shoot-busy>
                        <strong>Foto Anda sedang diproses oleh AI</strong>
                        <p>Pemeriksaan fitur wajah memerlukan beberapa detik di server. Hasilnya akan otomatis muncul pada bagian kemajuan di bawah.</p>
                    </div>
                @endif
            </section>
        </div>

        {{-- 2. Panduan Variasi Pose & Tips (Accordion Rapi) --}}
        <details class="enroll-guide-card">
            <summary>
                <div class="enroll-guide-summary-left">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <div>
                        <span class="enroll-card-title">Tips & Panduan Pemotretan Wajah</span>
                        <div class="enroll-card-subtitle">Panduan pencahayaan dan posisi wajah agar mudah diverifikasi AI</div>
                    </div>
                </div>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </summary>

            <div class="enroll-guide-body">
                <div class="enroll-tips-grid">
                    <div class="enroll-tip-item">
                        <span class="enroll-tip-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="5"></circle>
                                <line x1="12" y1="1" x2="12" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="23"></line>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                <line x1="1" y1="12" x2="3" y2="12"></line>
                                <line x1="21" y1="12" x2="23" y2="12"></line>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                            </svg>
                        </span>
                        <div>
                            <div class="enroll-tip-title">Cahaya Terang Merata</div>
                            <p class="enroll-tip-desc">Hadap sumber cahaya langsung dari depan. Hindari membelakangi lampu atau jendela.</p>
                        </div>
                    </div>

                    <div class="enroll-tip-item">
                        <span class="enroll-tip-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                <line x1="15" y1="9" x2="15.01" y2="9"></line>
                            </svg>
                        </span>
                        <div>
                            <div class="enroll-tip-title">Wajah Terbuka</div>
                            <p class="enroll-tip-desc">Lepas masker, kacamata hitam, atau topi agar seluruh struktur wajah terbaca jelas.</p>
                        </div>
                    </div>

                    <div class="enroll-tip-item">
                        <span class="enroll-tip-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <div>
                            <div class="enroll-tip-title">Sendiri di Layar</div>
                            <p class="enroll-tip-desc">Pastikan hanya ada satu wajah Anda di dalam bingkai kamera saat pemotretan berlangsung.</p>
                        </div>
                    </div>
                </div>
            </div>
        </details>

        {{-- 3. Area Kamera & Pemotretan Otomatis --}}
        @if (! $terkunci)
            <section class="enroll-camera-card" data-shoot-card @if ($state['usable'] >= $state['required']) style="display: none;" @endif>
                <div>
                    <h2 class="enroll-card-title">Ambil Foto Wajah Mandiri</h2>
                    <p class="enroll-card-subtitle">
                        Nyalakan kamera, lalu klik tombol pemotretan otomatis. Sistem akan menghitung mundur 3 detik untuk setiap sudut wajah secara terpadu.
                    </p>
                </div>

                @if ($sisaJatah === 0)
                    <div class="alert alert-warning" style="margin-top: 14px;">
                        <strong>Batas foto maksimal tercapai</strong>
                        <p>Akun Anda sudah memiliki {{ $state['total'] }} foto. Hapus sebagian foto di bawah jika ingin mengambil foto baru.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('enrollment.photos.store') }}" enctype="multipart/form-data"
                    data-enrollment-form data-max-submit="{{ $state['max_per_submit'] }}"
                    data-progress-url="{{ route('enrollment.progress') }}" style="margin-top: 14px;">
                    @csrf
                    <input type="hidden" name="replace" value="0">
                    <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-shoot-input hidden>

                    <div data-shoot="{{ $state['max_per_submit'] }}"
                        data-needed="{{ $kurang }}"
                        data-required="{{ $state['required'] }}"
                        data-usable="{{ $state['usable'] }}"
                        data-missing-poses="{{ json_encode($missingPoses) }}">

                        {{-- Frame Kamera --}}
                        <div class="camera-viewport-box">
                            <div class="camera-viewport-frame">
                                <video data-shoot-video playsinline muted></video>

                                <div class="camera-placeholder" data-shoot-placeholder>
                                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.5">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                        <circle cx="12" cy="13" r="4"></circle>
                                    </svg>
                                    <div>Kamera belum dinyalakan.<br>Klik tombol <strong>Nyalakan Kamera</strong> di bawah.</div>
                                </div>

                                {{-- Panduan Wajah dan Hitung Mundur Otomatis --}}
                                <div class="camera-guide-overlay" data-shoot-guide hidden>
                                    <div class="camera-guide-oval"></div>
                                    <div class="camera-guide-prompt" data-shoot-guide-prompt>
                                        <span class="guide-step" data-shoot-guide-step>1/5</span>
                                        <span class="guide-text" data-shoot-guide-text>Hadap lurus ke kamera</span>
                                    </div>
                                    <div class="camera-guide-countdown" data-shoot-guide-countdown hidden>3</div>
                                </div>

                                <div class="camera-flash" data-shoot-flash hidden></div>
                            </div>
                        </div>

                        {{-- Panel Kontrol Kamera --}}
                        <div class="camera-action-panel">
                            {{-- Pilihan Kamera Depan / Belakang --}}
                            <div class="camera-device-bar">
                                <span class="camera-device-label">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                        <circle cx="12" cy="13" r="4"></circle>
                                    </svg>
                                    Pilih Kamera:
                                </span>
                                <select class="camera-device-select" id="enrollment-facing" data-shoot-facing>
                                    <option value="user">Kamera Depan (Selfie)</option>
                                    <option value="environment">Kamera Belakang</option>
                                </select>
                            </div>

                            {{-- Tombol Aksi Kamera (Fitur Manual Dihapus) --}}
                            <div class="camera-buttons-bar">
                                <button class="btn btn-secondary" type="button" data-shoot-start style="display: inline-flex; align-items: center; gap: 6px;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polygon points="10 8 16 12 10 16 10 8"></polygon>
                                    </svg>
                                    Nyalakan Kamera
                                </button>

                                <button class="btn btn-primary" type="button" data-shoot-auto disabled style="display: inline-flex; align-items: center; gap: 6px; flex: 1; justify-content: center;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                        <circle cx="12" cy="13" r="4"></circle>
                                    </svg>
                                    {{ $kurang === 5 ? 'Mulai Foto Otomatis (5 Sudut)' : ($kurang > 0 ? "Mulai Foto Otomatis ({$kurang} Foto Lagi)" : 'Foto Wajah Lengkap') }}
                                </button>

                                <button class="btn btn-outline btn-small" type="button" data-shoot-stop disabled style="display: inline-flex; align-items: center; gap: 4px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    </svg>
                                    Matikan
                                </button>
                            </div>

                            {{-- Keterangan Status Kamera --}}
                            <div class="camera-status-callout">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="flex-shrink: 0; margin-top: 1px;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="16" x2="12" y2="12"></line>
                                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                </svg>
                                <span data-shoot-status>Nyalakan kamera, posisikan wajah di dalam oval panduan, lalu tekan Mulai Foto Otomatis.</span>
                            </div>

                            <div class="alert alert-warning" data-shoot-blocked hidden>
                                <strong>Kamera tidak dapat diakses di peramban ini</strong>
                                <p>Pastikan izin akses kamera telah diberikan dan situs diakses via HTTPS aman.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Area Pratinjau Foto Hasil Pemotretan --}}
                    <div class="enroll-staging-box">
                        <div class="enroll-staging-head">
                            <div>
                                <span class="enroll-staging-title">Foto Siap Dikirim</span>
                                <div class="enroll-card-subtitle" data-shoot-counter>Belum ada foto yang siap dikirim.</div>
                            </div>
                            <div class="field-hint" data-shoot-note style="margin: 0;"></div>
                        </div>

                        <div class="enroll-staging-grid" data-shoot-preview></div>

                        <div class="enroll-staging-actions">
                            <button class="btn btn-primary" type="submit" data-shoot-submit disabled style="display: inline-flex; align-items: center; gap: 8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                                Kirim Foto untuk Diperiksa
                            </button>
                            <a class="btn btn-text" href="{{ route('enrollment.show') }}" style="color: #64748b;">Bersihkan Pilihan</a>
                        </div>
                    </div>
                </form>
            </section>
        @endif

        {{-- 4. Wilayah Dinamis Kemajuan Pemeriksaan & Galeri Wajah (Live Dynamic Region) --}}
        <div id="kemajuan-pemeriksaan" data-live="enrollment">
            @include('enrollment.partials.progress')
        </div>

    </div>
@endsection
