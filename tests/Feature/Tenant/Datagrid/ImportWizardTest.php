<?php

namespace Tests\Feature\Tenant\Datagrid;

use App\Enums\DatagridColumnType;
use App\Imports\DatagridImport;
use App\Livewire\Tenant\Datagrid\ImportWizard;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridTable;
use App\Models\Tenant\User;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportWizardTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        Storage::fake('local');
    }

    // ── Accès ──────────────────────────────────────────────────────────────

    public function test_invité_redirigé_vers_login(): void
    {
        $this->get(route('datagrid.import'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_accède_à_la_page_import(): void
    {
        $this->actingAs($this->admin)
            ->get(route('datagrid.import'))
            ->assertOk()
            ->assertSeeLivewire(ImportWizard::class);
    }

    // ── Étape 1 — Upload ──────────────────────────────────────────────────

    public function test_upload_sans_fichier_déclenche_validation(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ImportWizard::class)
            ->call('uploadFile')
            ->assertHasErrors(['file']);
    }

    public function test_upload_xlsx_valide_passe_à_létape_2(): void
    {
        $file = $this->makeExcel(
            ['Nom', 'Prénom', 'Email'],
            [['Dupont', 'Jean', 'jean@example.com']]
        );

        Livewire::actingAs($this->admin)
            ->test(ImportWizard::class)
            ->set('file', $file)
            ->call('uploadFile')
            ->assertSet('step', 2)
            ->assertSet('columns.0.header', 'Nom')
            ->assertSet('columns.1.header', 'Prénom')
            ->assertSet('columns.2.header', 'Email')
            ->assertHasNoErrors();
    }

    public function test_colonnes_détectées_ont_des_noms_snake_case(): void
    {
        $file = $this->makeExcel(
            ['Nom de famille', 'Code postal'],
            []
        );

        $component = Livewire::actingAs($this->admin)
            ->test(ImportWizard::class)
            ->set('file', $file)
            ->call('uploadFile');

        $columns = $component->get('columns');

        $this->assertEquals('nom_de_famille', $columns[0]['name']);
        $this->assertEquals('code_postal', $columns[1]['name']);
    }

    public function test_colonnes_ont_le_type_text_par_défaut(): void
    {
        $file = $this->makeExcel(['Nom', 'Age'], []);

        $component = Livewire::actingAs($this->admin)
            ->test(ImportWizard::class)
            ->set('file', $file)
            ->call('uploadFile');

        $columns = $component->get('columns');

        foreach ($columns as $col) {
            $this->assertEquals(DatagridColumnType::TEXT->value, $col['type']);
            $this->assertFalse($col['required']);
        }
    }

    // ── Étape 2 — Configuration des colonnes ──────────────────────────────

    public function test_étape_2_valide_le_libellé_et_le_nom_technique(): void
    {
        $component = $this->atStep2(['Col1', 'Col2'], []);

        $component
            ->set('tableLabel', '')
            ->call('confirmColumns')
            ->assertHasErrors(['tableLabel']);

        $component
            ->set('tableLabel', 'Ma grille')
            ->set('tableName', 'nom invalide!')
            ->call('confirmColumns')
            ->assertHasErrors(['tableName']);
    }

    public function test_étape_2_valide_les_noms_techniques_des_colonnes(): void
    {
        $component = $this->atStep2(['Col1'], []);

        $component
            ->set('tableLabel', 'Test')
            ->set('tableName', 'test_table')
            ->set('columns.0.name', 'invalid name!')
            ->call('confirmColumns')
            ->assertHasErrors(['columns.0.name']);
    }

    public function test_confirmation_colonnes_passe_à_létape_3(): void
    {
        $component = $this->atStep2(['Nom', 'Email'], []);

        $component
            ->set('tableLabel', 'Test grille')
            ->set('tableName', 'test_grille')
            ->call('confirmColumns')
            ->assertSet('step', 3)
            ->assertHasNoErrors();
    }

    public function test_retour_à_létape_1_nettoie_létat(): void
    {
        $component = $this->atStep2(['Nom'], []);

        $component
            ->set('tableLabel', 'Test')
            ->set('tableName', 'test')
            ->call('backToStep1')
            ->assertSet('step', 1)
            ->assertSet('columns', [])
            ->assertSet('tableLabel', '')
            ->assertSet('tableName', '');
    }

    // ── Étape 3 — Import complet ──────────────────────────────────────────

    public function test_import_complet_crée_datagrid_table_et_colonnes(): void
    {
        $rows = [
            ['Dupont', 'Jean', 'jean@example.com'],
            ['Martin', 'Marie', 'marie@example.com'],
        ];

        $file = $this->makeExcel(['nom', 'prenom', 'email'], $rows);

        $component = Livewire::actingAs($this->admin)
            ->test(ImportWizard::class)
            ->set('file', $file)
            ->call('uploadFile')
            ->set('tableLabel', 'Agents')
            ->set('tableName', 'agents')
            ->set('columns.2.type', DatagridColumnType::EMAIL->value)
            ->call('confirmColumns')
            ->call('runImport');

        $component->assertSet('importedRows', 2)
            ->assertNotSet('importedTableId', null)
            ->assertSet('errorMessage', null);

        $dgTable = DatagridTable::on('tenant')->where('name', 'agents')->first();
        $this->assertNotNull($dgTable);
        $this->assertEquals('Agents', $dgTable->label);

        $this->assertCount(3, DatagridColumn::on('tenant')->where('datagrid_table_id', $dgTable->id)->get());

        $this->assertTrue(Schema::connection('tenant')->hasTable('agents'));
        $this->assertEquals(2, DB::connection('tenant')->table('agents')->count());

        Schema::connection('tenant')->dropIfExists('agents');
    }

    public function test_import_ignore_les_lignes_vides(): void
    {
        $rows = [
            ['Dupont', 'Jean'],
            ['', ''],
            ['Martin', 'Marie'],
        ];

        $file = $this->makeExcel(['nom', 'prenom'], $rows);

        $component = Livewire::actingAs($this->admin)
            ->test(ImportWizard::class)
            ->set('file', $file)
            ->call('uploadFile')
            ->set('tableLabel', 'Personnes')
            ->set('tableName', 'personnes_test')
            ->call('confirmColumns')
            ->call('runImport');

        $component->assertSet('importedRows', 2);

        Schema::connection('tenant')->dropIfExists('personnes_test');
    }

    public function test_import_avec_colonne_requise_crée_colonne_non_nullable(): void
    {
        $file = $this->makeExcel(['nom', 'commentaire'], [['Test', '']]);

        $component = Livewire::actingAs($this->admin)
            ->test(ImportWizard::class)
            ->set('file', $file)
            ->call('uploadFile')
            ->set('tableLabel', 'Req test')
            ->set('tableName', 'req_test')
            ->set('columns.0.required', true)
            ->call('confirmColumns')
            ->call('runImport');

        $component->assertSet('errorMessage', null);

        $colNom = DatagridColumn::on('tenant')
            ->whereHas('datagridTable', fn ($q) => $q->where('name', 'req_test'))
            ->where('name', 'nom')
            ->first();

        $this->assertNotNull($colNom);
        $this->assertTrue($colNom->required);

        Schema::connection('tenant')->dropIfExists('req_test');
    }

    // ── DatagridImport — isolation ─────────────────────────────────────────

    public function test_datagrid_import_retourne_en_têtes_et_données(): void
    {
        $filePath = $this->makeTempExcel(
            ['Commune', 'Population'],
            [['Soullans', '4200'], ['Saint-Jean-de-Monts', '8500']]
        );

        $import = new DatagridImport();
        Excel::import($import, $filePath);

        $this->assertEquals(['Commune', 'Population'], $import->getHeaders());
        $this->assertCount(2, $import->getDataRows());
        $this->assertEquals('Soullans', $import->getDataRows()->first()[0]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function makeExcel(array $headers, array $rows): TestingFile
    {
        $filePath = $this->makeTempExcel($headers, $rows);

        // Testing\File expose ->name (public $name) requis par Livewire\Testable::upload()
        return new TestingFile('import.xlsx', fopen($filePath, 'r'));
    }

    private function makeTempExcel(array $headers, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $i => $header) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $value);
            }
        }

        $path = sys_get_temp_dir().'/datagrid_test_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * Positionne le composant à l'étape 2 en simulant l'upload réel.
     *
     * @param  string[]  $headers
     * @param  array[]   $rows
     */
    private function atStep2(array $headers, array $rows): \Livewire\Features\SupportTesting\Testable
    {
        $file = $this->makeExcel($headers, $rows);

        return Livewire::actingAs($this->admin)
            ->test(ImportWizard::class)
            ->set('file', $file)
            ->call('uploadFile');
    }
}
