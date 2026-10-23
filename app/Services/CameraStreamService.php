<?php

namespace App\Services;

use App\Models\Camera;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CameraStreamService
{
    /**
     * Uji koneksi ke siaran kamera (RTSP atau Web Stream).
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(string $streamType, string $streamUrl, ?string $snapshotUrl = null): array
    {
        $streamUrl = trim($streamUrl);

        if ($streamUrl === '') {
            return [
                'success' => false,
                'message' => 'Alamat URL siaran kamera tidak boleh kosong.',
            ];
        }

        if ($streamType === Camera::TypeRtsp) {
            return $this->testRtspConnection($streamUrl);
        }

        return $this->testWebStreamConnection($streamUrl, $snapshotUrl);
    }

    /**
     * Ambil satu lembar cuplikan gambar (snapshot) dari kamera sebagai biner JPEG.
     */
    public function captureSnapshot(Camera $camera): ?string
    {
        // 1. Jika ada URL snapshot HTTP khusus (misal port 80/8080 dari CCTV), dahulukan ini.
        if ($camera->snapshot_url) {
            try {
                $response = Http::timeout(5)->get($camera->snapshot_url);
                if ($response->successful()) {
                    $body = $response->body();
                    if ($this->isValidImage($body)) {
                        return $body;
                    }
                }
            } catch (\Throwable) {
                // Lanjut coba ffmpeg bila snapshot URL gagal.
            }
        }

        // 2. Ambil cuplikan menggunakan ffmpeg dari URL siaran
        if ($camera->isRtsp()) {
            return $this->captureRtspFrame($camera->stream_url);
        }

        return $this->captureWebStreamFrame($camera->stream_url);
    }

    /**
     * Alirkan siaran kamera ke peramban (proxy lokal) agar tidak terhalang CORS
     * dan dapat digambar di kanvas absensi wajah.
     */
    public function streamResponse(Camera $camera): StreamedResponse
    {
        if ($camera->isRtsp()) {
            return $this->streamRtspMjpeg($camera->stream_url);
        }

        return $this->streamWebProxy($camera->stream_url);
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected function testRtspConnection(string $streamUrl): array
    {
        // Uji apakah URL diawali rtsp://
        if (! str_starts_with(strtolower($streamUrl), 'rtsp://')) {
            return [
                'success' => false,
                'message' => 'Alamat URL untuk RTSP harus diawali dengan rtsp://',
            ];
        }

        // Coba probe stream via ffmpeg
        $command = [
            'ffmpeg',
            '-rtsp_transport', 'tcp',
            '-stimeout', '5000000', // 5 detik
            '-i', $streamUrl,
            '-t', '1',
            '-f', 'null',
            '-',
        ];

        try {
            $process = Process::timeout(7)->run($command);

            if ($process->successful() || str_contains($process->errorOutput(), 'Output #0, null')) {
                return [
                    'success' => true,
                    'message' => 'Koneksi RTSP CCTV berhasil terhubung.',
                ];
            }

            $error = trim($process->errorOutput());
            $shortError = $this->extractShortError($error);

            return [
                'success' => false,
                'message' => 'Gagal terhubung ke RTSP CCTV' . ($shortError ? ': ' . $shortError : '. Periksa IP, port, atau sandi RTSP.'),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Gagal menguji koneksi RTSP: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected function testWebStreamConnection(string $streamUrl, ?string $snapshotUrl = null): array
    {
        if (! filter_var($streamUrl, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'message' => 'Format URL Web Stream tidak valid.',
            ];
        }

        try {
            $response = Http::timeout(6)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])->get($streamUrl);

            if ($response->successful() || in_array($response->status(), [200, 206, 301, 302, 304, 307], true)) {
                return [
                    'success' => true,
                    'message' => 'Koneksi ke Web Stream berhasil terhubung (Status ' . $response->status() . ').',
                ];
            }

            return [
                'success' => false,
                'message' => 'Server Web Stream memberikan kode status ' . $response->status() . '.',
            ];
        } catch (\Throwable $e) {
            // Coba verifikasi dengan snapshot jika ada
            if ($snapshotUrl && filter_var($snapshotUrl, FILTER_VALIDATE_URL)) {
                try {
                    $snapResponse = Http::timeout(5)->get($snapshotUrl);
                    if ($snapResponse->successful()) {
                        return [
                            'success' => true,
                            'message' => 'Web Stream tidak langsung merespons GET, tetapi URL cuplikan berhasil terhubung.',
                        ];
                    }
                } catch (\Throwable) {
                    // Abaikan fallback snapshot jika gagal juga
                }
            }

            return [
                'success' => false,
                'message' => 'Tidak dapat menjangkau Web Stream: ' . $e->getMessage(),
            ];
        }
    }

    protected function captureRtspFrame(string $streamUrl): ?string
    {
        $command = [
            'ffmpeg',
            '-y',
            '-rtsp_transport', 'tcp',
            '-stimeout', '5000000',
            '-i', $streamUrl,
            '-vframes', '1',
            '-q:v', '2',
            '-f', 'image2',
            'pipe:1',
        ];

        try {
            $process = Process::timeout(8)->run($command);
            $output = $process->output();

            if ($this->isValidImage($output)) {
                return $output;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    protected function captureWebStreamFrame(string $streamUrl): ?string
    {
        $command = [
            'ffmpeg',
            '-y',
            '-i', $streamUrl,
            '-vframes', '1',
            '-q:v', '2',
            '-f', 'image2',
            'pipe:1',
        ];

        try {
            $process = Process::timeout(8)->run($command);
            $output = $process->output();

            if ($this->isValidImage($output)) {
                return $output;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    protected function streamWebProxy(string $streamUrl): StreamedResponse
    {
        return response()->stream(function () use ($streamUrl) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: */*\r\n",
                    'timeout' => 30,
                    'follow_location' => 1,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $handle = @fopen($streamUrl, 'rb', false, $context);
            if ($handle === false) {
                return;
            }

            while (! feof($handle) && ! connection_aborted()) {
                $buffer = fread($handle, 16384);
                if ($buffer !== false && $buffer !== '') {
                    echo $buffer;
                    flush();
                }
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    protected function streamRtspMjpeg(string $streamUrl): StreamedResponse
    {
        return response()->stream(function () use ($streamUrl) {
            $cmd = sprintf(
                'ffmpeg -rtsp_transport tcp -stimeout 5000000 -i %s -f mjpeg -q:v 4 -r 5 -an pipe:1',
                escapeshellarg($streamUrl)
            );

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($cmd, $descriptors, $pipes);

            if (! is_resource($process)) {
                return;
            }

            fclose($pipes[0]);
            fclose($pipes[2]);

            while (! feof($pipes[1]) && ! connection_aborted()) {
                $chunk = fread($pipes[1], 16384);
                if ($chunk !== false && $chunk !== '') {
                    echo $chunk;
                    flush();
                }
            }

            fclose($pipes[1]);
            proc_terminate($process);
            proc_close($process);
        }, 200, [
            'Content-Type' => 'multipart/x-mixed-replace; boundary=ffserver',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    protected function isValidImage(string $binary): bool
    {
        if (strlen($binary) < 4) {
            return false;
        }

        // JPEG SOI marker FF D8
        if (str_starts_with($binary, "\xFF\xD8")) {
            return true;
        }

        // PNG signature 89 50 4E 47
        if (str_starts_with($binary, "\x89PNG")) {
            return true;
        }

        return false;
    }

    protected function extractShortError(string $rawError): string
    {
        $lines = explode("\n", $rawError);
        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if ($line !== '' && ! str_starts_with($line, 'lib') && ! str_starts_with($line, 'configuration:')) {
                return $line;
            }
        }

        return '';
    }
}
