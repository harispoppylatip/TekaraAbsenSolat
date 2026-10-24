<?php

namespace App\Services;

use App\Models\Setting;

class ProofOverlayService
{
    /**
     * Bubuhi foto bukti JPEG dengan kotak pendeteksi wajah dan label nama jamaah.
     *
     * @param  string  $jpegBinary  isi berkas foto JPEG mentah
     * @param  array<int, array<string, mixed>>  $faces  daftar wajah hasil penilaian
     * @param  int  $sourceWidth  lebar gambar saat dideteksi (bila berbeda dari foto bukti)
     * @param  int  $sourceHeight  tinggi gambar saat dideteksi
     */
    public function annotate(
        string $jpegBinary,
        array $faces,
        int $sourceWidth = 0,
        int $sourceHeight = 0
    ): string {
        if ($faces === [] || ! Setting::proofOverlayEnabled() || ! extension_loaded('gd')) {
            return $jpegBinary;
        }

        try {
            $im = @imagecreatefromstring($jpegBinary);
            if (! is_resource($im) && ! ($im instanceof \GdImage)) {
                return $jpegBinary;
            }

            $imgW = imagesx($im);
            $imgH = imagesy($im);

            if ($imgW <= 0 || $imgH <= 0) {
                return $jpegBinary;
            }

            $scaleX = ($sourceWidth > 0) ? ($imgW / $sourceWidth) : 1.0;
            $scaleY = ($sourceHeight > 0) ? ($imgH / $sourceHeight) : 1.0;

            $colorGreen = imagecolorallocate($im, 27, 107, 58); // #1b6b3a
            $colorOrange = imagecolorallocate($im, 138, 83, 0);  // #8a5300
            $colorWhite = imagecolorallocate($im, 255, 255, 255);

            $scale = max(0.5, $imgW / 720.0);
            $thickness = max(1, (int) round(2.0 * $scale));

            // Periksa font TrueType di sistem bila ada
            $ttfFont = $this->resolveTtfFont();

            foreach ($faces as $face) {
                $box = $face['box'] ?? null;
                if (! is_array($box)) {
                    continue;
                }

                $top = (int) round(($box['top'] ?? 0) * $scaleY);
                $right = (int) round(($box['right'] ?? 0) * $scaleX);
                $bottom = (int) round(($box['bottom'] ?? 0) * $scaleY);
                $left = (int) round(($box['left'] ?? 0) * $scaleX);

                $bx = max(0, min($imgW - 1, $left));
                $by = max(0, min($imgH - 1, $top));
                $bw = max(2, min($imgW - $bx, $right - $left));
                $bh = max(2, min($imgH - $by, $bottom - $top));

                $status = (string) ($face['attendance']['status'] ?? ($face['status'] ?? ''));
                $isSaved = ! empty($face['attendance']['recorded'])
                    || in_array($status, ['recorded', 'late', 'duplicate', 'cooldown'], true)
                    || ! empty($face['member']);

                $badgeColor = $isSaved ? $colorGreen : $colorOrange;

                // 1. Gambar kotak wajah dengan ketebalan rapi
                for ($t = 0; $t < $thickness; $t++) {
                    imagerectangle(
                        $im,
                        max(0, $bx - $t),
                        max(0, $by - $t),
                        min($imgW - 1, $bx + $bw + $t),
                        min($imgH - 1, $by + $bh + $t),
                        $badgeColor
                    );
                }

                // 2. Siapkan label nama dan status
                $nama = (string) ($face['label'] ?? ($face['member']['name'] ?? 'Tidak dikenal'));
                if ($nama === '' || $nama === 'Wajah tidak dikenal') {
                    $nama = 'Tidak dikenal';
                }

                $keadaan = (string) ($face['attendance']['display_message'] ?? ($face['display_message'] ?? 'Belum terdaftar'));
                if ($keadaan === '') {
                    $keadaan = 'Belum terdaftar';
                }

                // 3. Gambar badge dan teks
                if ($ttfFont !== null) {
                    $this->drawTtfBadge($im, $ttfFont, $bx, $by, $bw, $imgW, $imgH, $scale, $nama, $keadaan, $badgeColor, $colorWhite);
                } else {
                    $this->drawBitmapBadge($im, $bx, $by, $bw, $imgW, $imgH, $nama, $keadaan, $badgeColor, $colorWhite);
                }
            }

            ob_start();
            imagejpeg($im, null, 88);
            $annotated = ob_get_clean();
            imagedestroy($im);

            return is_string($annotated) && strlen($annotated) > 100 ? $annotated : $jpegBinary;
        } catch (\Throwable) {
            return $jpegBinary;
        }
    }

    private function resolveTtfFont(): ?string
    {
        $candidates = [
            resource_path('fonts/Inter-SemiBold.ttf'),
            resource_path('fonts/Inter.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
        ];

        foreach ($candidates as $font) {
            if (is_file($font)) {
                return $font;
            }
        }

        return null;
    }

    private function drawTtfBadge(
        \GdImage $im,
        string $font,
        int $bx,
        int $by,
        int $bw,
        int $imgW,
        int $imgH,
        float $scale,
        string $nama,
        string $keadaan,
        int $bgColor,
        int $textColor
    ): void {
        $fontSizeName = max(7, (int) round(12 * $scale));
        $fontSizeStatus = max(6, (int) round(10 * $scale));

        $bboxName = imagettfbbox($fontSizeName, 0, $font, $nama);
        $nameW = abs($bboxName[4] - $bboxName[0]);

        $bboxStatus = imagettfbbox($fontSizeStatus, 0, $font, $keadaan);
        $statusW = abs($bboxStatus[4] - $bboxStatus[0]);

        $badgePad = max(3, (int) round(6 * $scale));
        $badgeW = max($nameW, $statusW) + ($badgePad * 2);
        $badgeH = (int) round(($fontSizeName + $fontSizeStatus) * 1.3) + ($badgePad * 2);

        $badgeX = max(0, min($imgW - $badgeW, $bx));
        $badgeY = ($by - $badgeH < 0) ? $by : ($by - $badgeH);

        imagefilledrectangle($im, $badgeX, $badgeY, $badgeX + $badgeW, $badgeY + $badgeH, $bgColor);

        $textX = $badgeX + $badgePad;
        $textY1 = $badgeY + $badgePad + (int) round($fontSizeName * 1.05);
        $textY2 = $textY1 + (int) round($fontSizeStatus * 1.25);

        imagettftext($im, $fontSizeName, 0, $textX, $textY1, $textColor, $font, $nama);
        imagettftext($im, $fontSizeStatus, 0, $textX, $textY2, $textColor, $font, $keadaan);
    }

    private function drawBitmapBadge(
        \GdImage $im,
        int $bx,
        int $by,
        int $bw,
        int $imgW,
        int $imgH,
        string $nama,
        string $keadaan,
        int $bgColor,
        int $textColor
    ): void {
        $fontName = 4;   // 8x16 px
        $fontStatus = 2; // 6x13 px

        $nameW = strlen($nama) * imagefontwidth($fontName);
        $statusW = strlen($keadaan) * imagefontwidth($fontStatus);

        $badgePad = 6;
        $badgeW = max($nameW, $statusW) + ($badgePad * 2);
        $badgeH = imagefontheight($fontName) + imagefontheight($fontStatus) + 8;

        $badgeX = max(0, min($imgW - $badgeW, $bx));
        $badgeY = ($by - $badgeH < 0) ? $by : ($by - $badgeH);

        imagefilledrectangle($im, $badgeX, $badgeY, $badgeX + $badgeW, $badgeY + $badgeH, $bgColor);

        $textX = $badgeX + $badgePad;
        $textY1 = $badgeY + 4;
        $textY2 = $textY1 + imagefontheight($fontName) + 2;

        imagestring($im, $fontName, $textX, $textY1, $nama, $textColor);
        imagestring($im, $fontStatus, $textX, $textY2, $keadaan, $textColor);
    }
}
