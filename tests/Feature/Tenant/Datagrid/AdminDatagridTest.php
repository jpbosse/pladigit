<?php

namespace Tests\Feature\Tenant\Datagrid;

use App\Enums\DatagridColumnType;
use App\Enums\ModuleKey;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridSavedView;
use App\Models\Tenant\DatagridTable;
use App\Models\Tenant\User;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminDatagridTest extends TestCase
{
    private User $admin;
    private User $user;
    private DatagridTable $table;
    private string $mysqlTable = 'dg_test_admin';

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantManager::class)->current()->enableModule(ModuleKey::DATAGRID);

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->user  = User::factory()->create(['role' => 'user',  'status' => 'active']);

        $this->table = DatagridTable::create([
            'name'        => 'test_admin',
            'label'       => 'Grille Admin Test',
            'mysql_table' => $this->mysqlTable,
            'has_rgpd'    => false,
            'created_by'  => $this->admin->id,
        ]);

        DatagridColumn::create([
            'datagrid_table_id'  => $this->table->id,
            'name'               => 'nom',
            'label'              => 'Nom',
            'type'               => DatagridColumnType::TEXT,
            'required'           => true,
            'visible_by_default' => true,
            'is_rgpd_sensitive'  => false,
            'is_role_column'     => false,
            'sort_order'         => 0,
        ]);
        DatagridColumn::create([
            'datagrid_table_id'  => $this->table->id,
            'name'               => 'email',
            'label'              => 'Email',
            'type'               => DatagridColumnType::EMAIL,
            'required'           => false,
            'visible_by_default' => true,
            'is_rgpd_sensitive'  => false,
            'is_role_column'     => false,
            'sort_order'         => 1,
        ]);

        Schema::connection('tenant')->create($this->mysqlTable, function ($t) {
            $t->id();
            $t->string('nom', 255);
            $t->string('email', 255)->nullable();
            $t->timestamps();
        });

        DB::connection('tenant')->table($this->mysqlTable)->insert([
            ['nom' => 'Alice', 'email' => 'alice@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->table)) {
            DatagridSavedView::where('datagrid_table_id', $this->table->id)->delete();
            DatagridColumn::where('datagrid_table_id', $this->table->id)->forceDelete();
            $this->table->forceDelete();
        }
        Schema::connection('tenant')->dropIfExists($this->mysqlTable);
        parent::tearDown();
    }

    // ── Contrôle d'accès ──────────────────────────────────────────────────────

    public function test_utilisateur_standard_ne_peut_pas_acceder_au_panneau_admin(): void
    {
        $this->actingAs($this->user, 'tenant')
            ->get(route('admin.datagrid.index'))
            ->assertForbidden();
    }

    public function test_admin_peut_lister_les_grilles(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->get(route('admin.datagrid.index'))
            ->assertOk()
            ->assertSee('Grille Admin Test');
    }

    public function test_admin_peut_acceder_au_formulaire_edit(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->get(route('admin.datagrid.edit', $this->table))
            ->assertOk()
            ->assertSee('Grille Admin Test')
            ->assertSee('nom')
            ->assertSee('email');
    }

    // ── Modification table ────────────────────────────────────────────────────

    public function test_admin_peut_modifier_le_label_de_la_grille(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.update', $this->table), [
                'label'    => 'Nouveau Label',
                'has_rgpd' => false,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('datagrid_tables', [
            'id'    => $this->table->id,
            'label' => 'Nouveau Label',
        ], 'tenant');
    }

    // ── Gestion des colonnes ──────────────────────────────────────────────────

    public function test_admin_peut_supprimer_une_colonne(): void
    {
        $column = $this->table->columns()->where('name', 'email')->first();

        $this->actingAs($this->admin, 'tenant')
            ->deleteJson(route('datagrid.columns.destroy', [$this->table, $column]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('datagrid_columns', [
            'id' => $column->id,
        ], 'tenant');

        // Vérifier que la colonne a été droppée de la table MySQL
        $cols = DB::connection('tenant')
            ->getSchemaBuilder()
            ->getColumnListing($this->mysqlTable);
        $this->assertNotContains('email', $cols);
    }

    // ── Changement de type (via datagrid.columns.update) ─────────────────────

    public function test_alter_type_compatible_en_mode_admin(): void
    {
        $column = $this->table->columns()->where('name', 'nom')->first();

        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('datagrid.columns.update', [$this->table, $column]), [
                'label'              => 'Nom',
                'visible_by_default' => true,
                'required'           => true,
                'is_rgpd_sensitive'  => false,
                'sort_order'         => 0,
                'type'               => 'email',
                'length'             => null,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $column->refresh();
        $this->assertEquals(DatagridColumnType::EMAIL, $column->type);
    }

    public function test_alter_type_incompatible_retourne_422_en_mode_admin(): void
    {
        $column = $this->table->columns()->where('name', 'nom')->first();

        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('datagrid.columns.update', [$this->table, $column]), [
                'label'              => 'Nom',
                'visible_by_default' => true,
                'required'           => true,
                'is_rgpd_sensitive'  => false,
                'sort_order'         => 0,
                'type'               => 'date',
                'length'             => null,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Type incompatible avec les données existantes']);
    }

    // ── Suppression de la grille ──────────────────────────────────────────────

    public function test_admin_peut_supprimer_la_grille(): void
    {
        $tableId    = $this->table->id;
        $mysqlTable = $this->mysqlTable;

        $this->actingAs($this->admin, 'tenant')
            ->delete(route('admin.datagrid.destroy', $this->table))
            ->assertRedirect(route('admin.datagrid.index'));

        $this->assertDatabaseMissing('datagrid_tables', ['id' => $tableId], 'tenant');
        $this->assertFalse(Schema::connection('tenant')->hasTable($mysqlTable));

        // Éviter double-drop dans tearDown
        unset($this->table);
    }
}
