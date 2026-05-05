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

class ShowGridTest extends TestCase
{
    private User $admin;
    private DatagridTable $table;
    private string $mysqlTable = 'dg_test_show';

    protected function setUp(): void
    {
        parent::setUp();

        // Activer le module datagrid sur l'objet en mémoire (le middleware lit TenantManager::current())
        app(TenantManager::class)->current()->enableModule(ModuleKey::DATAGRID);

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        // Créer la DatagridTable
        $this->table = DatagridTable::create([
            'name'       => 'test_show',
            'label'      => 'Test Grille',
            'mysql_table' => $this->mysqlTable,
            'has_rgpd'   => false,
            'created_by' => $this->admin->id,
        ]);

        // Créer les colonnes
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

        // Créer la table MySQL tenant
        Schema::connection('tenant')->create($this->mysqlTable, function ($table) {
            $table->id();
            $table->string('nom', 255);
            $table->string('email', 255)->nullable();
            $table->timestamps();
        });

        // Insérer des données
        DB::connection('tenant')->table($this->mysqlTable)->insert([
            ['nom' => 'Alice', 'email' => 'alice@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['nom' => 'Bob',   'email' => 'bob@example.com',   'created_at' => now(), 'updated_at' => now()],
            ['nom' => 'Carla', 'email' => 'carla@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        // Le DDL (CREATE/DROP TABLE) cause un commit implicite MySQL → la transaction ne rollback pas.
        // On supprime explicitement les enregistrements tenant avant de droper la table physique.
        if (isset($this->table)) {
            DatagridSavedView::where('datagrid_table_id', $this->table->id)->delete();
            DatagridColumn::where('datagrid_table_id', $this->table->id)->forceDelete();
            $this->table->forceDelete();
        }
        Schema::connection('tenant')->dropIfExists($this->mysqlTable);
        parent::tearDown();
    }

    // ── show() ────────────────────────────────────────────────────────────────

    public function test_affiche_les_lignes_de_la_table(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->get(route('datagrid.show', $this->table))
            ->assertOk()
            ->assertSee('Alice')
            ->assertSee('Bob')
            ->assertSee('Carla');
    }

    public function test_filtre_par_colonne(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->get(route('datagrid.show', $this->table) . '?filters[nom]=Alice')
            ->assertOk()
            ->assertSee('Alice')
            ->assertDontSee('Bob');
    }

    public function test_tri_par_colonne(): void
    {
        $response = $this->actingAs($this->admin, 'tenant')
            ->get(route('datagrid.show', $this->table) . '?sort=nom&direction=asc')
            ->assertOk();

        $content = $response->getContent();
        $posAlice = strpos($content, 'Alice');
        $posBob   = strpos($content, 'Bob');
        $posCarla = strpos($content, 'Carla');

        $this->assertLessThan($posBob, $posAlice);
        $this->assertLessThan($posCarla, $posBob);
    }

    // ── storeView() ───────────────────────────────────────────────────────────

    public function test_sauvegarde_vue(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->postJson(route('datagrid.views.store', $this->table), [
                'name'    => 'Ma vue test',
                'filters' => ['nom' => 'Ali'],
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Ma vue test']);

        $this->assertDatabaseHas('datagrid_saved_views', [
            'datagrid_table_id' => $this->table->id,
            'user_id'           => $this->admin->id,
            'name'              => 'Ma vue test',
        ], 'tenant');
    }

    // ── updateColumn() ────────────────────────────────────────────────────────

    public function test_modification_label_colonne(): void
    {
        $column = $this->table->columns()->where('name', 'nom')->first();

        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('datagrid.columns.update', [$this->table, $column]), [
                'label'              => 'Nom complet',
                'visible_by_default' => true,
                'required'           => true,
                'is_rgpd_sensitive'  => false,
                'sort_order'         => 0,
                'type'               => 'text',
                'length'             => null,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('datagrid_columns', [
            'id'    => $column->id,
            'label' => 'Nom complet',
        ], 'tenant');
    }

    public function test_alter_type_compatible(): void
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

    public function test_alter_type_incompatible_retourne_422(): void
    {
        $column = $this->table->columns()->where('name', 'nom')->first();

        // TEXT → DATE est incompatible car les valeurs existantes ("Alice", etc.) ne sont pas des dates
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
}
