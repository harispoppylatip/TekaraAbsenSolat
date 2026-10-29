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
            $colorRed = imagecolorallocate($im, 220, 38, 38);   // #dc2626
            $colorWhite = imagecolorallocate($im, 255, 255, 255);

            $scale = max(0.5, $imgW / 720.0);
            $thickness = max(1, (int) round(2.0 * $scale));

            // Periksa font TrueType di sistem bila ada
            $ttfFont = $this->resolveTtfFont();
            $drawnBadges = [];

            foreach ($faces as $face) {
                // Gambar kotak merah bila terdeteksi foto palsu (spoofing), atau hijau untuk anggota terdaftar
                $isSpoof = (($face['status'] ?? '') === 'spoof_detected') || (($face['label'] ?? '') === '[FOTO PALSU]');
                $hasMember = ! empty($face['member']);
                $nama = (string) ($face['label'] ?? ($face['member']['name'] ?? ''));
                $isUnknown = $nama === '' || in_array($nama, ['Tidak dikenal', 'Wajah tidak dikenal'], true);

                if ($isSpoof) {
                    $hasMember = true;
                    $nama = '[FOTO PALSU]';
                    $keadaan = (string) ($face['detail'] ?? ($face['display'] ?? 'Layar HP/Kertas'));
                    $badgeColor = $colorRed;
                } elseif (! $hasMember && $isUnknown) {
                    continue;
                } else {
                    $badgeColor = $colorGreen;
                    $keadaan = (string) ($face['attendance']['display_message'] ?? ($face['display_message'] ?? 'Hadir'));
                    if ($keadaan === '' || $keadaan === 'Belum terdaftar') {
                        $keadaan = 'Hadir';
                    }
                }

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

                // 2. Gambar badge dan teks dengan pencegahan tumpang-tindih (collision avoidance)
                if ($ttfFont !== null) {
                    $drawn = $this->drawTtfBadge($im, $ttfFont, $bx, $by, $bw, $bh, $imgW, $imgH, $scale, $nama, $keadaan, $badgeColor, $colorWhite, $drawnBadges);
                } else {
                    $drawn = $this->drawBitmapBadge($im, $bx, $by, $bw, $bh, $imgW, $imgH, $nama, $keadaan, $badgeColor, $colorWhite, $drawnBadges);
                }

                if ($drawn !== null) {
                    $drawnBadges[] = $drawn;
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

    /**
     * @param  array<int, array{x: int, y: int, w: int, h: int}>  $drawnBadges
     * @return array{x: int, y: int, w: int, h: int}
     */
    private function drawTtfBadge(
        \GdImage $im,
        string $font,
        int $bx,
        int $by,
        int $bw,
        int $bh,
        int $imgW,
        int $imgH,
        float $scale,
        string $nama,
        string $keadaan,
        int $bgColor,
        int $textColor,
        array $drawnBadges = []
    ): array {
        $fontSizeName = max(7, (int) round(12 * $scale));
        $fontSizeStatus = max(6, (int) round(10 * $scale));

        $bboxName = imagettfbbox($fontSizeName, 0, $font, $nama);
        $nameW = abs($bboxName[4] - $bboxName[0]);

        $bboxStatus = imagettfbbox($fontSizeStatus, 0, $font, $keadaan);
        $statusW = abs($bboxStatus[4] - $bboxStatus[0]);

        $badgePad = max(3, (int) round(6 * $scale));
        $badgeW = max($nameW, $statusW) + ($badgePad * 2);
        $badgeH = (int) round(($fontSizeName + $fontSizeStatus) * 1.3) + ($badgePad * 2);

        [$badgeX, $badgeY] = $this->calculateBadgePosition($bx, $by, $bw, $bh, $badgeW, $badgeH, $imgW, $imgH, $drawnBadges);

        imagefilledrectangle($im, $badgeX, $badgeY, $badgeX + $badgeW, $badgeY + $badgeH, $bgColor);

        $textX = $badgeX + $badgePad;
        $textY1 = $badgeY + $badgePad + (int) round($fontSizeName * 1.05);
        $textY2 = $textY1 + (int) round($fontSizeStatus * 1.25);

        imagettftext($im, $fontSizeName, 0, $textX, $textY1, $textColor, $font, $nama);
        imagettftext($im, $fontSizeStatus, 0, $textX, $textY2, $textColor, $font, $keadaan);

        return ['x' => $badgeX, 'y' => $badgeY, 'w' => $badgeW, 'h' => $badgeH];
    }

    /**
     * @param  array<int, array{x: int, y: int, w: int, h: int}>  $drawnBadges
     * @return array{x: int, y: int, w: int, h: int}
     */
    private function drawBitmapBadge(
        \GdImage $im,
        int $bx,
        int $by,
        int $bw,
        int $bh,
        int $imgW,
        int $imgH,
        string $nama,
        string $keadaan,
        int $bgColor,
        int $textColor,
        array $drawnBadges = []
    ): array {
        $fontName = 4;   // 8x16 px
        $fontStatus = 2; // 6x13 px

        $nameW = strlen($nama) * imagefontwidth($fontName);
        $statusW = strlen($keadaan) * imagefontwidth($fontStatus);

        $badgePad = 6;
        $badgeW = max($nameW, $statusW) + ($badgePad * 2);
        $badgeH = imagefontheight($fontName) + imagefontheight($fontStatus) + 8;

        [$badgeX, $badgeY] = $this->calculateBadgePosition($bx, $by, $bw, $bh, $badgeW, $badgeH, $imgW, $imgH, $drawnBadges);

        imagefilledrectangle($im, $badgeX, $badgeY, $badgeX + $badgeW, $badgeY + $badgeH, $bgColor);

        $textX = $badgeX + $badgePad;
        $textY1 = $badgeY + 4;
        $textY2 = $textY1 + imagefontheight($fontName) + 2;

        imagestring($im, $fontName, $textX, $textY1, $nama, $textColor);
        imagestring($im, $fontStatus, $textX, $textY2, $keadaan, $textColor);

        return ['x' => $badgeX, 'y' => $badgeY, 'w' => $badgeW, 'h' => $badgeH];
    }

    /**
     * Tentukan posisi badge nama agar tidak keluar kanvas dan tidak menabrak badge lain.
     *
     * @param  array<int, array{x: int, y: int, w: int, h: int}>  $drawnBadges
     * @return array{0: int, 1: int} [badgeX, badgeY]
     */
    private function calculateBadgePosition(
        int $bx,
        int $by,
        int $bw,
        int $bh,
        int $badgeW,
        int $badgeH,
        int $imgW,
        int $imgH,
        array $drawnBadges
    ): array {
        $badgeX = max(0, min($imgW - $badgeW, $bx));

        // Pilihan 1: Di atas kotak wajah (posisi standar)
        $topY = $by - $badgeH;
        $isTopValid = ($topY >= 0);

        $collidesTop = false;
        if ($isTopValid) {
            foreach ($drawnBadges as $prev) {
                if ($this->isRectIntersect($badgeX, $topY, $badgeW, $badgeH, $prev['x'], $prev['y'], $prev['w'], $prev['h'])) {
                    $collidesTop = true;
                    break;
                }
            }
        }

        if ($isTopValid && ! $collidesTop) {
            return [$badgeX, $topY];
        }

        // Pilihan 2: Jika posisi atas bertabrakan atau keluar kanvas atas, coba di bawah kotak wajah
        $bottomY = $by + $bh;
        $isBottomValid = ($bottomY + $badgeH <= $imgH);

        $collidesBottom = false;
        if ($isBottomValid) {
            foreach ($drawnBadges as $prev) {
                if ($this->isRectIntersect($badgeX, $bottomY, $badgeW, $badgeH, $prev['x'], $prev['y'], $prev['w'], $prev['h'])) {
                    $collidesBottom = true;
                    break;
                }
            }
        }

        if ($isBottomValid && ! $collidesBottom) {
            return [$badgeX, $bottomY];
        }

        // Fallback: Di atas jika muat, atau di batas atas kanvas
        return [$badgeX, $isTopValid ? $topY : max(0, $by)];
    }

    private function isRectIntersect(int $x1, int $y1, int $w1, int $h1, int $x2, int $y2, int $w2, int $h2): bool
    {
        return ! ($x1 + $w1 <= $x2 || $x2 + $w2 <= $x1 || $y1 + $h1 <= $y2 || $y2 + $h2 <= $y1);
    }
}
