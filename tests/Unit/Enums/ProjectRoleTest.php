<?php

namespace Tests\Unit\Enums;

use App\Enums\ProjectRole;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'enum ProjectRole.
 */
class ProjectRoleTest extends TestCase
{
    public function test_owner_peut_editer(): void
    {
        $this->assertTrue(ProjectRole::OWNER->canEdit());
    }

    public function test_member_peut_editer(): void
    {
        $this->assertTrue(ProjectRole::MEMBER->canEdit());
    }

    public function test_viewer_ne_peut_pas_editer(): void
    {
        $this->assertFalse(ProjectRole::VIEWER->canEdit());
    }

    public function test_seul_owner_peut_gerer(): void
    {
        $this->assertTrue(ProjectRole::OWNER->canManage());
        $this->assertFalse(ProjectRole::MEMBER->canManage());
        $this->assertFalse(ProjectRole::VIEWER->canManage());
    }

    public function test_labels_en_francais(): void
    {
        $this->assertEquals('Chef de projet', ProjectRole::OWNER->label());
        $this->assertEquals('Contributeur', ProjectRole::MEMBER->label());
        $this->assertEquals('Observateur', ProjectRole::VIEWER->label());
    }

    public function test_values_retourne_toutes_les_valeurs(): void
    {
        $values = ProjectRole::values();
        $this->assertContains('owner', $values);
        $this->assertContains('member', $values);
        $this->assertContains('viewer', $values);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
