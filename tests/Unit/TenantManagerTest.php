<?php

namespace Tests\Unit;

use App\Models\Platform\Organization;
use App\Services\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_name_from_slug(): void
    {
        $this->assertEquals(
            'pladigit_mairie_olonne',
            Organization::dbNameFromSlug('mairie-olonne')
        );
    }

    public function test_tenant_manager_has_no_tenant_initially(): void
    {
        $manager = new TenantManager;
        $this->assertFalse($manager->hasTenant());
        $this->assertNull($manager->current());
    }

    public function test_current_or_fail_throws_without_tenant(): void
    {
        $this->expectException(\RuntimeException::class);
        $manager = new TenantManager;
        $manager->currentOrFail();
    }
}
