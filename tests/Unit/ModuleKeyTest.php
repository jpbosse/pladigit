<?php

namespace Tests\Unit;

use App\Enums\ModuleKey;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires — Enum ModuleKey.
 */
class ModuleKeyTest extends TestCase
{
    // ── Valeurs de base ────────────────────────────────────────────────

    public function test_media_a_la_valeur_string_correcte(): void
    {
        $this->assertSame('media', ModuleKey::MEDIA->value);
    }

    public function test_from_string_retourne_le_bon_case(): void
    {
        $this->assertSame(ModuleKey::MEDIA, ModuleKey::from('media'));
        $this->assertSame(ModuleKey::GED, ModuleKey::from('ged'));
    }

    public function test_try_from_retourne_null_pour_clé_inconnue(): void
    {
        $this->assertNull(ModuleKey::tryFrom('inconnu'));
        $this->assertNull(ModuleKey::tryFrom(''));
    }

    // ── Labels ────────────────────────────────────────────────────────

    public function test_chaque_module_a_un_label_non_vide(): void
    {
        foreach (ModuleKey::cases() as $module) {
            $this->assertNotEmpty($module->label(), "Label vide pour {$module->value}");
        }
    }

    public function test_media_label_est_photothèque(): void
    {
        $this->assertSame('Photothèque', ModuleKey::MEDIA->label());
    }

    // ── Phases ────────────────────────────────────────────────────────

    public function test_media_est_en_phase_3(): void
    {
        $this->assertSame(3, ModuleKey::MEDIA->phase());
    }

    public function test_ged_est_en_phase_6(): void
    {
        $this->assertSame(6, ModuleKey::GED->phase());
    }

    public function test_toutes_les_phases_sont_positives(): void
    {
        foreach (ModuleKey::cases() as $module) {
            $this->assertGreaterThan(0, $module->phase(), "Phase invalide pour {$module->value}");
        }
    }

    // ── Disponibilité ─────────────────────────────────────────────────

    public function test_media_est_disponible(): void
    {
        $this->assertTrue(ModuleKey::MEDIA->isAvailable());
    }

    public function test_ged_est_disponible(): void
    {
        // GED est en phase 6 — livré
        $this->assertTrue(ModuleKey::GED->isAvailable());
    }

    public function test_available_contient_media_projects_et_ged(): void
    {
        $available = ModuleKey::available();

        $availableValues = array_map(fn ($m) => $m->value, $available);
        // Media, projects et ged sont disponibles (phases 3–6 livrées)
        $this->assertContains('media', $availableValues);
        $this->assertContains('projects', $availableValues);
        $this->assertContains('ged', $availableValues);
        // Les modules des phases > 6 ne sont pas encore disponibles
        $this->assertNotContains('collabora', $availableValues);
        $this->assertNotContains('chat', $availableValues);
    }

    // ── Helpers statiques ─────────────────────────────────────────────

    public function test_values_retourne_toutes_les_clés_string(): void
    {
        $values = ModuleKey::values();

        $this->assertContains('media', $values);
        $this->assertContains('ged', $values);
        $this->assertContains('collabora', $values);
        $this->assertCount(count(ModuleKey::cases()), $values);
    }

    public function test_options_retourne_un_tableau_valeur_label(): void
    {
        $options = ModuleKey::options();

        $this->assertArrayHasKey('media', $options);
        $this->assertSame('Photothèque', $options['media']);
    }
}
