<?php

namespace Tests\Feature\SuperAdmin;

use Tests\TestCase;

class DatagridControllerTest extends TestCase
{
    private function actingAsSuperAdmin()
    {
        return $this->withSession([
            'super_admin_email' => config('superadmin.email'),
            'super_admin_verified' => true,
        ]);
    }

    public function test_invité_ne_peut_pas_accéder_aux_datagrids(): void
    {
        $this->get(route('super-admin.datagrids.index'))
            ->assertRedirect();
    }

    public function test_super_admin_peut_voir_la_liste_datagrids(): void
    {
        $this->actingAsSuperAdmin()
            ->get(route('super-admin.datagrids.index'))
            ->assertOk()
            ->assertViewIs('super-admin.datagrids.index')
            ->assertViewHas('rows');
    }
}
