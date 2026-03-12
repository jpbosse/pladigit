<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_retourne_200_quand_tout_va_bien(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure([
                'status', 'ts',
                'checks' => [
                    'database' => ['ok', 'message'],
                    'redis' => ['ok', 'message'],
                    'disk' => ['ok', 'message', 'free_percent', 'free_gb'],
                ],
            ]);
    }

    public function test_health_database_ok(): void
    {
        $this->getJson('/health')->assertOk()
            ->assertJsonPath('checks.database.ok', true);
    }

    public function test_health_redis_ok(): void
    {
        $this->getJson('/health')->assertOk()
            ->assertJsonPath('checks.redis.ok', true);
    }

    public function test_health_disk_ok(): void
    {
        $this->getJson('/health')->assertOk()
            ->assertJsonPath('checks.disk.ok', true);
    }

    public function test_ping_retourne_ok_texte(): void
    {
        $this->get('/health/ping')->assertOk()->assertSee('OK');
    }

    public function test_health_accessible_sans_authentification(): void
    {
        $this->getJson('/health')->assertOk();
        $this->get('/health/ping')->assertOk();
    }
}
