<?php

namespace App\Support;

use App\Models\FaceEmbedding;
use Illuminate\Support\Collection;

/**
 * Pengenal dan pengelola 5 pose wajah terpandu untuk pendaftaran wajah mandiri.
 *
 * Mengidentifikasi pose dari nama/label foto (lurus, kiri, kanan, atas/dongak,
 * bawah/tunduk) agar antrean pemeriksaan dan kamera otomatis tahu pose mana
 * yang sudah berhasil disimpan dan pose mana yang gagal atau belum diambil.
 */
class FacePose
{
    /**
     * @return array<int, array{id: int, key: string, label: string, short_label: string, instruction: string, hint: string, keywords: array<int, string>}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 1,
                'key' => 'lurus',
                'label' => 'Menghadap lurus',
                'short_label' => 'Lurus',
                'instruction' => 'Hadap lurus ke kamera',
                'hint' => 'Lihat tepat ke kamera, wajah lurus, telinga kiri dan kanan sama-sama terlihat.',
                'keywords' => ['lurus', 'depan', 'center', 'pose-1', 'foto-1', 'pose 1', 'foto 1'],
            ],
            [
                'id' => 2,
                'key' => 'kiri',
                'label' => 'Sedikit ke kiri',
                'short_label' => 'Serong Kiri',
                'instruction' => 'Tengok sedikit ke kiri',
                'hint' => 'Putar wajah pelan ke kiri, jangan sampai telinga kanan hilang.',
                'keywords' => ['kiri', 'left', 'pose-2', 'foto-2', 'pose 2', 'foto 2'],
            ],
            [
                'id' => 3,
                'key' => 'kanan',
                'label' => 'Sedikit ke kanan',
                'short_label' => 'Serong Kanan',
                'instruction' => 'Tengok sedikit ke kanan',
                'hint' => 'Putar wajah pelan ke kanan, jangan sampai telinga kiri hilang.',
                'keywords' => ['kanan', 'right', 'pose-3', 'foto-3', 'pose 3', 'foto 3'],
            ],
            [
                'id' => 4,
                'key' => 'atas',
                'label' => 'Sedikit mendongak',
                'short_label' => 'Sedikit ke Atas',
                'instruction' => 'Angkat kepala sedikit ke atas',
                'hint' => 'Angkat dagu sedikit saja, mata tetap melihat ke kamera.',
                'keywords' => ['atas', 'dongak', 'mendongak', 'up', 'pose-4', 'foto-4', 'pose 4', 'foto 4'],
            ],
            [
                'id' => 5,
                'key' => 'bawah',
                'label' => 'Sedikit menunduk',
                'short_label' => 'Sedikit ke Bawah',
                'instruction' => 'Tundukkan kepala sedikit ke bawah',
                'hint' => 'Turunkan dagu sedikit saja, jangan sampai mata hilang di balik kelopak.',
                'keywords' => ['bawah', 'tunduk', 'menunduk', 'down', 'pose-5', 'foto-5', 'pose 5', 'foto 5'],
            ],
        ];
    }

    /**
     * Cari pose berdasarkan nama/label foto.
     *
     * @return array{id: int, key: string, label: string, short_label: string, instruction: string, hint: string, keywords: array<int, string>}|null
     */
    public static function detect(?string $label): ?array
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $clean = mb_strtolower(trim($label));

        foreach (self::all() as $pose) {
            foreach ($pose['keywords'] as $keyword) {
                if (str_contains($clean, $keyword)) {
                    return $pose;
                }
            }
        }

        // Coba periksa angka tunggal (misal '1.jpg' atau 'foto 1')
        if (preg_match('/(?:^|[^0-9])([1-5])(?:[^0-9]|$)/', $clean, $matches)) {
            $num = (int) $matches[1];
            foreach (self::all() as $pose) {
                if ($pose['id'] === $num) {
                    return $pose;
                }
            }
        }

        return null;
    }

    /**
     * Dapatkan label ramah manusia untuk nama file / label foto.
     */
    public static function humanLabel(?string $label, ?int $fallbackIndex = null): string
    {
        $detected = self::detect($label);

        if ($detected !== null) {
            return 'Pose '.$detected['id'].': '.$detected['label'];
        }

        if ($fallbackIndex !== null && $fallbackIndex >= 1 && $fallbackIndex <= 5) {
            $all = self::all();
            $pose = $all[$fallbackIndex - 1] ?? null;
            if ($pose !== null) {
                return 'Pose '.$pose['id'].': '.$pose['label'];
            }
        }

        return $label ?: 'Foto Wajah';
    }

    /**
     * Analisis foto-foto yang tersimpan untuk menentukan pose mana yang sudah ada
     * dan pose mana yang masih kurang.
     *
     * @param  Collection<int, FaceEmbedding>|array<int, mixed>  $savedEmbeddings
     * @return array{
     *     saved_by_pose: array<int, array{pose: array<string, mixed>, embedding: FaceEmbedding|mixed}>,
     *     missing_poses: array<int, array{id: int, key: string, label: string, short_label: string, instruction: string, hint: string}>,
     *     missing_keys: array<int, string>
     * }
     */
    public static function analyzeSaved(mixed $savedEmbeddings): array
    {
        $all = self::all();
        $savedByPose = [];
        $unassigned = [];

        $items = $savedEmbeddings instanceof Collection ? $savedEmbeddings->all() : (array) $savedEmbeddings;

        foreach ($items as $item) {
            $label = $item instanceof FaceEmbedding ? $item->label : ($item['label'] ?? null);
            $detected = self::detect($label);

            if ($detected !== null && ! isset($savedByPose[$detected['id']])) {
                $savedByPose[$detected['id']] = [
                    'pose' => $detected,
                    'embedding' => $item,
                ];
            } else {
                $unassigned[] = $item;
            }
        }

        // Jika ada foto tersimpan yang belum terpetakan ke pose tertentu,
        // pasangkan ke slot pose yang masih kosong sesuai urutan nomor.
        foreach ($all as $pose) {
            if (! isset($savedByPose[$pose['id']]) && $unassigned !== []) {
                $item = array_shift($unassigned);
                $savedByPose[$pose['id']] = [
                    'pose' => $pose,
                    'embedding' => $item,
                ];
            }
        }

        $missingPoses = [];
        $missingKeys = [];

        foreach ($all as $pose) {
            if (! isset($savedByPose[$pose['id']])) {
                $missingPoses[] = [
                    'id' => $pose['id'],
                    'key' => $pose['key'],
                    'label' => $pose['label'],
                    'short_label' => $pose['short_label'],
                    'instruction' => $pose['instruction'],
                    'hint' => $pose['hint'],
                ];
                $missingKeys[] = $pose['key'];
            }
        }

        return [
            'saved_by_pose' => $savedByPose,
            'missing_poses' => $missingPoses,
            'missing_keys' => $missingKeys,
        ];
    }
}

