<?php

namespace Tests\Feature\Admin;

use App\Models\Platform\Organization;
use App\Models\Tenant\User;
use App\Services\TenantMailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantMailerTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        DB::connection('tenant')->table('users')->delete();
        DB::connection('mysql')->table('organizations')->delete();
        parent::tearDown();
    }

    public function test_is_configured_false_when_no_host(): void
    {
        $org = new Organization(['smtp_host' => null, 'smtp_user' => null, 'smtp_password_enc' => null]);
        $this->assertFalse((new TenantMailer)->isConfigured($org));
    }

    public function test_is_configured_false_when_missing_password(): void
    {
        $org = new Organization([
            'smtp_host' => 'smtp.example.com',
            'smtp_user' => 'user@example.com',
            'smtp_password_enc' => null,
        ]);
        $this->assertFalse((new TenantMailer)->isConfigured($org));
    }

    public function test_is_configured_true_when_all_fields_present(): void
    {
        $org = new Organization([
            'smtp_host' => 'smtp.example.com',
            'smtp_user' => 'user@example.com',
            'smtp_password_enc' => Crypt::encryptString('secret123'),
        ]);
        $this->assertTrue((new TenantMailer)->isConfigured($org));
    }

    public function test_configure_sets_smtp_config(): void
    {
        $org = new Organization([
            'smtp_host' => 'smtp.mairie.fr',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_user' => 'noreply@mairie.fr',
            'smtp_password_enc' => Crypt::encryptString('monsecret'),
            'smtp_from_address' => 'noreply@mairie.fr',
            'smtp_from_name' => 'Mairie de Test',
        ]);

        (new TenantMailer)->configureForTenant($org);

        $this->assertEquals('smtp.mairie.fr', Config::get('mail.mailers.smtp.host'));
        $this->assertEquals(587, Config::get('mail.mailers.smtp.port'));
        $this->assertEquals('noreply@mairie.fr', Config::get('mail.mailers.smtp.username'));
        $this->assertEquals('noreply@mairie.fr', Config::get('mail.from.address'));
        $this->assertEquals('Mairie de Test', Config::get('mail.from.name'));
    }

    public function test_configure_resolves_smtps_scheme(): void
    {
        $org = new Organization([
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'smtps',
            'smtp_user' => 'user@gmail.com',
            'smtp_password_enc' => Crypt::encryptString('apppassword'),
            'smtp_from_address' => 'user@gmail.com',
            'smtp_from_name' => 'Test',
        ]);

        (new TenantMailer)->configureForTenant($org);

        $this->assertEquals('smtps', Config::get('mail.mailers.smtp.scheme'));
    }

    public function test_configure_resolves_starttls_as_smtp_scheme(): void
    {
        $org = new Organization([
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_user' => 'u@example.com',
            'smtp_password_enc' => Crypt::encryptString('pass'),
            'smtp_from_address' => 'u@example.com',
            'smtp_from_name' => 'Test',
        ]);

        (new TenantMailer)->configureForTenant($org);

        $this->assertEquals('smtp', Config::get('mail.mailers.smtp.scheme'));
    }

    public function test_configure_does_nothing_when_not_configured(): void
    {
        $originalHost = Config::get('mail.mailers.smtp.host');
        $org = new Organization(['smtp_host' => null, 'smtp_user' => null, 'smtp_password_enc' => null]);

        (new TenantMailer)->configureForTenant($org);

        $this->assertEquals($originalHost, Config::get('mail.mailers.smtp.host'));
    }

    public function test_configure_does_nothing_on_invalid_ciphertext(): void
    {
        $originalHost = Config::get('mail.mailers.smtp.host');
        $org = new Organization([
            'smtp_host' => 'smtp.example.com',
            'smtp_user' => 'u@example.com',
            'smtp_password_enc' => 'INVALID_CIPHERTEXT',
        ]);

        (new TenantMailer)->configureForTenant($org);

        $this->assertEquals($originalHost, Config::get('mail.mailers.smtp.host'));
    }

    public function test_admin_can_save_smtp_settings(): void
    {
        $org = Organization::create([
            'slug' => 'test',
            'name' => 'Test Org',
            'db_name' => env('DB_TENANT_DATABASE', 'pladigit_testing_tenant'),
            'status' => 'active',
            'plan' => 'communautaire',
        ]);
        // Pointer le TenantManager vers l'org persistée en base
        app(\App\Services\TenantManager::class)->connectTo($org);

        $response = $this->actingAs($this->admin)
            ->put(route('admin.settings.smtp.update'), [
                'smtp_host' => 'smtp.mairie.fr',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_user' => 'noreply@mairie.fr',
                'smtp_password' => 'supersecret',
                'smtp_from_address' => 'noreply@mairie.fr',
                'smtp_from_name' => 'Mairie de Test',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $org->refresh();
        $this->assertEquals('smtp.mairie.fr', $org->smtp_host);
        $this->assertEquals('tls', $org->smtp_encryption);
        $this->assertNotNull($org->smtp_password_enc);
        $this->assertEquals('supersecret', Crypt::decryptString($org->smtp_password_enc));
    }

    public function test_smtp_password_unchanged_when_field_empty(): void
    {
        $enc = Crypt::encryptString('ancienmdp');
        $org = Organization::create([
            'slug' => 'test',
            'name' => 'Test Org',
            'db_name' => env('DB_TENANT_DATABASE', 'pladigit_testing_tenant'),
            'status' => 'active',
            'plan' => 'communautaire',
            'smtp_host' => 'old.smtp.fr',
            'smtp_user' => 'u@old.fr',
            'smtp_password_enc' => $enc,
        ]);
        // Pointer le TenantManager vers l'org persistée en base
        app(\App\Services\TenantManager::class)->connectTo($org);

        $this->actingAs($this->admin)
            ->put(route('admin.settings.smtp.update'), [
                'smtp_host' => 'new.smtp.fr',
                'smtp_port' => 465,
                'smtp_encryption' => 'smtps',
                'smtp_user' => 'u@new.fr',
                'smtp_password' => '',
                'smtp_from_address' => 'a@b.fr',
                'smtp_from_name' => 'X',
            ]);

        $org->refresh();
        $this->assertEquals($enc, $org->smtp_password_enc); // inchangé
        $this->assertEquals('new.smtp.fr', $org->smtp_host);
    }

    public function test_non_admin_cannot_save_smtp_settings(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($user)
            ->put(route('admin.settings.smtp.update'), ['smtp_host' => 'smtp.evil.com'])
            ->assertForbidden();
    }
}
