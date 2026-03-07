<?php

namespace Tests\Unit\Enums;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'enum UserRole.
 * Ne nécessite pas de base de données.
 */
class UserRoleTest extends TestCase
{
    // ── Hiérarchie ────────────────────────────────────────

    public function test_admin_a_le_niveau_le_plus_bas(): void
    {
        $this->assertEquals(1, UserRole::ADMIN->level());
    }

    public function test_user_a_le_niveau_le_plus_haut(): void
    {
        $this->assertEquals(6, UserRole::USER->level());
    }

    public function test_ordre_hierarchique_correct(): void
    {
        $this->assertLessThan(UserRole::PRESIDENT->level(), UserRole::ADMIN->level());
        $this->assertLessThan(UserRole::DGS->level(), UserRole::PRESIDENT->level());
        $this->assertLessThan(UserRole::RESP_DIRECTION->level(), UserRole::DGS->level());
        $this->assertLessThan(UserRole::RESP_SERVICE->level(), UserRole::RESP_DIRECTION->level());
        $this->assertLessThan(UserRole::USER->level(), UserRole::RESP_SERVICE->level());
    }

    // ── atLeast() ─────────────────────────────────────────

    public function test_admin_at_least_user(): void
    {
        $this->assertTrue(UserRole::ADMIN->atLeast(UserRole::USER));
    }

    public function test_user_not_at_least_admin(): void
    {
        $this->assertFalse(UserRole::USER->atLeast(UserRole::ADMIN));
    }

    public function test_dgs_at_least_dgs(): void
    {
        $this->assertTrue(UserRole::DGS->atLeast(UserRole::DGS));
    }

    public function test_dgs_at_least_resp_service(): void
    {
        $this->assertTrue(UserRole::DGS->atLeast(UserRole::RESP_SERVICE));
    }

    public function test_resp_service_not_at_least_dgs(): void
    {
        $this->assertFalse(UserRole::RESP_SERVICE->atLeast(UserRole::DGS));
    }

    // ── from() / tryFrom() ────────────────────────────────

    public function test_from_string_valide(): void
    {
        $this->assertEquals(UserRole::ADMIN, UserRole::from('admin'));
        $this->assertEquals(UserRole::DGS, UserRole::from('dgs'));
        $this->assertEquals(UserRole::RESP_DIRECTION, UserRole::from('resp_direction'));
    }

    public function test_try_from_string_invalide_retourne_null(): void
    {
        $this->assertNull(UserRole::tryFrom('super_admin'));
        $this->assertNull(UserRole::tryFrom(''));
        $this->assertNull(UserRole::tryFrom('ADMIN')); // Sensible à la casse
    }

    // ── values() / rule() ─────────────────────────────────

    public function test_values_contient_tous_les_roles(): void
    {
        $values = UserRole::values();
        $this->assertContains('admin', $values);
        $this->assertContains('president', $values);
        $this->assertContains('dgs', $values);
        $this->assertContains('resp_direction', $values);
        $this->assertContains('resp_service', $values);
        $this->assertContains('user', $values);
        $this->assertCount(6, $values);
    }

    public function test_rule_format_correct(): void
    {
        $rule = UserRole::rule();
        $this->assertStringStartsWith('in:', $rule);
        $this->assertStringContainsString('admin', $rule);
        $this->assertStringContainsString('resp_direction', $rule);
    }

    // ── options() / label() ───────────────────────────────

    public function test_options_retourne_tableau_value_label(): void
    {
        $options = UserRole::options();
        $this->assertArrayHasKey('admin', $options);
        $this->assertEquals('Administrateur', $options['admin']);
        $this->assertCount(6, $options);
    }

    public function test_labels_en_francais(): void
    {
        $this->assertEquals('Administrateur', UserRole::ADMIN->label());
        $this->assertEquals('Président', UserRole::PRESIDENT->label());
        $this->assertEquals('Utilisateur', UserRole::USER->label());
    }
}
