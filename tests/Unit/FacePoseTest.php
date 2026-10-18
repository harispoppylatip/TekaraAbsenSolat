<?php

namespace Tests\Unit;

use App\Models\FaceEmbedding;
use App\Models\User;
use App\Support\FacePose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacePoseTest extends TestCase
{
    use RefreshDatabase;

    public function test_mendeteksi_pose_dari_nama_file(): void
    {
        $this->assertSame('lurus', FacePose::detect('kamera-lurus-17290000.jpg')['key']);
        $this->assertSame('kiri', FacePose::detect('kamera-serong-kiri-17290000.jpg')['key']);
        $this->assertSame('kanan', FacePose::detect('kamera-serong-kanan-17290000.jpg')['key']);
        $this->assertSame('atas', FacePose::detect('kamera-sedikit-ke-atas-17290000.jpg')['key']);
        $this->assertSame('bawah', FacePose::detect('kamera-sedikit-ke-bawah-17290000.jpg')['key']);
    }

    public function test_label_ramah_manusia(): void
    {
        $this->assertSame('Pose 2: Sedikit ke kiri', FacePose::humanLabel('kamera-serong-kiri-17290000.jpg'));
        $this->assertSame('Pose 4: Sedikit mendongak', FacePose::humanLabel('kamera-sedikit-ke-atas-17290000.jpg'));
    }

    public function test_analisis_pose_yang_hilang_atau_gagal(): void
    {
        $user = $this->makeUser(User::RoleMurid);

        // Simulasikan 3 foto tersimpan: Pose 1 (lurus), Pose 3 (kanan), Pose 5 (bawah)
        // Pose 2 (kiri) dan Pose 4 (atas) gagal atau belum ada
        FaceEmbedding::create([
            'user_id' => $user->id,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.0),
            'label' => 'kamera-lurus-1.jpg',
            'model' => (string) config('face.model'),
        ]);

        FaceEmbedding::create([
            'user_id' => $user->id,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.0),
            'label' => 'kamera-serong-kanan-3.jpg',
            'model' => (string) config('face.model'),
        ]);

        FaceEmbedding::create([
            'user_id' => $user->id,
            'embedding' => array_fill(0, FaceEmbedding::Dimensions, 0.0),
            'label' => 'kamera-sedikit-ke-bawah-5.jpg',
            'model' => (string) config('face.model'),
        ]);

        $analysis = FacePose::analyzeSaved($user->refresh()->faceEmbeddings);

        $this->assertSame(['kiri', 'atas'], $analysis['missing_keys']);
        $this->assertCount(2, $analysis['missing_poses']);
        $this->assertSame(2, $analysis['missing_poses'][0]['id']);
        $this->assertSame('kiri', $analysis['missing_poses'][0]['key']);
        $this->assertSame(4, $analysis['missing_poses'][1]['id']);
        $this->assertSame('atas', $analysis['missing_poses'][1]['key']);
    }
}
