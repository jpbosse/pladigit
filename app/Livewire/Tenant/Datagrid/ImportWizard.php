<?php

namespace App\Livewire\Tenant\Datagrid;

use App\Enums\DatagridColumnType;
use App\Imports\DatagridImport;
use App\Jobs\ImportDatagridJob;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridTable;
use App\Services\TenantManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
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

    // ── Étape 2 — configuration des colonnes (mode 'new') ─────────

    public string $tableLabel = '';

    public string $tableName = '';

    public string $tableDescription = '';

    public bool $hasRgpd = false;

    /** 'public' = lecture pour tous | 'restricted' = admin configure | 'private' = refus explicite pour 'user' */
    public string $defaultVisibility = 'restricted';

    /**
     * Valeurs distinctes détectées par colonne (5 premières lignes du fichier).
     * Format : [columnIndex => ['val1', 'val2', ...]]
     *
     * @var array<int, string[]>
     */
    public array $sampleValues = [];

    /** @var array<int, array{index:int, header:string, label:string, name:string, type:string, required:bool, label_true:string, label_false:string, options_raw:string}> */
    public array $columns = [];

    // ── Étape 2 — mapping des colonnes (mode 'update') ────────────

    /**
     * @var array<int, array{index:int, header:string}>
     */
    public array $unmatchedColumns = [];

    /**
     * @var array<int, string>
     */
    public array $columnMapping = [];

    /**
     * @var array<int, array{name:string, label:string}>
     */
    public array $gridColumns = [];

    // ── Étape 3 — job / progression ───────────────────────────────

    public int $importedRows = 0;

    public ?int $importedTableId = null;

    public ?string $errorMessage = null;

    public bool $fileHasHeader = true;

    /** UUID du job en cours — null si pas encore lancé */
    public ?string $importId = null;

    /** Statut renvoyé par Redis : pending|running|done|error */
    public string $jobStatus = '';

    /** Nombre de lignes traitées (polling) */
    public int $jobProcessed = 0;

    /** Nombre total de lignes (polling) */
    public int $jobTotal = 0;

    /** @var array<int, array{id:int, label:string, name:string, columns_count:int}> */
    public array $existingGrids = [];

    // ── Lifecycle ──────────────────────────────────────────────────

    public function mount(): void
    {
        $this->existingGrids = DatagridTable::withCount('columns')
            ->orderBy('label')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'label' => $g->label,
                'name' => $g->name,
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

    // ── Polling progression ────────────────────────────────────────

    /**
     * Appelé par wire:poll toutes les 2 secondes quand un import est en cours.
     * Met à jour les propriétés de progression depuis Redis.
     */
    public function pollProgress(): void
    {
        if (! $this->importId) {
            return;
        }

        $data = Cache::get('datagrid_import:'.$this->importId);

        if (! $data) {
            return;
        }

        $this->jobStatus = $data['status'] ?? '';
        $this->jobProcessed = $data['processed'] ?? 0;
        $this->jobTotal = $data['total'] ?? 0;

        if ($this->jobStatus === 'done') {
            $this->importedRows = $this->jobProcessed;
            $this->importedTableId = $this->resolveImportedTableId();
            Cache::forget('datagrid_import:'.$this->importId);
        }

        if ($this->jobStatus === 'error') {
            $this->errorMessage = $data['error'] ?? 'Erreur inconnue.';
            Cache::forget('datagrid_import:'.$this->importId);
        }
    }

    private function resolveImportedTableId(): ?int
    {
        if ($this->importMode === 'update') {
            return $this->targetTableId;
        }

        return DatagridTable::where('name', $this->tableName)->value('id');
    }

    // ── Étape 1 : upload ──────────────────────────────────────────

    public function uploadFile(): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,ods', 'max:102400'],
        ], [
            'file.required' => 'Veuillez choisir un fichier.',
            'file.mimes' => 'Le fichier doit être au format .xlsx, .xls, .csv ou .ods.',
            'file.max' => 'La taille maximale est de 100 Mo.',
        ]);

        if ($this->importMode === 'update' && ! $this->targetTableId) {
            $this->addError('targetTableId', 'Veuillez sélectionner une grille cible.');

            return;
        }

        $this->tempPath = $this->file->storeAs(
            'imports/datagrid',
            Str::uuid().'.'.$this->file->getClientOriginalExtension(),
            'local'
        );

        $import = new DatagridImport;
        Excel::import($import, Storage::disk('local')->path($this->tempPath));

        $headers = $import->getHeaders();
        $this->sampleValues = $import->getSampleValues();

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
                'label_true' => '',
                'label_false' => '',
                'options_raw' => '',
            ])
            ->all();

        $this->file = null;

        if ($this->importMode === 'update') {
            $this->buildColumnMapping();
        } else {
            $this->step = 2;
        }
    }

    // ── Mapping automatique (mode 'update') ───────────────────────

    private function buildColumnMapping(): void
    {
        $dgTable = DatagridTable::findOrFail($this->targetTableId);
        $dbCols = $dgTable->columns()->orderBy('sort_order')->get();

        $this->gridColumns = $dbCols->map(fn ($c) => [
            'name' => $c->name,
            'label' => $c->label,
        ])->toArray();

        $gridColNames = $dbCols->pluck('name')->all();

        $this->unmatchedColumns = [];
        $this->columnMapping = [];
        $needsMapping = false;

        foreach ($this->columns as $col) {
            $normalized = Str::snake(Str::ascii(str_replace(["'", "\u{2019}", '`'], '_', $col['header'])));

            if (in_array($normalized, $gridColNames, true)) {
                $this->columnMapping[$col['index']] = $normalized;
            } else {
                $this->unmatchedColumns[] = ['index' => $col['index'], 'header' => $col['header']];
                $this->columnMapping[$col['index']] = '';
                $needsMapping = true;
            }
        }

        $this->step = $needsMapping ? 2 : 3;
    }

    // ── Étape 2 : confirmation du mapping (mode 'update') ─────────

    public function confirmMapping(): void
    {
        $this->step = 3;
    }

    // ── Étape 2 : confirmation des colonnes (mode 'new') ──────────

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
            'columns.*.label_true' => ['nullable', 'string', 'max:50'],
            'columns.*.label_false' => ['nullable', 'string', 'max:50'],
            'columns.*.options_raw' => ['nullable', 'string', 'max:500'],
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
        $this->defaultVisibility = 'restricted';
        $this->sampleValues = [];
        $this->importMode = 'new';
        $this->updateMode = 'append';
        $this->targetTableId = null;
        $this->fileHasHeader = true;
        $this->errorMessage = null;
        $this->importedRows = 0;
        $this->importedTableId = null;
        $this->unmatchedColumns = [];
        $this->columnMapping = [];
        $this->gridColumns = [];
        $this->importId = null;
        $this->jobStatus = '';
        $this->jobProcessed = 0;
        $this->jobTotal = 0;

        if ($this->tempPath) {
            Storage::disk('local')->delete($this->tempPath);
            $this->tempPath = null;
        }
    }

    public function backToStep2(): void
    {
        $this->step = 2;
        $this->errorMessage = null;
        $this->importId = null;
        $this->jobStatus = '';
        $this->jobProcessed = 0;
        $this->jobTotal = 0;
    }

    // ── Étape 3 : dispatch du job ─────────────────────────────────

    public function runImport(): void
    {
        $this->errorMessage = null;

        if ($this->importMode === 'new') {
            $this->runNew();
        } else {
            $this->runUpdate();
        }
    }

    private function runNew(): void
    {
        $dgTable = null;
        $tableCreated = false;

        try {
            // ── 1. Métadonnées (DML) ──────────────────────────────
            $dgTable = DatagridTable::create([
                'name' => $this->tableName,
                'label' => $this->tableLabel,
                'description' => $this->tableDescription ?: null,
                'mysql_table' => $this->mysqlTableName(),
                'has_rgpd' => $this->hasRgpd,
                'is_persons_view' => false,
                'created_by' => auth()->id(),
            ]);

            foreach ($this->columns as $i => $col) {
                $options = null;
                if (($col['type'] ?? '') === DatagridColumnType::SELECT->value && filled($col['options_raw'] ?? '')) {
                    $options = collect(explode(',', $col['options_raw']))
                        ->map(fn ($v) => trim($v))
                        ->filter()
                        ->values()
                        ->all();
                }

                DatagridColumn::create([
                    'datagrid_table_id' => $dgTable->id,
                    'name' => $col['name'],
                    'label' => $col['label'],
                    'type' => $col['type'],
                    'required' => (bool) $col['required'],
                    'visible_by_default' => true,
                    'sort_order' => $i + 1,
                    'label_true' => filled($col['label_true'] ?? '') ? $col['label_true'] : null,
                    'label_false' => filled($col['label_false'] ?? '') ? $col['label_false'] : null,
                    'options' => $options,
                ]);
            }

            // ── 2. Table physique (DDL) ───────────────────────────
            Schema::connection('tenant')->create($this->mysqlTableName(), function (Blueprint $table) {
                $table->id();
                foreach ($this->columns as $col) {
                    $this->addDynamicColumn($table, $col);
                }
                $table->timestamps();
            });
            $tableCreated = true;

            // ── 3. Dispatch job ───────────────────────────────────
            $this->importId = (string) Str::uuid();
            $columnDefs = array_map(fn ($col) => ['name' => $col['name'], 'type' => $col['type']], $this->columns);

            Cache::put('datagrid_import:'.$this->importId, [
                'status' => 'pending',
                'processed' => 0,
                'total' => 0,
                'error' => '',
            ], 7200);

            ImportDatagridJob::dispatch(
                orgSlug: $this->resolveOrgSlug(),
                importId: $this->importId,
                tempStoragePath: $this->tempPath,
                mode: 'new',
                mysqlTable: $this->mysqlTableName(),
                datagridTableId: $dgTable->id,
                columnDefs: $columnDefs,
                updateMode: 'append',
                fileHasHeader: $this->fileHasHeader,
                columnMapping: [],
                defaultVisibility: $this->defaultVisibility,
            );

            $this->tempPath = null; // le job gère la suppression
            $this->jobStatus = 'pending';

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
            $dgTable = DatagridTable::findOrFail($this->targetTableId);

            // Construire columnDefs à partir du mapping
            $colTypeMap = $dgTable->columns()->pluck('type', 'name')
                ->map(fn ($t) => $t->value)->all();

            $columnDefs = [];
            foreach ($this->columnMapping as $excelIndex => $gridColName) {
                if ($gridColName === '') {
                    continue;
                }
                $columnDefs[(int) $excelIndex] = [
                    'name' => $gridColName,
                    'type' => $colTypeMap[$gridColName] ?? DatagridColumnType::TEXT->value,
                ];
            }

            $this->importId = (string) Str::uuid();

            Cache::put('datagrid_import:'.$this->importId, [
                'status' => 'pending',
                'processed' => 0,
                'total' => 0,
                'error' => '',
            ], 7200);

            ImportDatagridJob::dispatch(
                orgSlug: $this->resolveOrgSlug(),
                importId: $this->importId,
                tempStoragePath: $this->tempPath,
                mode: 'update',
                mysqlTable: $dgTable->mysql_table,
                datagridTableId: $dgTable->id,
                columnDefs: $columnDefs,
                updateMode: $this->updateMode,
                fileHasHeader: $this->fileHasHeader,
                columnMapping: $this->columnMapping,
                defaultVisibility: 'restricted',
            );

            $this->tempPath = null;
            $this->jobStatus = 'pending';

        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function resolveOrgSlug(): string
    {
        return app(TenantManager::class)->current()->slug;
    }

    private function mysqlTableName(): string
    {
        return str_starts_with($this->tableName, 'dg_') ? $this->tableName : 'dg_'.$this->tableName;
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
        return view('livewire.tenant.datagrid.import-wizard', [
            'columnTypes' => DatagridColumnType::options(),
            'sampleValues' => $this->sampleValues,
        ]);
    }
}
