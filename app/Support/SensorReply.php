<?php

namespace App\Support;

/**
 * Bentuk balasan yang sama untuk layar perangkat dan halaman absen.
 *
 * Perangkat hanya punya layar lebar 16 kolom, jadi teks yang tampil
 * (display_message) dijaga pendek, sedangkan keterangan panjang untuk manusia
 * ditaruh di message.
 */
class SensorReply
{
    public const DetailFallback = 'Lapor operator';

    /**
     * @param  array<string, mixed>  $result  Balasan SolatAttendanceService.
     * @return array<string, mixed>
     */
    public static function fromResult(array $result): array
    {
        $user = $result['user'] ?? null;
        $log = $result['log'] ?? null;

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'blocked' => false,
            'status' => $result['status'] ?? 'unknown',
            'label' => $result['label'] ?? 'Tidak dikenal',
            'message' => $result['message'] ?? '',
            'display_message' => $result['display_message'] ?? self::DetailFallback,
            'display_detail' => $result['display_detail'] ?? self::DetailFallback,
            'member' => $user === null ? null : [
                'name' => $user->name,
                'identifier_number' => $user->identifier_number,
                'class_name' => $user->class_name,
            ],
            'log' => $log === null ? null : [
                'status' => $log->status,
                'status_label' => $log->statusLabel(),
                'scanned_at' => $log->scanned_at?->format('H:i:s'),
            ],
        ];
    }

    /**
     * Balasan penolakan untuk perangkat.
     *
     * @return array{payload: array<string, mixed>, http: int}
     */
    public static function refusal(string $code, string $display, int $http = 403): array
    {
        return [
            'payload' => [
                'ok' => false,
                'blocked' => true,
                'status' => $code,
                'label' => $display,
                'message' => $display,
                'display_message' => $display,
                'display_detail' => self::DetailFallback,
                'member' => null,
                'log' => null,
            ],
            'http' => $http,
        ];
    }
}
