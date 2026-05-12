<?php

namespace Tests\Feature\Tenant\Datagrid;

use App\Enums\DatagridColumnType;
use App\Enums\ModuleKey;
use App\Livewire\Tenant\Datagrid\EditRowModal;
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
 * Tests du composant EditRowModal.
 *
 * Couvre :
 *   - Ouverture et fermeture de la modal
 *   - Onglets Données / Complémentaires / Historique
 *   - Sauvegarde avec audit log
 *   - Suppression avec audit log
 *   - Contrôle des droits (can_write, can_delete)
 */
class EditRowModalTest extends TestCase
{
    private User $admin;

    private DatagridTable $table;

    private string $mysqlTable = 'dg_test_edit_modal';

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantManager::class)->current()->enableModule(ModuleKey::DATAGRID);

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->table = DatagridTable::create([
            'name' => 'test_edit_modal',
            'label' => 'Test Modal',
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
            'tab' => 'main',
        ]);

        DatagridColumn::create([
            'datagrid_table_id' => $this->table->id,
            'name' => 'notes',
            'label' => 'Notes',
            'type' => DatagridColumnType::TEXT,
            'required' => false,
            'visible_by_default' => false,
            'is_rgpd_sensitive' => false,
            'is_role_column' => false,
            'sort_order' => 1,
            'tab' => 'extra',
        ]);

        Schema::connection('tenant')->create($this->mysqlTable, function ($t) {
            $t->id();
            $t->string('nom', 255);
            $t->string('notes', 255)->nullable();
            $t->timestamps();
        });

        DB::connection('tenant')->table($this->mysqlTable)->insert([
            ['nom' => 'Alice', 'notes' => 'Note Alice', 'created_at' => now(), 'updated_at' => now()],
            ['nom' => 'Bob',   'notes' => null,          'created_at' => now(), 'updated_at' => now()],
        ]);
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

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function rowId(string $nom): int
    {
        return (int) DB::connection('tenant')
            ->table($this->mysqlTable)
            ->where('nom', $nom)
            ->value('id');
    }

    private function makeComponent(): Testable
    {
        return Livewire::actingAs($this->admin, 'tenant')
            ->test(EditRowModal::class, [
                'table' => $this->table,
                'userPerms' => ['can_write' => true, 'can_delete' => true],
            ]);
    }

    // ── Ouverture / fermeture ─────────────────────────────────────────────────

    public function test_modal_fermee_par_defaut(): void
    {
        $this->makeComponent()
            ->assertSet('rowId', null);
    }

    public function test_open_edit_charge_la_ligne(): void
    {
        $id = $this->rowId('Alice');

        $this->makeComponent()
            ->dispatch('open-edit-modal', rowId: $id)
            ->assertSet('rowId', $id)
            ->assertSet('activeTab', 'main');
    }

    public function test_close_edit_remet_a_zero(): void
    {
        $id = $this->rowId('Alice');

        $this->makeComponent()
            ->dispatch('open-edit-modal', rowId: $id)
            ->call('closeEdit')
            ->assertSet('rowId', null)
            ->assertSet('editForm', []);
    }

    // ── Onglets ───────────────────────────────────────────────────────────────

    public function test_switch_tab_main(): void
    {
        $id = $this->rowId('Alice');

        $this->makeComponent()
            ->dispatch('open-edit-modal', rowId: $id)
            ->call('switchTab', 'extra')
            ->call('switchTab', 'main')
            ->assertSet('activeTab', 'main');
    }

    public function test_switch_tab_extra(): void
    {
        $id = $this->rowId('Alice');

        $this->makeComponent()
            ->dispatch('open-edit-modal', rowId: $id)
            ->call('switchTab', 'extra')
            ->assertSet('activeTab', 'extra');
    }

    public function test_switch_tab_history_charge_entrees(): void
    {
        $id = $this->rowId('Alice');

        // Créer un log d'audit pour cet enregistrement
        DatagridAuditLog::create([
            'datagrid_table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'action' => 'write',
            'row_id' => $id,
            'column_name' => 'nom',
            'old_value' => 'Ancienne valeur',
            'new_value' => 'Alice',
            'ip_address' => '127.0.0.1',
        ]);

        $component = $this->makeComponent()
            ->dispatch('open-edit-modal', rowId: $id)
            ->call('switchTab', 'history')
            ->assertSet('activeTab', 'history');

        $this->assertNotEmpty($component->get('historyEntries'));
    }

    // ── Sauvegarde ────────────────────────────────────────────────────────────

    public function test_save_edit_modifie_la_ligne(): void
    {
        $id = $this->rowId('Alice');

        $this->makeComponent()
            ->dispatch('open-edit-modal', rowId: $id)
            ->set('editForm.nom', 'Alice Modifiée')
            ->call('saveEdit')
            ->assertSet('rowId', null); // modal fermée après save

        $this->assertDatabaseHas($this->mysqlTable, [
            'id' => $id,
            'nom' => 'Alice Modifiée',
        ], 'tenant');
    }

    public function test_save_edit_cree_un_audit_log(): void
    {
        $id = $this->rowId('Alice');

        $this->makeComponent()
            ->dispatch('open-edit-modal', rowId: $id)
            ->set('editForm.nom', 'Alice Auditée')
            ->call('saveEdit');

        $this->assertDatabaseHas('datagrid_audit_log', [
            'datagrid_table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'row_id' => $id,
            'column_name' => 'nom',
            'old_value' => 'Alice',
            'new_value' => 'Alice Auditée',
        ], 'tenant');
    }

    public function test_save_edit_emet_row_updated(): void
    {
        $id = $this->rowId('Alice');

        $this->makeComponent()
            ->dispatch('open-edit-modal', rowId: $id)
            ->set('editForm.nom', 'Alice Updated')
            ->call('saveEdit')
            ->assertDispatched('row-updated');
    }

    public function test_save_edit_bloque_sans_can_write(): void
    {
        $id = $this->rowId('Alice');

        Livewire::actingAs($this->admin, 'tenant')
            ->test(EditRowModal::class, [
                'table' => $this->table,
                'userPerms' => ['can_write' => false, 'can_delete' => false],
            ])
            ->dispatch('open-edit-modal', rowId: $id)
            ->set('editForm.nom', 'Tentative')
            ->call('saveEdit');

        // La ligne ne doit pas avoir été modifiée
        $this->assertDatabaseHas($this->mysqlTable, [
            'id' => $id,
            'nom' => 'Alice',
        ], 'tenant');
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function test_delete_row_supprime_la_ligne(): void
    {
        $id = $this->rowId('Bob');

        $this->makeComponent()
            ->dispatch('open-edit-modal', rowId: $id)
            ->call('deleteRow')
            ->assertSet('rowId', null);

        $this->assertDatabaseMissing($this->mysqlTable, ['id' => $id], 'tenant');
    }

    public function test_delete_row_cree_un_audit_log(): void
    {
        $id = $this->rowId('Bob');

        $this->makeComponent()
            ->dispatch('open-edit-modal', rowId: $id)
            ->call('deleteRow');

        $this->assertDatabaseHas('datagrid_audit_log', [
            'datagrid_table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'row_id' => $id,
            'action' => 'delete',
            'column_name' => null,
        ], 'tenant');
    }

    public function test_delete_row_emet_row_deleted(): void
    {
        $id = $this->rowId('Bob');

        $this->makeComponent()
            ->dispatch('open-edit-modal', rowId: $id)
            ->call('deleteRow')
            ->assertDispatched('row-deleted');
    }

    public function test_delete_row_bloque_sans_can_delete(): void
    {
        $id = $this->rowId('Bob');

        Livewire::actingAs($this->admin, 'tenant')
            ->test(EditRowModal::class, [
                'table' => $this->table,
                'userPerms' => ['can_write' => true, 'can_delete' => false],
            ])
            ->dispatch('open-edit-modal', rowId: $id)
            ->call('deleteRow');

        // La ligne ne doit pas avoir été supprimée
        $this->assertDatabaseHas($this->mysqlTable, ['id' => $id], 'tenant');
    }
}
