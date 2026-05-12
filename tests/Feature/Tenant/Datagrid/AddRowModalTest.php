<?php

namespace Tests\Feature\Tenant\Datagrid;

use App\Enums\DatagridColumnType;
use App\Enums\ModuleKey;
use App\Livewire\Tenant\Datagrid\AddRowModal;
use App\Models\Tenant\DatagridAuditLog;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridTable;
use App\Models\Tenant\User;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tests du composant AddRowModal.
 *
 * Couvre :
 *   - Ouverture et fermeture de la modal
 *   - Insertion d'une nouvelle ligne
 *   - Audit log à la création
 *   - Événement row-added émis
 *   - Contrôle des droits (can_write)
 *   - Validation des champs requis
 */
class AddRowModalTest extends TestCase
{
    private User $admin;

    private DatagridTable $table;

    private string $mysqlTable = 'dg_test_add_modal';

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantManager::class)->current()->enableModule(ModuleKey::DATAGRID);

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->table = DatagridTable::create([
            'name' => 'test_add_modal',
            'label' => 'Test Ajout',
            'mysql_table' => $this->mysqlTable,
            'has_rgpd' => true,
            'created_by' => $this->admin->id,
        ]);

        DatagridColumn::create([
            'datagrid_table_id' => $this->table->id,
            'name' => 'nom',
            'label' => 'Nom',
            'type' => DatagridColumnType::TEXT,
            'required' => true,
            'visible_by_default' => true,
            'is_rgpd_sensitive' => false,
            'is_role_column' => false,
            'sort_order' => 0,
        ]);

        DatagridColumn::create([
            'datagrid_table_id' => $this->table->id,
            'name' => 'email',
            'label' => 'Email',
            'type' => DatagridColumnType::EMAIL,
            'required' => false,
            'visible_by_default' => true,
            'is_rgpd_sensitive' => false,
            'is_role_column' => false,
            'sort_order' => 1,
        ]);

        Schema::connection('tenant')->create($this->mysqlTable, function ($t) {
            $t->id();
            $t->string('nom', 255);
            $t->string('email', 255)->nullable();
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->table)) {
            DatagridAuditLog::where('datagrid_table_id', $this->table->id)->forceDelete();
            DatagridColumn::where('datagrid_table_id', $this->table->id)->forceDelete();
            $this->table->forceDelete();
        }
        Schema::connection('tenant')->dropIfExists($this->mysqlTable);
        parent::tearDown();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeComponent(): Testable
    {
        return Livewire::actingAs($this->admin, 'tenant')
            ->test(AddRowModal::class, [
                'table' => $this->table,
                'userPerms' => ['can_write' => true],
            ]);
    }

    // ── Ouverture / fermeture ─────────────────────────────────────────────────

    public function test_modal_fermee_par_defaut(): void
    {
        $this->makeComponent()
            ->assertSet('open', false);
    }

    public function test_open_add_ouvre_la_modal(): void
    {
        $this->makeComponent()
            ->dispatch('open-add-modal')
            ->assertSet('open', true);
    }

    public function test_open_add_initialise_le_formulaire(): void
    {
        $component = $this->makeComponent()
            ->dispatch('open-add-modal');

        $form = $component->get('addForm');
        $this->assertArrayHasKey('nom', $form);
        $this->assertArrayHasKey('email', $form);
        $this->assertEquals('', $form['nom']);
    }

    public function test_close_add_ferme_la_modal(): void
    {
        $this->makeComponent()
            ->dispatch('open-add-modal')
            ->call('closeAdd')
            ->assertSet('open', false)
            ->assertSet('addForm', []);
    }

    // ── Sauvegarde ────────────────────────────────────────────────────────────

    public function test_save_add_insere_la_ligne(): void
    {
        $this->makeComponent()
            ->dispatch('open-add-modal')
            ->set('addForm.nom', 'Charlie')
            ->set('addForm.email', 'charlie@example.com')
            ->call('saveAdd');

        $this->assertDatabaseHas($this->mysqlTable, [
            'nom' => 'Charlie',
            'email' => 'charlie@example.com',
        ], 'tenant');
    }

    public function test_save_add_cree_un_audit_log(): void
    {
        $this->makeComponent()
            ->dispatch('open-add-modal')
            ->set('addForm.nom', 'David')
            ->set('addForm.email', 'david@example.com')
            ->call('saveAdd');

        $newId = DB::connection('tenant')
            ->table($this->mysqlTable)
            ->where('nom', 'David')
            ->value('id');

        $this->assertDatabaseHas('datagrid_audit_log', [
            'datagrid_table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'row_id' => $newId,
            'action' => 'write',
            'column_name' => null,
        ], 'tenant');
    }

    public function test_save_add_emet_row_added(): void
    {
        $this->makeComponent()
            ->dispatch('open-add-modal')
            ->set('addForm.nom', 'Eve')
            ->call('saveAdd')
            ->assertDispatched('row-added');
    }

    public function test_save_add_ferme_la_modal_apres_insertion(): void
    {
        $this->makeComponent()
            ->dispatch('open-add-modal')
            ->set('addForm.nom', 'Frank')
            ->call('saveAdd')
            ->assertSet('open', false);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_save_add_valide_champ_requis(): void
    {
        $this->makeComponent()
            ->dispatch('open-add-modal')
            ->set('addForm.nom', '')
            ->call('saveAdd')
            ->assertHasErrors(['addForm.nom']);
    }

    public function test_save_add_valide_email(): void
    {
        $this->makeComponent()
            ->dispatch('open-add-modal')
            ->set('addForm.nom', 'Test')
            ->set('addForm.email', 'pas-un-email')
            ->call('saveAdd')
            ->assertHasErrors(['addForm.email']);
    }

    // ── Droits ────────────────────────────────────────────────────────────────

    public function test_save_add_bloque_sans_can_write(): void
    {
        // On force l'état open=true pour bypasser openAdd() et tester saveAdd() directement
        Livewire::actingAs($this->admin, 'tenant')
            ->test(AddRowModal::class, [
                'table' => $this->table,
                'userPerms' => ['can_write' => false],
            ])
            ->set('open', true)
            ->set('addForm.nom', 'Tentative')
            ->call('saveAdd');

        // Aucune ligne ne doit avoir été insérée
        $this->assertDatabaseMissing($this->mysqlTable, ['nom' => 'Tentative'], 'tenant');
    }
}
