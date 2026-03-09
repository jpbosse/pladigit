<?php

namespace Tests\Feature;

use App\Models\Tenant\Department;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\Share;
use App\Models\Tenant\User;
use App\Services\ShareService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests Feature — ShareService (résolution des droits de partage).
 *
 * Logique testée (par ordre de priorité) :
 *   1. Délégation utilisateur individuel
 *   2. Délégation nœud organisationnel direct
 *   3. Droit par rôle exact
 *   4. Héritage hiérarchique des rôles
 *   5. Héritage hiérarchique des nœuds (récursif)
 */
class ShareServiceTest extends TestCase
{
    private ShareService $service;
    private MediaAlbum $album;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ShareService::class);

        // Album restreint de référence pour tous les tests
        $this->album = MediaAlbum::factory()->create([
            'visibility' => 'restricted',
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('tenant')->table('shares')->delete();
        DB::connection('tenant')->table('user_department')->delete();
        DB::connection('tenant')->statement('DELETE FROM departments');
        DB::connection('tenant')->statement('DELETE FROM media_albums');
        DB::connection('tenant')->statement('DELETE FROM users');
        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function shareWith(string $type, mixed $id, array $abilities): void
    {
        Share::create([
            'shareable_type'   => 'media_album',
            'shareable_id'     => $this->album->id,
            'shared_with_type' => $type,
            'shared_with_id'   => $type === 'user' || $type === 'department' ? $id : null,
            'shared_with_role' => $type === 'role' ? $id : null,
            'can_view'         => $abilities['can_view']     ?? false,
            'can_download'     => $abilities['can_download'] ?? false,
            'can_edit'         => $abilities['can_edit']     ?? false,
            'can_manage'       => $abilities['can_manage']   ?? false,
        ]);
    }

    private function attachToDept(User $user, Department $dept, bool $isManager = false): void
    {
        DB::connection('tenant')->table('user_department')->insert([
            'user_id'       => $user->id,
            'department_id' => $dept->id,
            'is_manager'    => $isManager,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    // ── 1. Délégation utilisateur individuel ─────────────────────────────────

    public function test_utilisateur_avec_délégation_directe_peut_visionner(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->shareWith('user', $user->id, ['can_view' => true]);

        $this->assertTrue($this->service->can($user, $this->album, 'can_view'));
    }

    public function test_utilisateur_avec_délégation_directe_sans_droit_ne_peut_pas_visionner(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->shareWith('user', $user->id, ['can_view' => false]);

        $this->assertFalse($this->service->can($user, $this->album, 'can_view'));
    }

    public function test_délégation_individuelle_prime_sur_délégation_de_rôle(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        // Le rôle user peut visionner — mais l'override individuel dit non
        $this->shareWith('role', 'user', ['can_view' => true]);
        $this->shareWith('user', $user->id, ['can_view' => false]);

        $this->assertFalse($this->service->can($user, $this->album, 'can_view'));
    }

    // ── 2. Délégation nœud organisationnel direct ────────────────────────────

    public function test_utilisateur_membre_dun_nœud_délégué_peut_visionner(): void
    {
        $direction = Department::factory()->direction()->create();
        $user      = User::factory()->create(['role' => 'user']);
        $service   = Department::factory()->service($direction->id)->create();
        $this->attachToDept($user, $service);
        $this->shareWith('department', $service->id, ['can_view' => true]);

        $this->assertTrue($this->service->can($user, $this->album, 'can_view'));
    }

    public function test_utilisateur_non_membre_du_nœud_délégué_ne_peut_pas_visionner(): void
    {
        $direction = Department::factory()->direction()->create();
        $user      = User::factory()->create(['role' => 'user']);
        $service1  = Department::factory()->service($direction->id)->create();
        $service2  = Department::factory()->service($direction->id)->create();
        $this->attachToDept($user, $service1);
        $this->shareWith('department', $service2->id, ['can_view' => true]);

        $this->assertFalse($this->service->can($user, $this->album, 'can_view'));
    }

    // ── 3. Droit par rôle exact ───────────────────────────────────────────────

    public function test_utilisateur_dont_le_rôle_exact_a_un_droit_peut_visionner(): void
    {
        $user = User::factory()->create(['role' => 'resp_service']);
        $this->shareWith('role', 'resp_service', ['can_view' => true]);

        $this->assertTrue($this->service->can($user, $this->album, 'can_view'));
    }

    public function test_utilisateur_dont_le_rôle_na_pas_le_droit_ne_peut_pas_visionner(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->shareWith('role', 'resp_service', ['can_view' => true]);

        $this->assertFalse($this->service->can($user, $this->album, 'can_view'));
    }

    // ── 4. Héritage hiérarchique des rôles ───────────────────────────────────

    public function test_resp_direction_hérite_du_droit_accordé_à_resp_service(): void
    {
        $user = User::factory()->create(['role' => 'resp_direction']);
        $this->shareWith('role', 'resp_service', ['can_view' => true]);

        $this->assertTrue($this->service->can($user, $this->album, 'can_view'));
    }

    public function test_dgs_hérite_du_droit_accordé_à_resp_direction(): void
    {
        $user = User::factory()->create(['role' => 'dgs']);
        $this->shareWith('role', 'resp_direction', ['can_view' => true]);

        $this->assertTrue($this->service->can($user, $this->album, 'can_view'));
    }

    public function test_resp_service_nhérite_pas_du_droit_accordé_à_resp_direction(): void
    {
        // L'héritage ne remonte pas — il descend uniquement
        $user = User::factory()->create(['role' => 'resp_service']);
        $this->shareWith('role', 'resp_direction', ['can_view' => true]);

        $this->assertFalse($this->service->can($user, $this->album, 'can_view'));
    }

    // ── 5. Héritage hiérarchique des nœuds (récursif) ───────────────────────

    public function test_responsable_de_pôle_hérite_du_droit_accordé_à_sa_direction_enfant(): void
    {
        $pole      = Department::factory()->direction()->create(['name' => 'Pôle Technique']);
        $direction = Department::factory()->direction()->create([
            'name'      => 'Direction DST',
            'parent_id' => $pole->id,
        ]);

        $resp = User::factory()->create(['role' => 'resp_direction']);
        $this->attachToDept($resp, $pole, isManager: true);

        // La délégation est accordée à la Direction DST (enfant du Pôle)
        $this->shareWith('department', $direction->id, ['can_view' => true]);

        $this->assertTrue($this->service->can($resp, $this->album, 'can_view'));
    }

    public function test_responsable_de_pôle_hérite_du_droit_accordé_à_un_service_petit_enfant(): void
    {
        $pole      = Department::factory()->direction()->create(['name' => 'Pôle Technique']);
        $direction = Department::factory()->direction()->create([
            'name'      => 'Direction DST',
            'parent_id' => $pole->id,
        ]);
        $service   = Department::factory()->service($direction->id)->create([
            'name'      => 'Service Voirie',
            'parent_id' => $direction->id,
        ]);

        $resp = User::factory()->create(['role' => 'resp_direction']);
        $this->attachToDept($resp, $pole, isManager: true);

        // La délégation est accordée au Service Voirie (petit-enfant du Pôle)
        $this->shareWith('department', $service->id, ['can_view' => true]);

        $this->assertTrue($this->service->can($resp, $this->album, 'can_view'));
    }

    public function test_membre_simple_du_pôle_nhérite_pas_des_droits_des_enfants(): void
    {
        $pole      = Department::factory()->direction()->create();
        $direction = Department::factory()->direction()->create(['parent_id' => $pole->id]);

        // Membre simple — is_manager = false
        $user = User::factory()->create(['role' => 'resp_direction']);
        $this->attachToDept($user, $pole, isManager: false);

        $this->shareWith('department', $direction->id, ['can_view' => true]);

        $this->assertFalse($this->service->can($user, $this->album, 'can_view'));
    }

    public function test_délégation_accordée_au_pôle_nest_pas_héritée_par_les_membres_enfants(): void
    {
        $pole    = Department::factory()->direction()->create();
        $service = Department::factory()->service($pole->id)->create();

        // Agent simple dans le service enfant
        $user = User::factory()->create(['role' => 'user']);
        $this->attachToDept($user, $service);

        // La délégation est sur le Pôle — pas sur le service de l'agent
        $this->shareWith('department', $pole->id, ['can_view' => true]);

        $this->assertFalse($this->service->can($user, $this->album, 'can_view'));
    }

    // ── Sans aucun droit ─────────────────────────────────────────────────────

    public function test_utilisateur_sans_aucune_délégation_ne_peut_pas_accéder(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->assertFalse($this->service->can($user, $this->album, 'can_view'));
        $this->assertFalse($this->service->can($user, $this->album, 'can_download'));
        $this->assertFalse($this->service->can($user, $this->album, 'can_edit'));
        $this->assertFalse($this->service->can($user, $this->album, 'can_manage'));
    }
}
