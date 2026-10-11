{{--
    Satu kelompok foto wajah yang sedang dikerjakan di latar belakang.
    Markahnya dicerminkan oleh resources/js/app.js supaya kemajuannya bisa
    diperbarui sendiri tanpa memuat ulang halaman.
--}}
@php
    $tone = $batch['done'] ? ($batch['failed'] > 0 ? 'sebagian' : 'selesai') : 'berjalan';
@endphp

<article class="queue-item" data-batch="{{ $batch['id'] }}" data-tone="{{ $tone }}">
    <div class="queue-head">
        <div>
            <p class="queue-title">{{ $batch['member'] }}</p>
            <p class="queue-note">{{ $batch['member_note'] }}</p>
        </div>
        <p class="queue-value" data-batch-percent>{{ $batch['percent'] }}%</p>
    </div>

    {{-- Ditaruh di atas daftar foto, bukan di bawahnya: kalau pekerja antrean
         belum jalan, inilah satu-satunya hal yang perlu dibaca admin, dan
         daftar sembilan foto bisa mendorongnya ke luar layar. --}}
    @if ($batch['stalled'])
        <p class="queue-note queue-alert" data-batch-alert>
            Antrean belum dijalankan. Jalankan <code>php artisan queue:work</code> di server supaya foto
            yang menunggu mulai diproses.
        </p>
    @endif

    <p class="queue-note" data-batch-note>{{ $batch['note'] }}</p>

    <div class="progress-track">
        <div class="progress-fill" data-batch-bar style="width: {{ $batch['percent'] }}%"></div>
    </div>

    <div class="queue-rows">
        @foreach ($batch['rows'] as $row)
            <div class="queue-row" data-row="{{ $row['id'] }}" data-tone="{{ $row['status'] }}">
                <p class="queue-file">{{ $row['name'] }}</p>
                <p class="queue-note">{{ $row['note'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($batch['retryable'] > 0)
        <form method="POST" action="{{ route('face.queue.retry', $batch['id']) }}">
            @csrf
            <button class="btn btn-small btn-secondary" type="submit">Ulangi {{ $batch['retryable'] }} foto
                yang gagal</button>
        </form>
    @endif
</article>
