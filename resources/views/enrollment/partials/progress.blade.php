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

<section class="card">
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

    <div class="progress-block">
        <p class="progress-value">{{ $jumlah }} dari {{ $wajib }} foto</p>
        <div class="progress-track">
            <div class="progress-fill" style="width: {{ $state['percent'] }}%"></div>
        </div>
        @if ($state['unusable'] > 0)
            <p class="progress-note">
                {{ $state['unusable'] }} foto lama tidak dihitung karena dihitung mesin versi sebelumnya.
            </p>
        @endif
    </div>

    @if ($state['batches'] === [])
        <p class="empty-state">Belum ada foto yang dikirim dari halaman ini.</p>
    @else
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
                                    <p class="queue-file">{{ $row['name'] }}</p>
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
    @endif
</section>

@if ($state['has_photos'])
    <section class="card">
        <div class="section-head">
            <div>
                <h2 class="section-title">Foto wajah yang tersimpan</h2>
                <p class="section-note">
                    Daftar ini hanya memuat jumlah foto akun Anda, tanpa gambar dan tanpa angka wajahnya.
                    @if ($terkunci)
                        Pendaftaran Anda sudah selesai, jadi fotonya tidak bisa dihapus dari halaman ini.
                        Bila memang perlu diganti, minta petugas sekolah membuka pendaftaran ulang.
                    @else
                        Hapus foto yang menurut Anda sudah tidak layak, lalu ambil penggantinya.
                    @endif
                </p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Foto ke</th>
                        <th>Nama berkas asal</th>
                        <th>Diterima pada</th>
                        @unless ($terkunci)
                            <th>Aksi</th>
                        @endunless
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tersimpan as $index => $wajah)
                        <tr>
                            <td>{{ $banyakTersimpan - $index }}</td>
                            <td>{{ $wajah->label ?: 'tanpa nama' }}</td>
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
            <form method="POST" action="{{ route('enrollment.photos.clear') }}">
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
    </section>
@endif
