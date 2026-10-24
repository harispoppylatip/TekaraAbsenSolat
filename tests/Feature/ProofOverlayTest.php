<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\ProofOverlayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProofOverlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengaturan_overlay_dapat_diubah_admin(): void
    {
        $admin = User::factory()->create(['role' => User::RoleAdmin]);

        $this->actingAs($admin)
            ->put(route('settings.proof-overlay'), [
                'proof_box_overlay' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue(Setting::proofOverlayEnabled());

        $this->actingAs($admin)
            ->put(route('settings.proof-overlay'), [])
            ->assertRedirect();

        $this->assertFalse(Setting::proofOverlayEnabled());
    }

    public function test_overlay_service_dapat_membubuhi_gambar(): void
    {
        $service = app(ProofOverlayService::class);
        Setting::set(Setting::KeyProofBoxOverlay, true);

        // Buat dummy JPEG image 300x200
        $im = imagecreatetruecolor(300, 200);
        ob_start();
        imagejpeg($im, null, 80);
        $dummyJpeg = ob_get_clean();
        imagedestroy($im);

        $faces = [
            [
                'box' => ['top' => 30, 'right' => 150, 'bottom' => 120, 'left' => 60],
                'label' => 'HARI POPPY LATIP',
                'status' => 'recorded',
                'attendance' => [
                    'recorded' => true,
                    'status' => 'recorded',
                    'display_message' => 'Hadir',
                ],
            ],
        ];

        $annotated = $service->annotate($dummyJpeg, $faces, 300, 200);
        $this->assertNotEmpty($annotated);
        $this->assertStringStartsWith("\xFF\xD8\xFF", $annotated);
    }
}

