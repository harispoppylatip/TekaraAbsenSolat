{{--
    Kemajuan pemeriksaan foto milik sendiri.
    Markahnya dicerminkan oleh wilayah `data-live="enrollment"` pada halaman
    pendaftaran, sehingga hasil pemeriksaan muncul tanpa memuat ulang halaman.
--}}
@php
    $jumlah = $state['usable'];
    $wajib = $state['required'];
    $tersimpan = $user->faceEmbeddings()->orderByDesc('id')->get();
    $banyakTersimpan = $tersimpan->count();
    $terkunci = $state['locked'];
@endphp

<style>
    /* Styling Komponen Kemajuan Pemeriksaan & Galeri */
    .progress-section-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        margin-top: 20px;
    }

    .progress-queue-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 20px;
        margin-top: 14px;
        transition: opacity 0.4s ease;
    }

    .progress-queue-card[data-tone="berjalan"] {
        border-left: 4px solid #2563eb;
    }

    .progress-queue-card[data-tone="selesai"] {
        border-left: 4px solid #16a34a;
    }

    .progress-queue-card[data-tone="sebagian"] {
        border-left: 4px solid #ea580c;
    }

    .saved-photos-accordion summary::-webkit-details-marker {
        display: none;
    }

    .saved-photos-accordion[open] .toggle-arrow {
        transform: rotate(180deg);
    }

    .saved-photos-accordion[open] .toggle-text-open {
        display: none !important;
    }

    .saved-photos-accordion[open] .toggle-text-close {
        display: inline !important;
    }

    .saved-photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 14px;
        margin-top: 16px;
    }

    .saved-photo-item {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .saved-photo-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.07);
    }

    .saved-photo-thumb-wrap {
        position: relative;
        width: 100%;
        aspect-ratio: 1 / 1;
        border-radius: 8px;
        overflow: hidden;
        background: #0f172a;
    }

    .saved-photo-thumb-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .saved-photo-pose-badge {
        position: absolute;
        bottom: 6px;
        left: 6px;
        background: rgba(15, 23, 42, 0.85);
        color: #ffffff;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 4px;
        backdrop-filter: blur(2px);
        white-space: nowrap;
        max-width: calc(100% - 12px);
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .saved-photo-meta {
        font-size: 11px;
        color: #64748b;
        line-height: 1.3;
    }

    .enroll-complete-banner {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 16px;
        padding: 24px;
        margin-top: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
    }

    .enroll-complete-left {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        flex: 1;
        min-width: 260px;
    }

    .enroll-complete-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #dcfce7;
        color: #16a34a;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
</style>

{{-- 1. Kartu Antrean Pemeriksaan Foto yang Sedang Berjalan / Baru Selesai --}}
@if ($state['batches'] !== [])
    <section class="progress-section-card" data-progress-card>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap;">
            <div>
                <h2 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0;">Kemajuan Pemeriksaan AI</h2>
                <p style="font-size: 13px; color: #64748b; margin-top: 3px; margin-bottom: 0;">
                    @if ($state['pending'])
                        Foto sedang diperiksa oleh sistem AI. Layar akan diperbarui secara otomatis.
                    @elseif ($jumlah >= $wajib)
                        Seluruh foto wajib telah lolos verifikasi AI.
                    @else
                        {{ $jumlah }} dari {{ $wajib }} foto telah lolos verifikasi.
                    @endif
                </p>
            </div>
        </div>

        @foreach ($state['batches'] as $batch)
            @php
                $tone = $batch['done'] ? ($batch['failed'] > 0 ? 'sebagian' : 'selesai') : 'berjalan';
            @endphp

            <article class="progress-queue-card" data-batch="{{ $batch['id'] }}" data-tone="{{ $tone }}">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <div>
                        <strong style="font-size: 14px; color: #0f172a;">{{ $batch['total'] }} Foto Dikirim</strong>
                        <div style="font-size: 12.5px; color: #64748b; margin-top: 2px;">
                            {{ $batch['finished'] }} dari {{ $batch['total'] }} foto selesai diproses
                        </div>
                    </div>
                    <span style="font-size: 16px; font-weight: 800; color: #2563eb;" data-batch-percent>{{ $batch['percent'] }}%</span>
                </div>

                @if ($batch['stalled'])
                    <div class="alert alert-warning" style="margin-bottom: 10px; font-size: 12.5px; padding: 8px 12px;" data-batch-alert>
                        Pekerja antrean di server sedang sibuk atau belum aktif. Mohon tunggu sejenak.
                    </div>
                @endif

                <div class="progress-track" style="height: 8px; border-radius: 999px; background: #f1f5f9; overflow: hidden; margin-bottom: 12px;">
                    <div class="progress-fill" data-batch-bar style="width: {{ $batch['percent'] }}%; height: 100%; background: #2563eb; border-radius: 999px; transition: width 0.3s ease;"></div>
                </div>

                <details class="record-card" data-record="batch-{{ $batch['id'] }}" @if ($batch['failed'] > 0) open @endif style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px;">
                    <summary style="font-size: 13px; font-weight: 600; color: #334155; cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                        <span>
                            Rincian Verifikasi
                            <span style="font-weight: 400; color: #64748b; margin-left: 6px;">
                                ({{ $batch['saved'] }} diterima @if ($batch['failed'] > 0), {{ $batch['failed'] }} ditolak @endif)
                            </span>
                        </span>
                        <span style="font-size: 12px; color: #2563eb;">Lihat Detail</span>
                    </summary>

                    <div style="margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 8px; display: flex; flex-direction: column; gap: 8px;">
                        @foreach ($batch['rows'] as $row)
                            <div class="queue-row" data-row="{{ $row['id'] }}" data-tone="{{ $row['status'] }}" style="font-size: 12.5px; display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px dashed #f1f5f9;">
                                <div>
                                    <strong style="color: #0f172a;">{{ $row['pose_label'] ?? \App\Support\FacePose::humanLabel($row['name']) }}</strong>
                                    <span style="color: #94a3b8; font-size: 11px; margin-left: 4px;">({{ $row['name'] }})</span>
                                    <div style="font-size: 11.5px; color: #64748b;">{{ $row['note'] }}</div>
                                </div>
                                <div>
                                    @if ($row['status'] === 'saved')
                                        <span style="background: #dcfce7; color: #166534; font-size: 11.5px; font-weight: 600; padding: 2px 8px; border-radius: 6px;">Lolos</span>
                                    @elseif ($row['status'] === 'failed')
                                        <span style="background: #fee2e2; color: #991b1b; font-size: 11.5px; font-weight: 600; padding: 2px 8px; border-radius: 6px;">Ditolak</span>
                                    @else
                                        <span style="background: #eff6ff; color: #1e40af; font-size: 11.5px; font-weight: 600; padding: 2px 8px; border-radius: 6px;">Memproses</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </details>

                @if ($batch['retryable'] > 0)
                    <form method="POST" action="{{ route('enrollment.retry', $batch['id']) }}" style="margin-top: 10px;">
                        @csrf
                        <button class="btn btn-small btn-secondary" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
                            </svg>
                            Periksa Ulang {{ $batch['retryable'] }} Foto Gagal
                        </button>
                    </form>
                @endif
            </article>
        @endforeach
    </section>
@endif

{{-- 2. Galeri Foto Wajah Tersimpan (Tertutup secara bawaan) --}}
@if ($state['has_photos'])
    <details class="progress-section-card saved-photos-accordion" data-record="foto-wajah-tersimpan" style="padding: 20px 24px;">
        <summary style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; list-style: none; user-select: none;">
            <div>
                <h2 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0;">Foto Wajah yang Tersimpan ({{ $banyakTersimpan }} Foto)</h2>
                <div style="font-size: 13px; color: #64748b; margin-top: 3px;">
                    @if ($terkunci)
                        Pendaftaran telah selesai dan terkunci untuk absensi.
                    @elseif ($state['usable'] >= $state['required'])
                        5 sudut foto wajah sudah lengkap. Tekan lihat detail untuk membuka galeri.
                    @else
                        Kumpulan foto wajah yang telah lolos verifikasi AI untuk akun Anda.
                    @endif
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 6px; color: #2563eb; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 8px; background: #eff6ff;">
                <span class="toggle-text-open">Lihat Detail</span>
                <span class="toggle-text-close" style="display: none;">Tutup</span>
                <svg class="toggle-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="transition: transform 0.2s ease;">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>
        </summary>

        <div style="margin-top: 16px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
            <div class="saved-photo-grid">
                @foreach ($tersimpan as $index => $wajah)
                    @php
                        $poseLabel = \App\Support\FacePose::humanLabel($wajah->label, $banyakTersimpan - $index);
                    @endphp
                    <div class="saved-photo-item">
                        <div class="saved-photo-thumb-wrap">
                            @if ($wajah->photo_path)
                                <a href="{{ route('enrollment.photos.show', $wajah) }}" target="_blank" rel="noopener" title="Lihat Foto Ukuran Penuh">
                                    <img src="{{ route('enrollment.photos.show', $wajah) }}" alt="{{ $poseLabel }}" loading="lazy">
                                </a>
                            @else
                                <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #64748b; font-size: 12px;">Tanpa Foto</div>
                            @endif
                            <span class="saved-photo-pose-badge">{{ $poseLabel }}</span>
                        </div>

                        <div class="saved-photo-meta">
                            Diterima: {{ $wajah->created_at?->translatedFormat('d M Y, H:i') ?? '-' }}
                        </div>

                        @unless ($terkunci)
                            <form method="POST" action="{{ route('enrollment.photos.destroy', $wajah) }}" style="margin-top: 2px;">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-small btn-danger" type="submit" style="width: 100%; padding: 4px 8px; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;"
                                    data-confirm="Hapus foto {{ $poseLabel }} ini? Anda perlu mengambil foto sudut ini kembali.">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                    Hapus
                                </button>
                            </form>
                        @endunless
                    </div>
                @endforeach
            </div>

            @unless ($terkunci)
                <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end;">
                    <form method="POST" action="{{ route('enrollment.photos.clear') }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-small btn-outline" type="submit" style="color: #dc2626; border-color: #fca5a5; display: inline-flex; align-items: center; gap: 6px;"
                            data-confirm="Hapus seluruh foto wajah yang tersimpan di akun Anda dan mulai dari awal?">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Hapus Seluruh Foto Saya
                        </button>
                    </form>
                </div>
            @endunless
        </div>
    </details>
@endif

{{-- 3. Banner & Form Kunci Pendaftaran Ketika 5 Foto Lengkap --}}
@if (! $terkunci && $state['finished'])
    <section class="enroll-complete-banner">
        <div class="enroll-complete-left">
            <div class="enroll-complete-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <div>
                <h3 style="font-size: 16px; font-weight: 700; color: #166534; margin: 0;">Foto Wajah Sudah Lengkap ({{ $state['usable'] }} dari {{ $state['required'] }} Foto)</h3>
                <p style="font-size: 13px; color: #15803d; margin: 4px 0 0 0; line-height: 1.45;">
                    Semua 5 sudut foto wajah telah lolos verifikasi AI. Tekan tombol di samping untuk mengunci pendaftaran agar wajah Anda aktif digunakan untuk presensi.
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('enrollment.complete') }}"
            data-confirm="Pastikan foto wajah Anda sudah jelas. Setelah dikunci, foto tidak dapat diubah kecuali petugas membukakan pendaftaran ulang. Selesaikan pendaftaran sekarang?">
            @csrf
            <button class="btn btn-primary" type="submit" style="background: #16a34a; border-color: #15803d; display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; font-size: 14px; font-weight: 700;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Kirim & Selesaikan Pendaftaran
            </button>
        </form>
    </section>
@endif
