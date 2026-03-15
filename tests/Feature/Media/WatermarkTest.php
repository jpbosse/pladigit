<?php

namespace Tests\Feature\Media;

use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use App\Services\WatermarkService;
use Tests\TestCase;

/**
 * Tests Feature — Watermark sur les téléchargements.
 *
 * Couvre :
 *   - Watermark désactivé → fichier original retourné
 *   - Watermark activé, image JPEG → WatermarkService::apply() appelé
 *   - Watermark activé, vidéo → WatermarkService non appelé (streaming)
 *   - Watermark activé, PNG → appliqué
 *   - shouldApply() : MIME non supporté → false
 *   - shouldApply() : wm_enabled false → false
 *   - Sauvegarde paramètres via SettingsController
 */
class WatermarkTest extends TestCase
{
    private User $admin;

    private MediaAlbum $album;

    private MediaItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->album = MediaAlbum::factory()->create([
            'created_by' => $this->admin->id,
            'visibility' => 'public',
        ]);

        $this->item = MediaItem::factory()->create([
            'album_id' => $this->album->id,
            'uploaded_by' => $this->admin->id,
            'mime_type' => 'image/jpeg',
            'file_name' => 'photo.jpg',
            'file_path' => 'photos/photo.jpg',
            'file_size_bytes' => 1024,
        ]);
    }

    // ── WatermarkService::shouldApply() ───────────────────────────────

    public function test_should_apply_retourne_false_si_wm_disabled(): void
    {
        $settings = TenantSettings::factory()->make(['wm_enabled' => false]);
        $service = new WatermarkService;

        $this->assertFalse($service->shouldApply('image/jpeg', $settings));
    }

    public function test_should_apply_retourne_true_pour_jpeg_si_wm_enabled(): void
    {
        $settings = TenantSettings::factory()->make(['wm_enabled' => true]);
        $service = new WatermarkService;

        $this->assertTrue($service->shouldApply('image/jpeg', $settings));
    }

    public function test_should_apply_retourne_true_pour_png_si_wm_enabled(): void
    {
        $settings = TenantSettings::factory()->make(['wm_enabled' => true]);
        $service = new WatermarkService;

        $this->assertTrue($service->shouldApply('image/png', $settings));
    }

    public function test_should_apply_retourne_false_pour_video(): void
    {
        $settings = TenantSettings::factory()->make(['wm_enabled' => true]);
        $service = new WatermarkService;

        $this->assertFalse($service->shouldApply('video/mp4', $settings));
    }

    public function test_should_apply_retourne_false_pour_pdf(): void
    {
        $settings = TenantSettings::factory()->make(['wm_enabled' => true]);
        $service = new WatermarkService;

        $this->assertFalse($service->shouldApply('application/pdf', $settings));
    }

    // ── WatermarkService::apply() ─────────────────────────────────────

    public function test_apply_retourne_jpeg_watermarked(): void
    {
        $settings = TenantSettings::factory()->make([
            'wm_enabled' => true,
            'wm_type' => 'text',
            'wm_text' => '© Test',
            'wm_position' => 'bottom-right',
            'wm_opacity' => 60,
            'wm_size' => 'medium',
        ]);

        // Image JPEG 100x100 minimale générée en mémoire
        $src = imagecreatetruecolor(100, 100);
        $this->assertNotFalse($src);
        $blue = imagecolorallocate($src, 0, 100, 200);
        imagefill($src, 0, 0, $blue ?: 0);
        ob_start();
        imagejpeg($src);
        $jpegContents = ob_get_clean();
        imagedestroy($src);

        $service = new WatermarkService;
        $result = $service->apply($jpegContents, 'image/jpeg', $settings);

        // Le résultat doit être une image JPEG valide (différente de l'originale)
        $this->assertNotEmpty($result);
        $this->assertNotSame($jpegContents, $result);

        $decoded = imagecreatefromstring($result);
        $this->assertNotFalse($decoded, 'Le résultat watermarké doit être une image valide');
        imagedestroy($decoded);
    }

    public function test_apply_retourne_original_pour_mime_non_supporté(): void
    {
        $settings = TenantSettings::factory()->make(['wm_enabled' => true]);
        $service = new WatermarkService;

        $original = 'contenu-video-binaire';
        $result = $service->apply($original, 'video/mp4', $settings);

        $this->assertSame($original, $result);
    }

    // ── Sauvegarde paramètres ─────────────────────────────────────────

    public function test_admin_peut_activer_le_watermark(): void
    {
        TenantSettings::firstOrCreate([]);

        $this->actingAs($this->admin, 'tenant')
            ->put(route('admin.settings.media.update'), [
                'media_default_cols' => 3,
                'wm_enabled' => '1',
                'wm_type' => 'text',
                'wm_text' => '© Ma Commune',
                'wm_position' => 'bottom-right',
                'wm_opacity' => 70,
                'wm_size' => 'medium',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = TenantSettings::first();
        $this->assertTrue((bool) $settings->wm_enabled);
        $this->assertSame('© Ma Commune', $settings->wm_text);
        $this->assertSame(70, (int) $settings->wm_opacity);
    }

    public function test_admin_peut_désactiver_le_watermark(): void
    {
        TenantSettings::firstOrCreate(['wm_enabled' => true]);

        $this->actingAs($this->admin, 'tenant')
            ->put(route('admin.settings.media.update'), [
                'media_default_cols' => 3,
                // wm_enabled absent = décoché
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = TenantSettings::first();
        $this->assertFalse((bool) $settings->wm_enabled);
    }

    public function test_wm_opacity_invalide_est_rejetée(): void
    {
        TenantSettings::firstOrCreate([]);

        $this->actingAs($this->admin, 'tenant')
            ->put(route('admin.settings.media.update'), [
                'media_default_cols' => 3,
                'wm_enabled' => '1',
                'wm_opacity' => 5, // < 10 → invalide
            ])
            ->assertSessionHasErrors('wm_opacity');
    }
}
