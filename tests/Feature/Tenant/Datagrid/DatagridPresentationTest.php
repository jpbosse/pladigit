<?php

namespace Tests\Feature\Tenant\Datagrid;

use App\Enums\DatagridColumnType;
use App\Enums\ModuleKey;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridTable;
use App\Models\Tenant\User;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests pour les tâches 2.15, 2.16 et 2.17.
 *
 * 2.15 — Réordonnancement des colonnes par l'admin tenant
 * 2.16 — Tri par défaut configurable par grille
 * 2.17 — Colonne numéro de ligne (optionnelle)
 */
class DatagridPresentationTest extends TestCase
{
    private User $admin;

    private User $user;

    private DatagridTable $table;

    private string $mysqlTable = 'dg_test_presentation';

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantManager::class)->current()->enableModule(ModuleKey::DATAGRID);

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->user = User::factory()->create(['role' => 'user',  'status' => 'active']);

        $this->table = DatagridTable::create([
            'name' => 'test_presentation',
            'label' => 'Grille Présentation Test',
            'mysql_table' => $this->mysqlTable,
            'has_rgpd' => false,
            'created_by' => $this->admin->id,
        ]);

        foreach ([
            ['nom',    'Nom',    DatagridColumnType::TEXT,   0],
            ['prenom', 'Prénom', DatagridColumnType::TEXT,   1],
            ['age',    'Âge',    DatagridColumnType::NUMBER, 2],
        ] as [$name, $label, $type, $order]) {
            DatagridColumn::create([
                'datagrid_table_id' => $this->table->id,
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'required' => false,
                'visible_by_default' => true,
                'is_rgpd_sensitive' => false,
                'is_role_column' => false,
                'sort_order' => $order,
            ]);
        }

        Schema::connection('tenant')->create($this->mysqlTable, function ($t) {
            $t->id();
            $t->string('nom', 255)->nullable();
            $t->string('prenom', 255)->nullable();
            $t->decimal('age', 15, 4)->nullable();
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->table)) {
            DatagridColumn::where('datagrid_table_id', $this->table->id)->forceDelete();
            $this->table->forceDelete();
        }
        Schema::connection('tenant')->dropIfExists($this->mysqlTable);
        parent::tearDown();
    }

    // ── 2.15 — Réordonnancement des colonnes ─────────────────────────────────

    public function test_admin_peut_reordonner_les_colonnes(): void
    {
        $cols = $this->table->columns()->orderBy('sort_order')->get();
        // Ordre inversé : age(2) → prenom(1) → nom(0)
        $newOrder = [$cols->where('name', 'age')->first()->id,
            $cols->where('name', 'prenom')->first()->id,
            $cols->where('name', 'nom')->first()->id];

        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.columns.reorder', $this->table), [
                'order' => $newOrder,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        foreach ($newOrder as $position => $colId) {
            $this->assertDatabaseHas('datagrid_columns', [
                'id' => $colId,
                'sort_order' => $position,
            ], 'tenant');
        }
    }

    public function test_user_ne_peut_pas_reordonner_les_colonnes(): void
    {
        $cols = $this->table->columns()->orderBy('sort_order')->get();

        $this->actingAs($this->user, 'tenant')
            ->patchJson(route('admin.datagrid.columns.reorder', $this->table), [
                'order' => $cols->pluck('id')->toArray(),
            ])
            ->assertForbidden();
    }

    public function test_reorder_rejette_un_id_inconnu(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.columns.reorder', $this->table), [
                'order' => [99999],
            ])
            ->assertStatus(422);
    }

    public function test_reorder_valide_que_order_est_un_tableau(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.columns.reorder', $this->table), [
                'order' => 'pas-un-tableau',
            ])
            ->assertStatus(422);
    }

    // ── 2.16 — Tri par défaut ────────────────────────────────────────────────

    public function test_admin_peut_configurer_le_tri_par_defaut(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.settings.update', $this->table), [
                'default_sort_column' => 'nom',
                'default_sort_direction' => 'desc',
                'show_row_number' => false,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->table->refresh();
        $this->assertEquals('nom', $this->table->default_sort_column);
        $this->assertEquals('desc', $this->table->default_sort_direction);
    }

    public function test_admin_peut_reinitialiser_le_tri_par_defaut(): void
    {
        // D'abord configurer
        $this->table->update([
            'default_sort_column' => 'nom',
            'default_sort_direction' => 'asc',
        ]);

        // Puis réinitialiser avec colonne vide
        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.settings.update', $this->table), [
                'default_sort_column' => '',
                'default_sort_direction' => 'asc',
                'show_row_number' => false,
            ])
            ->assertOk();

        $this->table->refresh();
        $this->assertNull($this->table->default_sort_column);
    }

    public function test_settings_rejette_une_colonne_inconnue(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.settings.update', $this->table), [
                'default_sort_column' => 'colonne_inexistante',
                'default_sort_direction' => 'asc',
                'show_row_number' => false,
            ])
            ->assertStatus(422);
    }

    public function test_settings_rejette_une_direction_invalide(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.settings.update', $this->table), [
                'default_sort_column' => 'nom',
                'default_sort_direction' => 'invalid',
                'show_row_number' => false,
            ])
            ->assertStatus(422);
    }

    public function test_user_ne_peut_pas_modifier_les_settings(): void
    {
        $this->actingAs($this->user, 'tenant')
            ->patchJson(route('admin.datagrid.settings.update', $this->table), [
                'default_sort_column' => 'nom',
                'default_sort_direction' => 'asc',
                'show_row_number' => false,
            ])
            ->assertForbidden();
    }

    // ── 2.17 — Numéro de ligne ───────────────────────────────────────────────

    public function test_admin_peut_activer_le_numero_de_ligne(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.settings.update', $this->table), [
                'default_sort_column' => '',
                'default_sort_direction' => 'asc',
                'show_row_number' => true,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->table->refresh();
        $this->assertTrue($this->table->show_row_number);
    }

    public function test_admin_peut_desactiver_le_numero_de_ligne(): void
    {
        $this->table->update(['show_row_number' => true]);

        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.settings.update', $this->table), [
                'default_sort_column' => '',
                'default_sort_direction' => 'asc',
                'show_row_number' => false,
            ])
            ->assertOk();

        $this->table->refresh();
        $this->assertFalse($this->table->show_row_number);
    }

    public function test_modele_a_les_nouvelles_colonnes(): void
    {
        $this->table->update([
            'default_sort_column' => 'prenom',
            'default_sort_direction' => 'desc',
            'show_row_number' => true,
        ]);

        $this->table->refresh();

        $this->assertEquals('prenom', $this->table->default_sort_column);
        $this->assertEquals('desc', $this->table->default_sort_direction);
        $this->assertTrue($this->table->show_row_number);
    }
}
