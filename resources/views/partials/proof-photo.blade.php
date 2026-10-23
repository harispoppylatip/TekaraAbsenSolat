@php
    $isFingerprint = $log instanceof \App\Models\SolatAttendanceLog && $log->isFingerprint();
    $proofUrl =
        !$isFingerprint && $log instanceof \App\Models\SolatAttendanceLog
            ? app(\App\Services\SolatProofService::class)->url($log)
            : null;

    $proofFallback = $proofFallback ?? null;
@endphp

@if ($isFingerprint)
    @if ($proofFallback !== null)
        <span class="cell-note">Via sidik jari{{ $log->device_id ? ' (' . $log->device_id . ')' : '' }}</span>
    @else
        <div class="proof-thumb proof-thumb-fingerprint" title="Absen via sidik jari{{ $log->device_id ? ' (' . $log->device_id . ')' : '' }}" aria-label="Absen via sidik jari">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 3"></path>
                <path d="M14 13.12c0 2.38 0 6.38-1 8.88"></path>
                <path d="M2 16h.01"></path>
                <path d="M21.8 16c.2-2 .131-5.354 0-6"></path>
                <path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2"></path>
                <path d="M8.65 22c.21-.66.45-1.32.57-2"></path>
                <path d="M9 6.8a6 6 0 0 1 9 5.2v2"></path>
            </svg>
        </div>
    @endif
@elseif ($proofUrl === null)
    @if ($proofFallback !== null)
        <span class="cell-note">{{ $proofFallback }}</span>
    @endif
@else
    <a class="proof-link" href="{{ $proofUrl }}" target="_blank" rel="noopener" title="Buka foto bukti absen">
        <img class="proof-thumb" src="{{ $proofUrl }}" alt="Foto bukti absen" width="56" height="56"
            loading="lazy" decoding="async">
    </a>
@endif

