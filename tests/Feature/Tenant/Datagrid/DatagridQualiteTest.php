<?php

namespace Tests\Feature\Tenant\Datagrid;

use App\Enums\DatagridColumnType;
use App\Enums\ModuleKey;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridTable;
use App\Models\Tenant\User;
use App\Services\DatagridFuzzySearch;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests bloc 3 — Qualité des données.
 *
 * 3.1 — Type NOM_PERSONNE : fuzzy_search configurable via admin
 * 3.2 — Recherche floue dans la grille
 * 3.3 — Détection de doublons à l'import
 */
class DatagridQualiteTest extends TestCase
{
    private User $admin;

    private User $user;

    private DatagridTable $table;

    private string $mysqlTable = 'dg_test_qualite';

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantManager::class)->current()->enableModule(ModuleKey::DATAGRID);

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->user = User::factory()->create(['role' => 'user',  'status' => 'active']);

        $this->table = DatagridTable::create([
            'name' => 'test_qualite',
            'label' => 'Grille Qualité Test',
            'mysql_table' => $this->mysqlTable,
            'has_rgpd' => false,
            'created_by' => $this->admin->id,
        ]);

        DatagridColumn::create([
            'datagrid_table_id' => $this->table->id,
            'name' => 'nom',
            'label' => 'Nom',
            'type' => DatagridColumnType::NOM_PERSONNE,
            'required' => false,
            'visible_by_default' => true,
            'is_rgpd_sensitive' => false,
            'is_role_column' => false,
            'sort_order' => 0,
            'fuzzy_search' => false,
        ]);

        DatagridColumn::create([
            'datagrid_table_id' => $this->table->id,
            'name' => 'commune',
            'label' => 'Commune',
            'type' => DatagridColumnType::TEXT,
            'required' => false,
            'visible_by_default' => true,
            'is_rgpd_sensitive' => false,
            'is_role_column' => false,
            'sort_order' => 1,
            'fuzzy_search' => false,
        ]);

        Schema::connection('tenant')->create($this->mysqlTable, function ($t) {
            $t->id();
            $t->string('nom', 255)->nullable();
            $t->string('commune', 255)->nullable();
            $t->timestamps();
        });

        DB::connection('tenant')->table($this->mysqlTable)->insert([
            ['nom' => 'Dupont Jean',  'commune' => 'Paris',    'created_at' => now(), 'updated_at' => now()],
            ['nom' => 'Martin Marie', 'commune' => 'Lyon',     'created_at' => now(), 'updated_at' => now()],
            ['nom' => 'Bernard Paul', 'commune' => 'Nantes',   'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->table)) {
            DatagridColumn::where('datagrid_table_id', $this->table->id)->forceDelete();
            $this->table->forceDelete();
        }
        Schema::connection('tenant')->dropIfExists($this->mysqlTable);
        // Nettoyer les users créés dans setUp pour éviter les conflits d'unicité email
        if (isset($this->admin)) {
            $this->admin->forceDelete();
        }
        if (isset($this->user)) {
            $this->user->forceDelete();
        }
        parent::tearDown();
    }

    // ── 3.1 — fuzzy_search configurable ──────────────────────────────────────

    public function test_admin_peut_activer_fuzzy_search_sur_nom_personne(): void
    {
        $col = $this->table->columns()->where('name', 'nom')->first();

        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.columns.update', [$this->table, $col]), [
                'label' => 'Nom',
                'visible_by_default' => true,
                'required' => false,
                'is_rgpd_sensitive' => false,
                'sort_order' => 0,
                'type' => 'nom_personne',
                'fuzzy_search' => true,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $col->refresh();
        $this->assertTrue($col->fuzzy_search);
    }

    public function test_admin_peut_desactiver_fuzzy_search(): void
    {
        $col = $this->table->columns()->where('name', 'nom')->first();
        $col->update(['fuzzy_search' => true]);

        $this->actingAs($this->admin, 'tenant')
            ->patchJson(route('admin.datagrid.columns.update', [$this->table, $col]), [
                'label' => 'Nom',
                'visible_by_default' => true,
                'required' => false,
                'is_rgpd_sensitive' => false,
                'sort_order' => 0,
                'type' => 'nom_personne',
                'fuzzy_search' => false,
            ])
            ->assertOk();

        $col->refresh();
        $this->assertFalse((bool) $col->fuzzy_search);
    }

    public function test_fuzzy_search_est_false_par_defaut(): void
    {
        $col = DatagridColumn::create([
            'datagrid_table_id' => $this->table->id,
            'name' => 'prenom',
            'label' => 'Prénom',
            'type' => DatagridColumnType::NOM_PERSONNE,
            'required' => false,
            'visible_by_default' => true,
            'is_rgpd_sensitive' => false,
            'is_role_column' => false,
            'sort_order' => 2,
        ]);

        $this->assertFalse($col->fuzzy_search);
    }

    // ── 3.2 — Service DatagridFuzzySearch ────────────────────────────────────

    public function test_normalize_supprime_accents_et_casse(): void
    {
        $this->assertEquals('dupont jean', DatagridFuzzySearch::normalize('Dupont Jean'));
        $this->assertEquals('dupond', DatagridFuzzySearch::normalize('Dupönd'));
        $this->assertEquals('martin marie', DatagridFuzzySearch::normalize('  Martin   Marie  '));
    }

    public function test_matching_ids_trouve_variante_levenshtein_1(): void
    {
        // "Dupond" vs "Dupont" → distance 1
        $ids = DatagridFuzzySearch::matchingIds($this->mysqlTable, 'nom', 'Dupond Jean');
        $this->assertNotEmpty($ids, 'Dupond Jean doit matcher Dupont Jean (distance 1)');
    }

    public function test_matching_ids_trouve_variante_levenshtein_2(): void
    {
        // "Dupond Jeen" vs "Dupont Jean" → distance 2
        $ids = DatagridFuzzySearch::matchingIds($this->mysqlTable, 'nom', 'Dupond Jeen');
        $this->assertNotEmpty($ids, 'Dupond Jeen doit matcher Dupont Jean (distance 2)');
    }

    public function test_matching_ids_ne_trouve_pas_distance_3(): void
    {
        // "Dupond XXX" → trop loin
        $ids = DatagridFuzzySearch::matchingIds($this->mysqlTable, 'nom', 'Dupond XXX');
        $this->assertEmpty($ids, 'Dupond XXX ne doit pas matcher Dupont Jean (distance > 2)');
    }

    public function test_matching_ids_retourne_vide_pour_valeur_vide(): void
    {
        $ids = DatagridFuzzySearch::matchingIds($this->mysqlTable, 'nom', '');
        $this->assertEmpty($ids);
    }

    public function test_matching_ids_trouve_correspondance_exacte(): void
    {
        $ids = DatagridFuzzySearch::matchingIds($this->mysqlTable, 'nom', 'Dupont Jean');
        $this->assertNotEmpty($ids);
    }

    // ── 3.3 — detectDuplicates ───────────────────────────────────────────────

    public function test_detect_duplicates_trouve_doublon_proche(): void
    {
        $importValues = ['Dupond Jean', 'Nouveau Nom'];
        $existingValues = [
            ['id' => 1, 'value' => 'Dupont Jean'],
            ['id' => 2, 'value' => 'Martin Marie'],
        ];

        $result = DatagridFuzzySearch::detectDuplicates($importValues, $existingValues);

        $this->assertCount(1, $result);
        $this->assertEquals('Dupond Jean', $result[0]['import_value']);
        $this->assertEquals('Dupont Jean', $result[0]['existing_value']);
        $this->assertEquals(1, $result[0]['distance']);
    }

    public function test_detect_duplicates_ne_signale_pas_exact(): void
    {
        // Correspondance exacte → mise à jour normale, pas un doublon suspect
        $importValues = ['Dupont Jean'];
        $existingValues = [['id' => 1, 'value' => 'Dupont Jean']];

        $result = DatagridFuzzySearch::detectDuplicates($importValues, $existingValues);
        $this->assertEmpty($result, 'Les correspondances exactes ne doivent pas être signalées');
    }

    public function test_detect_duplicates_retourne_vide_sans_correspondance(): void
    {
        $importValues = ['Untel Inconnu'];
        $existingValues = [
            ['id' => 1, 'value' => 'Dupont Jean'],
            ['id' => 2, 'value' => 'Martin Marie'],
        ];

        $result = DatagridFuzzySearch::detectDuplicates($importValues, $existingValues);
        $this->assertEmpty($result);
    }

    public function test_detect_duplicates_retourne_meilleur_match(): void
    {
        $importValues = ['Dupond Jean'];
        $existingValues = [
            ['id' => 1, 'value' => 'Dupont Jean'],
            ['id' => 3, 'value' => 'Bernard Paul'],
        ];

        $result = DatagridFuzzySearch::detectDuplicates($importValues, $existingValues);
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['existing_id']);
    }

    // ── 3.3 — detectInternalDuplicates ───────────────────────────────────────

    public function test_internal_duplicates_trouve_paire_levenshtein_1(): void
    {
        // Aubert (index 1) vs Auberd (index 2) → distance 1
        $values = [1 => 'Aubert Jean', 2 => 'Auberd Jean'];
        $result = DatagridFuzzySearch::detectInternalDuplicates($values);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['distance']);
        $this->assertEquals(1, $result[0]['index_a']);
        $this->assertEquals(2, $result[0]['index_b']);
    }

    public function test_internal_duplicates_trouve_doublon_exact(): void
    {
        $values = [1 => 'Dupont Jean', 2 => 'Martin Marie', 3 => 'Dupont Jean'];
        $result = DatagridFuzzySearch::detectInternalDuplicates($values);

        $this->assertCount(1, $result);
        $this->assertEquals(0, $result[0]['distance']);
        $this->assertEquals(1, $result[0]['index_a']);
        $this->assertEquals(3, $result[0]['index_b']);
    }

    public function test_internal_duplicates_retourne_vide_sans_similitude(): void
    {
        $values = [1 => 'Dupont Jean', 2 => 'Martin Marie', 3 => 'Bernard Paul'];
        $result = DatagridFuzzySearch::detectInternalDuplicates($values);
        $this->assertEmpty($result);
    }

    public function test_internal_duplicates_chaque_paire_une_seule_fois(): void
    {
        // Seuil adaptatif : 6 chars → distance max 1
        // Aubert/Auberd = dist 1 ✓
        // Aubert/Aubetr = dist 2 ✗ (au-delà du seuil)
        // Auberd/Aubetr = dist 2 ✗ (au-delà du seuil)
        // Donc 1 seule paire détectée
        $values = [1 => 'Aubert', 2 => 'Auberd', 3 => 'Aubetr'];
        $result = DatagridFuzzySearch::detectInternalDuplicates($values);

        $this->assertCount(1, $result);
        foreach ($result as $pair) {
            // index_a toujours < index_b
            $this->assertLessThan($pair['index_b'], $pair['index_a']);
        }
    }

    public function test_internal_duplicates_noms_longs_distance_2(): void
    {
        // Noms longs (≥ 9 chars) → distance max 2
        $values = [1 => 'Beauchamps', 2 => 'Beauchamp'];  // dist 1
        $result = DatagridFuzzySearch::detectInternalDuplicates($values);
        $this->assertCount(1, $result);
    }

    public function test_internal_duplicates_ignore_valeurs_vides(): void
    {
        $values = [1 => 'Dupont', 2 => '', 3 => 'Dupond'];
        $result = DatagridFuzzySearch::detectInternalDuplicates($values);
        $this->assertCount(1, $result);
    }
}
