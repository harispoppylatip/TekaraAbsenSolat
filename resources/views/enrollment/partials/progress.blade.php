{{--
    Kemajuan pemeriksaan foto milik sendiri.

    Markahnya dicerminkan oleh wilayah `data-live="enrollment"` pada halaman
    pendaftaran, sehingga hasil pemeriksaan muncul tanpa memuat ulang halaman.

    Foto dilaporkan satu per satu, bukan hanya jumlahnya, sebab yang menentukan
    pemiliknya bisa melanjutkan atau tidak adalah alasan tiap foto diterima atau
    ditolak.
--}}
@php
    $jumlah = $state['usable'];
    $wajib = $state['required'];
    $tersimpan = $user->faceEmbeddings()->orderByDesc('id')->get();
    $banyakTersimpan = $tersimpan->count();
    $terkunci = $state['locked'];
@endphp

@if ($state['batches'] !== [])
    <section class="card" data-progress-card>
        <div class="section-head">
            <div>
                <h2 class="section-title">Kemajuan pemeriksaan foto</h2>
                <p class="section-note">
                    @if ($state['pending'])
                        Ada foto yang masih diperiksa. Bagian ini akan berubah sendiri setelah pemeriksaannya
                        selesai.
                    @elseif ($jumlah >= $wajib)
                        Semua foto yang dibutuhkan sudah lolos pemeriksaan.
                    @else
                        {{ $jumlah }} dari {{ $wajib }} foto sudah lolos pemeriksaan.
                    @endif
                </p>
            </div>
        </div>

        @foreach ($state['batches'] as $batch)
            @php
                $tone = $batch['done'] ? ($batch['failed'] > 0 ? 'sebagian' : 'selesai') : 'berjalan';
            @endphp

            <article class="queue-item" data-batch="{{ $batch['id'] }}" data-tone="{{ $tone }}">
                <div class="queue-head">
                    <div>
                        <p class="queue-title">{{ $batch['total'] }} foto yang Anda kirim</p>
                        <p class="queue-note">{{ $batch['finished'] }} dari {{ $batch['total'] }} foto selesai
                            diperiksa.</p>
                    </div>
                    <p class="queue-value" data-batch-percent>{{ $batch['percent'] }}%</p>
                </div>

                @if ($batch['stalled'])
                    <p class="queue-note queue-alert" data-batch-alert>
                        Pemeriksaan foto belum dijalankan di server. Beri tahu petugas sekolah bahwa pekerja
                        antrean wajah belum menyala.
                    </p>
                @endif

                <p class="queue-note" data-batch-note>{{ $batch['note'] }}</p>

                <div class="progress-track">
                    <div class="progress-fill" data-batch-bar style="width: {{ $batch['percent'] }}%"></div>
                </div>

                {{--
                    Daftar ini dilipat supaya halaman tetap pendek, tetapi
                    dibuka sendiri saat ada foto yang gagal supaya alasannya
                    langsung terbaca tanpa perlu diklik.
                --}}
                <details class="record-card" data-record="batch-{{ $batch['id'] }}"
                    @if ($batch['failed'] > 0) open @endif>
                    <summary>
                        <span class="record-card-name">Rincian setiap foto</span>
                        <span class="record-card-note">
                            {{ $batch['saved'] }} diterima
                            @if ($batch['failed'] > 0)
                                , {{ $batch['failed'] }} ditolak
                            @endif
                        </span>
                        <span class="record-card-hint">
                            <span class="record-card-open">Lihat detail</span>
                            <span class="record-card-close">Tutup</span>
                        </span>
                    </summary>

                    <div class="record-card-body">
                        <div class="queue-rows">
                            @foreach ($batch['rows'] as $row)
                                <div class="queue-row" data-row="{{ $row['id'] }}"
                                    data-tone="{{ $row['status'] }}">
                                    <p class="queue-file">
                                        <strong>{{ $row['pose_label'] ?? \App\Support\FacePose::humanLabel($row['name']) }}</strong>
                                        <span class="cell-note">({{ $row['name'] }})</span>
                                    </p>
                                    <p class="queue-note">{{ $row['note'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </details>

                @if ($batch['retryable'] > 0)
                    <form method="POST" action="{{ route('enrollment.retry', $batch['id']) }}">
                        @csrf
                        <button class="btn btn-small btn-secondary" type="submit">Periksa ulang
                            {{ $batch['retryable'] }} foto</button>
                    </form>
                @endif
            </article>
        @endforeach
    </section>
@endif

@if ($state['has_photos'])
    <details class="card fold-card" data-record="foto-wajah-tersimpan" @if ($state['usable'] < $state['required']) open @endif>
        <summary>
            <div>
                <h2 class="section-title">Foto wajah yang tersimpan</h2>
                <p class="section-note">
                    {{ $banyakTersimpan }} foto tersimpan di akun Anda.
                    @if ($terkunci)
                        Pendaftaran sudah selesai dan terkunci.
                    @elseif ($state['usable'] >= $state['required'])
                        Foto sudah lengkap. Buka untuk memeriksa atau menghapus foto.
                    @else
                        Periksa kembali foto wajah Anda agar jelas dan bagus sebelum menyelesaikan pendaftaran.
                    @endif
                </p>
            </div>
            <span class="fold-card-hint">
                <span class="fold-card-open">Lihat foto tersimpan</span>
                <span class="fold-card-close">Tutup foto tersimpan</span>
            </span>
        </summary>

        <div style="margin-top: 14px;">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Foto ke</th>
                            <th>Pose Wajah</th>
                            <th>Foto</th>
                            <th>Diterima pada</th>
                            @unless ($terkunci)
                                <th>Aksi</th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tersimpan as $index => $wajah)
                            @php
                                $poseLabel = \App\Support\FacePose::humanLabel($wajah->label, $banyakTersimpan - $index);
                            @endphp
                            <tr>
                                <td>{{ $banyakTersimpan - $index }}</td>
                                <td>
                                    <strong>{{ $poseLabel }}</strong>
                                </td>
                                <td>
                                    @if ($wajah->photo_path)
                                        <a class="proof-link" href="{{ route('enrollment.photos.show', $wajah) }}"
                                            target="_blank" rel="noopener" title="Lihat foto wajah">
                                            <img class="proof-thumb" src="{{ route('enrollment.photos.show', $wajah) }}"
                                                alt="Foto ke-{{ $banyakTersimpan - $index }}" width="56" height="56"
                                                loading="lazy" decoding="async">
                                        </a>
                                    @else
                                        <span class="cell-note">Tanpa foto</span>
                                    @endif
                                </td>
                                <td>{{ $wajah->created_at?->translatedFormat('d M Y, H:i') ?? 'tidak diketahui' }}</td>
                                @unless ($terkunci)
                                    <td>
                                        <form method="POST" action="{{ route('enrollment.photos.destroy', $wajah) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-small btn-danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                @endunless
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @unless ($terkunci)
                <form method="POST" action="{{ route('enrollment.photos.clear') }}" style="margin-top: 14px;">
                    @csrf
                    @method('DELETE')
                    <div class="form-actions">
                        <button class="btn btn-outline" type="submit"
                            data-confirm="Hapus seluruh foto wajah akun Anda? Anda perlu mendaftar ulang dari awal.">
                            Hapus seluruh foto saya
                        </button>
                    </div>
                </form>
            @endunless
        </div>
    </details>
@endif

@if (! $terkunci && $state['finished'])
    <section class="card" style="margin-top: 16px;">
        <div class="section-head">
            <div>
                <h2 class="section-title">Foto wajah sudah lengkap ({{ $state['usable'] }} dari {{ $state['required'] }} foto)</h2>
                <p class="section-note">
                    Periksa kembali foto wajah Anda pada tabel di atas agar jelas dan benar. Jika sudah sesuai, tekan tombol di bawah untuk mengirim dan mengunci pendaftaran.
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('enrollment.complete') }}"
            data-confirm="Pastikan wajah jelas dan benar karena ini akan terkunci jika sudah terkunci tidak bisa diubah kecuali admin membuka. Selesaikan pendaftaran?">
            @csrf
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">
                    Kirim dan selesaikan pendaftaran
                </button>
            </div>
        </form>
    </section>
@endif
