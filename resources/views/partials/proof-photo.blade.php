@php
    // Foto bukti disajikan lewat rute berizin, jadi tautannya dibuat dari
    // catatan absennya, bukan dari lokasi berkasnya.
    $proofUrl =
        $log instanceof \App\Models\SolatAttendanceLog ? app(\App\Services\SolatProofService::class)->url($log) : null;

    $isFingerprint = $log instanceof \App\Models\SolatAttendanceLog && $log->isFingerprint();
    $proofFallback = $proofFallback ?? null;
@endphp

@if ($proofUrl === null)
    @if ($proofFallback !== null)
        @if ($isFingerprint)
            <span class="cell-note">Via sidik jari{{ $log->device_id ? ' (' . $log->device_id . ')' : '' }}</span>
        @else
            <span class="cell-note">{{ $proofFallback }}</span>
        @endif
    @endif
@else
    <a class="proof-link" href="{{ $proofUrl }}" target="_blank" rel="noopener" title="Buka foto bukti absen">
        <img class="proof-thumb" src="{{ $proofUrl }}" alt="Foto bukti absen" width="56" height="56"
            loading="lazy" decoding="async">
    </a>
@endif
