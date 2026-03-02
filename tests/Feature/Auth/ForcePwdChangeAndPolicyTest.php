<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\TenantSettings;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePwdChangeAndPolicyTest extends TestCase
{

    private function settings(): TenantSettings
    {
        TenantSettings::truncate();
        return TenantSettings::factory()->create([
            'pwd_min_length'        => 12,
            'pwd_require_uppercase' => true,
            'pwd_require_number'    => true,
            'pwd_require_special'   => true,
            'pwd_history_count'     => 5,
            'login_max_attempts'    => 10,
            'login_lockout_minutes' => 15,
        ]);
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role'             => 'admin',
            'status'           => 'active',
            'password_hash'    => Hash::make('AdminPass!123'),
            'force_pwd_change' => false,
        ]);
    }

    public function test_force_pwd_change_redirige_vers_changement(): void
    {
        $user = User::factory()->create(['force_pwd_change' => true, 'status' => 'active']);
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change.forced'));
    }

    public function test_force_pwd_change_autorise_logout(): void
    {
        $user = User::factory()->create(['force_pwd_change' => true, 'status' => 'active']);
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_force_pwd_change_autorise_page_changement(): void
    {
        $user = User::factory()->create(['force_pwd_change' => true, 'status' => 'active']);
        $this->actingAs($user)->get(route('password.change.forced'))->assertOk();
    }

    public function test_apres_changement_acces_dashboard_accorde(): void
    {
        $this->settings();
        $user = User::factory()->create([
            'force_pwd_change' => true,
            'status'           => 'active',
            'password_hash'    => Hash::make('OldPassword!1'),
        ]);
        $this->actingAs($user)->post(route('password.change.forced.update'), [
            'current_password'      => 'OldPassword!1',
            'password'              => 'NewSecurePass!9',
            'password_confirmation' => 'NewSecurePass!9',
        ]);
        $user->refresh();
        $this->assertFalse((bool) $user->force_pwd_change);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_sans_force_pwd_change_acces_dashboard_normal(): void
    {
        $user = User::factory()->create(['force_pwd_change' => false, 'status' => 'active']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_creation_utilisateur_mot_de_passe_trop_court(): void
    {
        $this->settings();
        $admin = $this->adminUser();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Jean Dupont', 'email' => 'jean@test.fr', 'role' => 'user',
            'password' => 'Court!1', 'password_confirmation' => 'Court!1',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'jean@test.fr'], 'tenant');
    }

    public function test_creation_utilisateur_sans_majuscule_rejete(): void
    {
        $this->settings();
        $admin = $this->adminUser();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Jean Dupont', 'email' => 'jean2@test.fr', 'role' => 'user',
            'password' => 'minuscules!123', 'password_confirmation' => 'minuscules!123',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'jean2@test.fr'], 'tenant');
    }

    public function test_creation_utilisateur_conforme_force_pwd_change(): void
    {
        $this->settings();
        $admin = $this->adminUser();
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Jean Dupont', 'email' => 'conforme@test.fr', 'role' => 'user',
            'password' => 'SecurePass!123', 'password_confirmation' => 'SecurePass!123',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'conforme@test.fr', 'force_pwd_change' => true], 'tenant');
    }

    public function test_modification_mot_de_passe_trop_court_rejete(): void
    {
        $this->settings();
        $admin  = $this->adminUser();
        $target = User::factory()->create(['status' => 'active']);
        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => $target->name, 'role' => $target->role, 'status' => 'active',
            'password' => 'Court!1', 'password_confirmation' => 'Court!1',
        ])->assertSessionHasErrors('password');
    }

    public function test_modification_sans_mot_de_passe_passe(): void
    {
        $this->settings();
        $admin  = $this->adminUser();
        $target = User::factory()->create(['status' => 'active', 'role' => 'user']);
        $this->actingAs($admin)->put(route('admin.users.update', $target), [
            'name' => 'Nouveau Nom', 'role' => 'user', 'status' => 'active',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Nouveau Nom'], 'tenant');
    }
}
