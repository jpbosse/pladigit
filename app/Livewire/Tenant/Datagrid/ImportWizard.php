<?php

namespace App\Livewire\Tenant\Datagrid;

use App\Enums\DatagridColumnType;
use App\Imports\DatagridImport;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridTable;
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

    public int $step = 1;

    /** @var mixed Livewire TemporaryUploadedFile */
    public $file;

    public ?string $tempPath = null;

    // ── Étape 1 — mode ────────────────────────────────────────────

    /** 'new' = créer une nouvelle grille | 'update' = mettre à jour une grille existante */
    public string $importMode = 'new';

    /** 'append' = INSERT sans tronquer | 'replace' = TRUNCATE puis INSERT */
    public string $updateMode = 'append';

    /** DatagridTable::id cible quand importMode === 'update' */
    public ?int $targetTableId = null;

    // ── Étape 2 — configuration des colonnes (mode 'new' uniquement) ─

    public string $tableLabel = '';

    public string $tableName = '';

    public string $tableDescription = '';

    public bool $hasRgpd = false;

    /** @var array<int, array{index:int, header:string, label:string, name:string, type:string, required:bool}> */
    public array $columns = [];

    // ── Étape 3 — résultats ────────────────────────────────────────

    public int $importedRows = 0;

    public ?int $importedTableId = null;

    public ?string $errorMessage = null;

    public bool $fileHasHeader = true;

    /** @var array<int, array{id:int, label:string, name:string, columns_count:int}> */
    public array $existingGrids = [];

    // ── Lifecycle ──────────────────────────────────────────────────

    public function mount(): void
    {
        $this->existingGrids = DatagridTable::withCount('columns')
            ->orderBy('label')
            ->get()
            ->map(fn ($g) => [
                'id'            => $g->id,
                'label'         => $g->label,
                'name'          => $g->name,
                'columns_count' => $g->columns_count,
            ])
            ->toArray();
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
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:40960'],
        ], [
            'file.required' => 'Veuillez choisir un fichier.',
            'file.mimes'    => 'Le fichier doit être au format .xlsx ou .xls.',
            'file.max'      => 'La taille maximale est de 40 Mo.',
        ]);

        if ($this->importMode === 'update' && ! $this->targetTableId) {
            $this->addError('targetTableId', 'Veuillez sélectionner une grille cible.');
            return;
        }

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
                'index'    => $i,
                'header'   => (string) $header,
                'label'    => (string) $header,
                'name'     => Str::snake(Str::ascii(str_replace(["'", "\u{2019}", '`'], '_', (string) $header))),
                'type'     => DatagridColumnType::TEXT->value,
                'required' => false,
            ])
            ->all();

        $this->file = null;

        if ($this->importMode === 'update') {
            $this->step = 3;
        } else {
            $this->step = 2;
        }
    }

    // ── Étape 2 : confirmation des colonnes (mode 'new') ──────────

    public function confirmColumns(): void
    {
        $typeValues = implode(',', DatagridColumnType::values());

        $this->validate([
            'tableLabel'           => ['required', 'string', 'max:100'],
            'tableName'            => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'columns'              => ['required', 'array', 'min:1'],
            'columns.*.label'     => ['required', 'string', 'max:100'],
            'columns.*.name'      => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'columns.*.type'      => ['required', "in:{$typeValues}"],
            'columns.*.required'  => ['boolean'],
        ], [
            'tableLabel.required'           => 'Le libellé de la grille est obligatoire.',
            'tableName.required'            => 'Le nom technique est obligatoire.',
            'tableName.regex'               => 'Le nom technique doit commencer par une lettre et ne contenir que des lettres minuscules, chiffres et underscores.',
            'columns.*.label.required'     => 'Chaque colonne doit avoir un libellé.',
            'columns.*.name.required'      => 'Chaque colonne doit avoir un nom technique.',
            'columns.*.name.regex'         => 'Les noms techniques doivent commencer par une lettre et ne contenir que lettres minuscules, chiffres et underscores.',
            'columns.*.type.in'            => 'Type de colonne invalide.',
        ]);

        $this->step = 3;
    }

    public function backToStep1(): void
    {
        $this->step         = 1;
        $this->columns      = [];
        $this->tableLabel   = '';
        $this->tableName    = '';
        $this->tableDescription = '';
        $this->hasRgpd      = false;
        $this->importMode    = 'new';
        $this->updateMode    = 'append';
        $this->targetTableId = null;
        $this->fileHasHeader = true;
        $this->errorMessage = null;
        $this->importedRows = 0;
        $this->importedTableId = null;

        if ($this->tempPath) {
            Storage::disk('local')->delete($this->tempPath);
            $this->tempPath = null;
        }
    }

    public function backToStep2(): void
    {
        $this->step         = 2;
        $this->errorMessage = null;
    }

    // ── Étape 3 : import ──────────────────────────────────────────

    public function runImport(): void
    {
        $this->errorMessage = null;

        if ($this->importMode === 'update') {
            $this->runUpdate();
            return;
        }

        $this->runNew();
    }

    private function runNew(): void
    {
        $dgTable      = null;
        $tableCreated = false;

        try {
            $import = new DatagridImport;
            Excel::import($import, Storage::disk('local')->path($this->tempPath));

            // ── 1. Métadonnées (DML) ──────────────────────────────
            $dgTable = DatagridTable::create([
                'name'           => $this->tableName,
                'label'          => $this->tableLabel,
                'description'    => $this->tableDescription ?: null,
                'mysql_table'    => $this->mysqlTableName(),
                'has_rgpd'       => $this->hasRgpd,
                'is_persons_view' => false,
                'created_by'     => auth()->id(),
            ]);

            foreach ($this->columns as $i => $col) {
                DatagridColumn::create([
                    'datagrid_table_id' => $dgTable->id,
                    'name'              => $col['name'],
                    'label'             => $col['label'],
                    'type'              => $col['type'],
                    'required'          => (bool) $col['required'],
                    'visible_by_default' => true,
                    'sort_order'        => $i + 1,
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
            $count       = 0;

            foreach ($import->getDataRows() as $row) {
                $rowArr = array_values($row->toArray());
                $data   = [];

                foreach ($columnNames as $idx => $colName) {
                    $raw            = isset($rowArr[$idx]) && $rowArr[$idx] !== '' ? (string) $rowArr[$idx] : null;
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
                $count++;
            }

            $this->importedRows    = $count;
            $this->importedTableId = $dgTable->id;

            if ($this->tempPath) {
                Storage::disk('local')->delete($this->tempPath);
                $this->tempPath = null;
            }

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

    private function runUpdate(): void
    {
        try {
            $dgTable    = DatagridTable::findOrFail($this->targetTableId);
            $dbCols     = $dgTable->columns()->orderBy('sort_order')->get();
            $mysqlTable = $dgTable->mysql_table;

            // Construire un dictionnaire nom_colonne → type à partir des colonnes MySQL réelles
            $colTypeMap = $dbCols->pluck('type', 'name')
                ->map(fn ($t) => $t->value)
                ->all();

            $import = new DatagridImport;
            Excel::import($import, Storage::disk('local')->path($this->tempPath));

            // Mapper les colonnes Excel → colonnes existantes
            $headerMap = [];
            if ($this->fileHasHeader) {
                foreach ($this->columns as $col) {
                    if (array_key_exists($col['name'], $colTypeMap)) {
                        $headerMap[$col['index']] = [
                            'name' => $col['name'],
                            'type' => $colTypeMap[$col['name']],
                        ];
                    }
                }
                $dataRows = $import->getDataRows();
            } else {
                foreach ($dbCols->values() as $idx => $dbCol) {
                    $headerMap[$idx] = ['name' => $dbCol->name, 'type' => $dbCol->type->value];
                }
                $dataRows = $import->getAllRows();
            }

            if ($this->updateMode === 'replace') {
                DB::connection('tenant')->table($mysqlTable)->truncate();
            }

            $count = 0;
            foreach ($dataRows as $row) {
                $rowArr = array_values($row->toArray());
                $data   = [];

                foreach ($headerMap as $idx => $colInfo) {
                    $raw            = isset($rowArr[$idx]) && $rowArr[$idx] !== '' ? (string) $rowArr[$idx] : null;
                    $data[$colInfo['name']] = ($raw !== null && $colInfo['type'] === DatagridColumnType::DATE->value)
                        ? $this->normalizeDate($raw)
                        : $raw;
                }

                if (collect($data)->filter(fn ($v) => $v !== null)->isEmpty()) {
                    continue;
                }

                $data['created_at'] = now();
                $data['updated_at'] = now();
                DB::connection('tenant')->table($mysqlTable)->insert($data);
                $count++;
            }

            $this->importedRows    = $count;
            $this->importedTableId = $dgTable->id;

            if ($this->tempPath) {
                Storage::disk('local')->delete($this->tempPath);
                $this->tempPath = null;
            }

        } catch (\Throwable $e) {
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
        $type     = DatagridColumnType::tryFrom($col['type']) ?? DatagridColumnType::TEXT;
        $nullable = ! ((bool) $col['required']);

        $column = match ($type) {
            DatagridColumnType::NUMBER      => $table->decimal($col['name'], 15, 4),
            DatagridColumnType::DATE        => $table->date($col['name']),
            DatagridColumnType::BOOLEAN     => $table->boolean($col['name'])->default(false),
            DatagridColumnType::EMAIL       => $table->string($col['name'], 255),
            DatagridColumnType::PHONE       => $table->string($col['name'], 30),
            DatagridColumnType::SIRET       => $table->string($col['name'], 14),
            DatagridColumnType::POSTAL_CODE => $table->string($col['name'], 10),
            DatagridColumnType::SELECT      => $table->string($col['name'], 100),
            default                         => $table->string($col['name'], 255),
        };

        if ($nullable) {
            $column->nullable();
        }
    }

    public function render(): View
    {
        return view('livewire.tenant.datagrid.import-wizard', [
            'columnTypes' => DatagridColumnType::options(),
        ]);
    }
}
