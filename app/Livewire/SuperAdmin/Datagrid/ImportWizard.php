<?php

namespace App\Livewire\SuperAdmin\Datagrid;

use App\Enums\DatagridColumnType;
use App\Imports\DatagridImport;
use App\Models\Platform\Organization;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridTable;
use App\Services\TenantManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class ImportWizard extends Component
{
    use WithFileUploads;

    public ?int $organizationId = null;

    public int $step = 1;

    /** @var mixed Livewire TemporaryUploadedFile */
    public $file;

    public ?string $tempPath = null;

    // ── Étape 2 — configuration des colonnes ─────────────────────

    public string $tableLabel = '';

    public string $tableName = '';

    public string $tableDescription = '';

    public bool $hasRgpd = false;

    /** @var array<int, array{index:int, header:string, label:string, name:string, type:string, required:bool}> */
    public array $columns = [];

    // ── Étape 3 — résultats ────────────────────────────────────────

    public ?string $errorMessage = null;

    // ── Lifecycle ──────────────────────────────────────────────────

    public function mount(?int $organizationId = null): void
    {
        if ($organizationId !== null) {
            $this->organizationId = $organizationId;
        }
        if ($this->organizationId !== null) {
            $this->connectTenant();
        }
    }

    public function hydrate(): void
    {
        if ($this->organizationId !== null) {
            $this->connectTenant();
        }
    }

    private function connectTenant(): void
    {
        $org = Organization::findOrFail((int) $this->organizationId);
        app(TenantManager::class)->connectTo($org);
    }

    // ── Réactivité ─────────────────────────────────────────────────

    public function updatedTableLabel(string $value): void
    {
        if ($this->tableName === '') {
            $this->tableName = 'dg_'.Str::snake(Str::ascii($value));
        }
    }

    // ── Étape 1 : upload ──────────────────────────────────────────

    public function uploadFile(): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [
            'file.required' => 'Veuillez choisir un fichier.',
            'file.mimes' => 'Le fichier doit être au format .xlsx ou .xls.',
            'file.max' => 'La taille maximale est de 10 Mo.',
        ]);

        $this->tempPath = $this->file->storeAs(
            'imports/datagrid',
            Str::uuid().'.xlsx',
            'local'
        );

        $import = new DatagridImport;
        Excel::import($import, Storage::disk('local')->path($this->tempPath));

        $headers = $import->getHeaders();

        $this->columns = collect($headers)
            ->filter(fn ($h) => filled($h))
            ->values()
            ->map(fn ($header, int $i) => [
                'index' => $i,
                'header' => (string) $header,
                'label' => (string) $header,
                'name' => Str::snake(Str::ascii(str_replace(["'", "\u{2019}", '`'], '_', (string) $header))),
                'type' => DatagridColumnType::TEXT->value,
                'required' => false,
            ])
            ->all();

        $this->file = null;
        $this->step = 2;
    }

    // ── Étape 2 : confirmation des colonnes ───────────────────────

    public function confirmColumns(): void
    {
        $typeValues = implode(',', DatagridColumnType::values());

        $this->validate([
            'tableLabel' => ['required', 'string', 'max:100'],
            'tableName' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*.label' => ['required', 'string', 'max:100'],
            'columns.*.name' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'columns.*.type' => ['required', "in:{$typeValues}"],
            'columns.*.required' => ['boolean'],
        ], [
            'tableLabel.required' => 'Le libellé de la grille est obligatoire.',
            'tableName.required' => 'Le nom technique est obligatoire.',
            'tableName.regex' => 'Le nom technique doit commencer par une lettre et ne contenir que des lettres minuscules, chiffres et underscores.',
            'columns.*.label.required' => 'Chaque colonne doit avoir un libellé.',
            'columns.*.name.required' => 'Chaque colonne doit avoir un nom technique.',
            'columns.*.name.regex' => 'Les noms techniques doivent commencer par une lettre et ne contenir que lettres minuscules, chiffres et underscores.',
            'columns.*.type.in' => 'Type de colonne invalide.',
        ]);

        $this->step = 3;
    }

    public function backToStep1(): void
    {
        $this->step = 1;
        $this->columns = [];
        $this->tableLabel = '';
        $this->tableName = '';
        $this->tableDescription = '';
        $this->hasRgpd = false;
        if ($this->tempPath) {
            Storage::disk('local')->delete($this->tempPath);
            $this->tempPath = null;
        }
    }

    public function backToStep2(): void
    {
        $this->step = 2;
        $this->errorMessage = null;
    }

    // ── Étape 3 : import ──────────────────────────────────────────

    public function runImport(): void
    {
        $this->errorMessage = null;

        $dgTable = null;
        $tableCreated = false;
        $importedRows = 0;

        try {
            $import = new DatagridImport;
            Excel::import($import, Storage::disk('local')->path($this->tempPath));

            // ── 1. Métadonnées (DML) ──────────────────────────────
            $dgTable = DatagridTable::create([
                'name' => $this->tableName,
                'label' => $this->tableLabel,
                'description' => $this->tableDescription ?: null,
                'mysql_table' => $this->mysqlTableName(),
                'has_rgpd' => $this->hasRgpd,
                'is_persons_view' => false,
                'created_by' => null,
            ]);

            foreach ($this->columns as $i => $col) {
                DatagridColumn::create([
                    'datagrid_table_id' => $dgTable->id,
                    'name' => $col['name'],
                    'label' => $col['label'],
                    'type' => $col['type'],
                    'required' => (bool) $col['required'],
                    'visible_by_default' => true,
                    'sort_order' => $i + 1,
                ]);
            }

            // ── 2. Table physique (DDL — commit implicite MySQL) ──
            Schema::connection('tenant')->create($this->mysqlTableName(), function (Blueprint $table) {
                $table->id();
                foreach ($this->columns as $col) {
                    $this->addDynamicColumn($table, $col);
                }
                $table->timestamps();
            });
            $tableCreated = true;

            // ── 3. Lignes de données ──────────────────────────────
            $columnNames = array_column($this->columns, 'name');
            $columnTypes = array_column($this->columns, 'type', 'name');

            foreach ($import->getDataRows() as $row) {
                $rowArr = array_values($row->toArray());
                $data = [];

                foreach ($columnNames as $idx => $colName) {
                    $raw = isset($rowArr[$idx]) && $rowArr[$idx] !== '' ? (string) $rowArr[$idx] : null;
                    $data[$colName] = ($raw !== null && ($columnTypes[$colName] ?? '') === DatagridColumnType::DATE->value)
                        ? $this->normalizeDate($raw)
                        : $raw;
                }

                if (collect($data)->filter(fn ($v) => $v !== null)->isEmpty()) {
                    continue;
                }

                $data['created_at'] = now();
                $data['updated_at'] = now();
                DB::connection('tenant')->table($this->mysqlTableName())->insert($data);
                $importedRows++;
            }

            if ($this->tempPath) {
                Storage::disk('local')->delete($this->tempPath);
                $this->tempPath = null;
            }

            session()->flash('success', "Grille « {$this->tableLabel} » créée avec succès ({$importedRows} ligne(s) importée(s)).");
            $this->redirect(route('super-admin.datagrids.index'), navigate: false);

        } catch (\Throwable $e) {
            if ($tableCreated) {
                Schema::connection('tenant')->dropIfExists($this->mysqlTableName());
            }
            if ($dgTable) {
                $dgTable->forceDelete();
            }
            $this->errorMessage = $e->getMessage();
        }
    }

    private function mysqlTableName(): string
    {
        return str_starts_with($this->tableName, 'dg_') ? $this->tableName : 'dg_'.$this->tableName;
    }

    private function normalizeDate(string $value): ?string
    {
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return $value ?: null;
    }

    private function addDynamicColumn(Blueprint $table, array $col): void
    {
        $type = DatagridColumnType::tryFrom($col['type']) ?? DatagridColumnType::TEXT;
        $nullable = ! ((bool) $col['required']);

        $column = match ($type) {
            DatagridColumnType::NUMBER => $table->decimal($col['name'], 15, 4),
            DatagridColumnType::DATE => $table->date($col['name']),
            DatagridColumnType::BOOLEAN => $table->boolean($col['name'])->default(false),
            DatagridColumnType::EMAIL => $table->string($col['name'], 255),
            DatagridColumnType::PHONE => $table->string($col['name'], 30),
            DatagridColumnType::SIRET => $table->string($col['name'], 14),
            DatagridColumnType::POSTAL_CODE => $table->string($col['name'], 10),
            DatagridColumnType::SELECT => $table->string($col['name'], 100),
            default => $table->string($col['name'], 255),
        };

        if ($nullable) {
            $column->nullable();
        }
    }

    public function render(): View
    {
        return view('livewire.super-admin.datagrid.import-wizard', [
            'columnTypes' => DatagridColumnType::options(),
        ]);
    }
}
