<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\DatagridColumnType;
use App\Livewire\SuperAdmin\Datagrid\ImportWizard;
use App\Models\Platform\Organization;
use App\Models\Tenant\DatagridTable;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DatagridImportTest extends TestCase
{
    private function actingAsSuperAdmin(): static
    {
        return $this->withSession([
            'super_admin_email' => config('superadmin.email'),
            'super_admin_verified' => true,
        ]);
    }

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::first();

        Storage::fake('local');
    }

    // ── Accès ──────────────────────────────────────────────────────────────

    public function test_invité_redirigé_vers_login(): void
    {
        $this->get(route('super-admin.datagrids.import', $this->org))
            ->assertRedirect();
    }

    public function test_super_admin_accède_à_la_page_import(): void
    {
        $this->actingAsSuperAdmin()
            ->get(route('super-admin.datagrids.import', $this->org))
            ->assertOk()
            ->assertViewIs('super-admin.datagrids.import')
            ->assertSeeLivewire(ImportWizard::class);
    }

    public function test_organisation_inexistante_retourne_404(): void
    {
        $this->actingAsSuperAdmin()
            ->get(route('super-admin.datagrids.import', ['organization' => 99999]))
            ->assertNotFound();
    }

    // ── Étape 1 — Upload ──────────────────────────────────────────────────

    public function test_upload_sans_fichier_déclenche_validation(): void
    {
        Livewire::test(ImportWizard::class, ['organizationId' => $this->org->id])
            ->call('uploadFile')
            ->assertHasErrors(['file']);
    }

    public function test_upload_xlsx_valide_passe_à_létape_2(): void
    {
        $file = $this->makeExcel(['Nom', 'Email'], [['Dupont', 'jean@example.com']]);

        Livewire::test(ImportWizard::class, ['organizationId' => $this->org->id])
            ->set('file', $file)
            ->call('uploadFile')
            ->assertSet('step', 2)
            ->assertSet('columns.0.header', 'Nom')
            ->assertHasNoErrors();
    }

    // ── Étape 2 — Configuration ───────────────────────────────────────────

    public function test_étape_2_valide_le_libellé_obligatoire(): void
    {
        $component = $this->atStep2(['Col1', 'Col2'], []);

        $component
            ->set('tableLabel', '')
            ->call('confirmColumns')
            ->assertHasErrors(['tableLabel']);
    }

    public function test_confirmation_colonnes_passe_à_létape_3(): void
    {
        $component = $this->atStep2(['Nom', 'Email'], []);

        $component
            ->set('tableLabel', 'Test grille SA')
            ->set('tableName', 'test_grille_sa')
            ->call('confirmColumns')
            ->assertSet('step', 3)
            ->assertHasNoErrors();
    }

    // ── Étape 3 — Import ─────────────────────────────────────────────────

    public function test_import_crée_datagrid_table_avec_created_by_null(): void
    {
        $rows = [['Dupont', 'jean@example.com'], ['Martin', 'marie@example.com']];
        $file = $this->makeExcel(['nom', 'email'], $rows);

        Livewire::test(ImportWizard::class, ['organizationId' => $this->org->id])
            ->set('file', $file)
            ->call('uploadFile')
            ->set('tableLabel', 'Import SA')
            ->set('tableName', 'import_sa_test')
            ->set('columns.1.type', DatagridColumnType::EMAIL->value)
            ->call('confirmColumns')
            ->call('runImport')
            ->assertHasNoErrors()
            ->assertRedirect(route('super-admin.datagrids.index'));

        $dgTable = DatagridTable::on('tenant')->where('name', 'import_sa_test')->first();
        $this->assertNotNull($dgTable);
        $this->assertNull($dgTable->created_by);
        $this->assertTrue(Schema::connection('tenant')->hasTable('dg_import_sa_test'));
        $this->assertEquals(2, DB::connection('tenant')->table('dg_import_sa_test')->count());

        Schema::connection('tenant')->dropIfExists('dg_import_sa_test');
    }

    public function test_import_flash_success_sur_la_page_liste(): void
    {
        $file = $this->makeExcel(['titre'], [['valeur1']]);

        Livewire::test(ImportWizard::class, ['organizationId' => $this->org->id])
            ->set('file', $file)
            ->call('uploadFile')
            ->set('tableLabel', 'Flash test')
            ->set('tableName', 'flash_test_sa')
            ->call('confirmColumns')
            ->call('runImport');

        $this->assertEquals(
            'Grille « Flash test » créée avec succès (1 ligne(s) importée(s)).',
            session('success')
        );

        Schema::connection('tenant')->dropIfExists('dg_flash_test_sa');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function makeExcel(array $headers, array $rows): TestingFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $i => $header) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $value);
            }
        }

        $path = sys_get_temp_dir().'/datagrid_sa_test_'.uniqid().'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new TestingFile('import.xlsx', fopen($path, 'r'));
    }

    private function atStep2(array $headers, array $rows): Testable
    {
        $file = $this->makeExcel($headers, $rows);

        return Livewire::test(ImportWizard::class, ['organizationId' => $this->org->id])
            ->set('file', $file)
            ->call('uploadFile');
    }
}
