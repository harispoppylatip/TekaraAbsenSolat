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

        // 2. Ambil cuplikan menggunakan go2rtc atau ffmpeg dari URL siaran
        if ($camera->isRtsp()) {
            if (! app()->runningUnitTests()) {
                if ($this->isGo2RtcRunning()) {
                    $streamKey = 'cam_' . $camera->id;
                    $baseUrl = $this->go2RtcUrl();

                    try {
                        $res = Http::timeout(3)->get($baseUrl . '/api/frame.jpeg', [
                            'src' => $streamKey,
                        ]);

                        // Bila stream belum terdaftar di go2rtc (misal container go2rtc baru menyala), daftarkan lalu ambil lagi
                        if (! $res->successful() || ! $this->isValidImage($res->body())) {
                            Http::timeout(2)->put($baseUrl . '/api/streams?' . http_build_query([
                                'name' => $streamKey,
                                'src' => trim($camera->stream_url),
                            ]));
                            $res = Http::timeout(4)->get($baseUrl . '/api/frame.jpeg', [
                                'src' => $streamKey,
                            ]);
                        }

                        if ($res->successful() && $this->isValidImage($res->body())) {
                            return $res->body();
                        }
                    } catch (\Throwable) {
                        // Lanjut ke fallback berikutnya
                    }
                }

                $liveFrame = $this->readOrStartLiveRtspFrame($camera);
                if ($liveFrame !== null) {
                    return $liveFrame;
                }
            }

            return $this->captureRtspFrame($camera->stream_url);
        }

        return $this->captureWebStreamFrame($camera->stream_url);
    }

    /**
     * Pastikan layanan go2rtc berjalan dan siaran kamera terdaftar di dalamnya
     * untuk pemutaran WebRTC / MSE (WebSocket MP4) tanpa beban CPU.
     */
    public function ensureGo2RtcStream(Camera $camera): string
    {
        $streamKey = 'cam_' . $camera->id;

        if (! $camera->isRtsp() || app()->runningUnitTests()) {
            return $streamKey;
        }

        $this->writeGo2RtcConfig();
        $this->startGo2RtcIfNotRunning();

        try {
            Http::timeout(2)->put($this->go2RtcUrl() . '/api/streams?' . http_build_query([
                'name' => $streamKey,
                'src' => trim($camera->stream_url),
            ]));
        } catch (\Throwable) {
            // Abaikan bila go2rtc baru saja menyala dan sedang memuat go2rtc.yaml
        }

        return $streamKey;
    }

    public function go2RtcUrl(): string
    {
        $configured = env('GO2RTC_URL');
        if (is_string($configured) && trim($configured) !== '') {
            return rtrim(trim($configured), '/');
        }

        return 'http://127.0.0.1:1984';
    }

    public function isGo2RtcRunning(): bool
    {
        $parsed = parse_url($this->go2RtcUrl());
        $host = is_array($parsed) && ! empty($parsed['host']) ? $parsed['host'] : '127.0.0.1';
        $port = is_array($parsed) && ! empty($parsed['port']) ? (int) $parsed['port'] : 1984;

        $fp = @fsockopen($host, $port, $errno, $errstr, 0.35);
        if (is_resource($fp)) {
            fclose($fp);

            return true;
        }

        return false;
    }

    protected function writeGo2RtcConfig(): void
    {
        $dir = base_path('bin/go2rtc');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $rtspCameras = Camera::query()
            ->where('stream_type', Camera::TypeRtsp)
            ->get();

        $lines = [
            'api:',
            '  listen: ":1984"',
            '  origin: "*"',
            '',
            'rtsp:',
            '  listen: ":18554"',
            '',
            'webrtc:',
            '  listen: ":18555"',
            '',
            'streams:',
        ];

        foreach ($rtspCameras as $cam) {
            $url = str_replace('"', '\\"', trim($cam->stream_url));
            $lines[] = "  cam_{$cam->id}:";
            $lines[] = "    - \"{$url}\"";
        }

        @file_put_contents($dir . '/go2rtc.yaml', implode("\n", $lines) . "\n");
    }

    protected function startGo2RtcIfNotRunning(): void
    {
        if ($this->isGo2RtcRunning()) {
            return;
        }

        foreach ($this->processEnv() as $key => $val) {
            @putenv("{$key}={$val}");
        }

        $configPath = base_path('bin/go2rtc/go2rtc.yaml');

        if (DIRECTORY_SEPARATOR === '\\') {
            $binPath = base_path('bin/go2rtc/go2rtc.exe');
            if (! is_file($binPath)) {
                return;
            }

            $cmd = sprintf(
                'start /B "" %s -c %s > NUL 2>&1',
                escapeshellarg($binPath),
                escapeshellarg($configPath)
            );
            $handle = @popen($cmd, 'r');
            if (is_resource($handle)) {
                @pclose($handle);
            }
        } else {
            $binPath = base_path('bin/go2rtc/go2rtc');
            $executable = is_file($binPath) ? escapeshellarg($binPath) : 'go2rtc';
            $cmd = sprintf(
                'nohup %s -c %s > /dev/null 2>&1 &',
                $executable,
                escapeshellarg($configPath)
            );
            @exec($cmd);
        }

        for ($i = 0; $i < 12; $i++) {
            usleep(100000);
            if ($this->isGo2RtcRunning()) {
                break;
            }
        }
    }

    /**
     * Pastikan proses latar belakang FFmpeg untuk siaran langsung RTSP sedang berjalan
     * sehingga peramban dapat menampilkan video secara mulus (10 FPS) tanpa jeda.
     */
    public function ensureBackgroundStream(Camera $camera): void
    {
        if (! $camera->isRtsp() || app()->runningUnitTests()) {
            return;
        }

        $streamUrl = trim($camera->stream_url);
        if (! str_starts_with(strtolower($streamUrl), 'rtsp://')) {
            return;
        }

        $dir = storage_path('app/cctv');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $framePath = $this->liveFramePath($camera->id);
        $lockPath = $this->liveLockPath($camera->id);

        clearstatcache(true, $framePath);
        clearstatcache(true, $lockPath);

        $now = time();
        $frameFresh = is_file($framePath) && ($now - (int) @filemtime($framePath)) <= 3;
        $lockAge = is_file($lockPath) ? ($now - (int) @filemtime($lockPath)) : 9999;

        // Jika frame masih segar dan proses belum mendekati batas waktu 180 detik, tidak perlu spawn ulang
        if ($frameFresh && $lockAge < 160) {
            return;
        }

        // Jika baru saja di-spawn dalam 7 detik terakhir, tunggu koneksi RTSP selesai
        if ($lockAge <= 7) {
            return;
        }

        @file_put_contents($lockPath, (string) $now);

        foreach ($this->processEnv() as $key => $val) {
            @putenv("{$key}={$val}");
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $cmd = sprintf(
                'start /B "" ffmpeg -nostdin -loglevel error -y -rtsp_transport tcp -analyzeduration 500000 -probesize 500000 -i %s -t 180 -vf fps=10 -q:v 4 -f image2 -atomic_writing 1 -update 1 %s > NUL 2>&1',
                escapeshellarg($streamUrl),
                escapeshellarg($framePath)
            );
            $handle = @popen($cmd, 'r');
            if (is_resource($handle)) {
                @pclose($handle);
            }
        } else {
            $cmd = sprintf(
                'nohup ffmpeg -nostdin -loglevel error -y -rtsp_transport tcp -analyzeduration 500000 -probesize 500000 -i %s -t 180 -vf fps=10 -q:v 4 -f image2 -atomic_writing 1 -update 1 %s > /dev/null 2>&1 &',
                escapeshellarg($streamUrl),
                escapeshellarg($framePath)
            );
            @exec($cmd);
        }
    }

    protected function readOrStartLiveRtspFrame(Camera $camera): ?string
    {
        $framePath = $this->liveFramePath($camera->id);
        clearstatcache(true, $framePath);

        // Jika frame sudah tersedia dan segar (<= 4 detik), langsung kembalikan dalam < 1ms
        if (is_file($framePath) && (time() - (int) @filemtime($framePath)) <= 4) {
            $this->ensureBackgroundStream($camera);
            $bytes = @file_get_contents($framePath);
            if (is_string($bytes) && $this->isValidImage($bytes)) {
                return $bytes;
            }
        }

        // Jalankan stream latar belakang bila belum aktif, lalu tunggu hingga frame pertama siap
        $this->ensureBackgroundStream($camera);

        for ($i = 0; $i < 25; $i++) {
            usleep(150000); // 150ms (total maks 3.75 detik)
            clearstatcache(true, $framePath);
            if (is_file($framePath) && (time() - (int) @filemtime($framePath)) <= 4) {
                $bytes = @file_get_contents($framePath);
                if (is_string($bytes) && $this->isValidImage($bytes)) {
                    return $bytes;
                }
            }
        }

        return null;
    }

    protected function liveFramePath(int $cameraId): string
    {
        return storage_path('app/cctv/cam_' . $cameraId . '.jpg');
    }

    protected function liveLockPath(int $cameraId): string
    {
        return storage_path('app/cctv/cam_' . $cameraId . '.lock');
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
            '-analyzeduration', '500000',
            '-probesize', '500000',
            '-i', $streamUrl,
            '-t', '1',
            '-f', 'null',
            '-',
        ];

        try {
            $process = Process::timeout(7)->env($this->processEnv())->run($command);

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
        $urlsToTry = [$streamUrl];
        if (str_contains($streamUrl, '/Channels/101')) {
            $urlsToTry[] = str_replace('/Channels/101', '/Channels/102', $streamUrl);
        }

        foreach ($urlsToTry as $url) {
            $command = [
                'ffmpeg',
                '-y',
                '-rtsp_transport', 'tcp',
                '-analyzeduration', '500000',
                '-probesize', '500000',
                '-i', $url,
                '-vframes', '1',
                '-q:v', '2',
                '-f', 'image2',
                'pipe:1',
            ];

            try {
                $process = Process::timeout(6)->env($this->processEnv())->run($command);
                $output = $process->output();

                if ($this->isValidImage($output)) {
                    return $output;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    protected function captureWebStreamFrame(string $streamUrl): ?string
    {
        $command = [
            'ffmpeg',
            '-y',
            '-analyzeduration', '500000',
            '-probesize', '500000',
            '-i', $streamUrl,
            '-vframes', '1',
            '-q:v', '2',
            '-f', 'image2',
            'pipe:1',
        ];

        try {
            $process = Process::timeout(8)->env($this->processEnv())->run($command);
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
                'ffmpeg -rtsp_transport tcp -i %s -f mjpeg -q:v 4 -r 5 -an pipe:1',
                escapeshellarg($streamUrl)
            );

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($cmd, $descriptors, $pipes, null, $this->processEnv());

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

    /**
     * Pastikan variabel lingkungan Windows (terutama SystemRoot untuk Winsock TCP/IP)
     * diteruskan ke proses anak ffmpeg agar tidak gagal dengan galat -10106 (WSAEPROVIDERFAILEDINIT).
     *
     * @return array<string, string>
     */
    protected function processEnv(): array
    {
        $env = [];
        foreach (['SystemRoot', 'SystemDrive', 'PATH', 'Path', 'TEMP', 'TMP', 'USERPROFILE', 'windir'] as $key) {
            $val = getenv($key) ?: ($_SERVER[$key] ?? $_ENV[$key] ?? null);
            if (is_string($val) && $val !== '') {
                $env[$key] = $val;
            }
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $env['SystemRoot'] ??= 'C:\\WINDOWS';
            $env['SystemDrive'] ??= 'C:';
            $env['windir'] ??= 'C:\\WINDOWS';
        }

        return $env;
    }
}

