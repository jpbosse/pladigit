<?php

namespace App\Livewire\Tenant\Datagrid;

use App\Enums\DatagridColumnType;
use App\Exports\DatagridExport;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridSavedView;
use App\Models\Tenant\DatagridTable;
use App\Services\DatagridPermissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Composant principal DataGrid — liste, filtres, tri, pagination, exports.
 *
 * Ce composant est volontairement limité à la liste et à ses contrôles.
 * La modal d'édition est déléguée au composant EditRowModal (enfant),
 * la modal d'ajout à AddRowModal (enfant).
 *
 * Communication :
 *   - ShowGrid → EditRowModal  : dispatch('open-edit-modal', rowId)
 *   - EditRowModal → ShowGrid  : $listeners row-updated, row-deleted → resetRows()
 *   - ShowGrid → AddRowModal   : dispatch('open-add-modal')
 *   - AddRowModal → ShowGrid   : $listeners row-added → resetRows()
 */
class ShowGrid extends Component
{
    use WithPagination;

    public DatagridTable $table;

    /** @var array<string, mixed> */
    public array $filters = [];

    public string $sortColumn = '';

    public string $sortDirection = 'asc';

    public int $perPage = 10;

    /** @var array<string, array<int, mixed>> Valeurs distinctes par colonne pour les selects de filtre */
    public array $distinctValues = [];

    public ?int $activeViewId = null;

    public string $newViewName = '';

    public bool $showColumnSettings = false;

    /** @var array<int, array<string, mixed>> États temporaires pour l'édition des colonnes (indexés par column->id) */
    public array $columnEdits = [];

    /** @var array{can_write: bool, can_delete: bool, can_export: bool} Droits de l'utilisateur courant sur cette grille */
    public array $userPerms = [
        'can_write' => false,
        'can_delete' => false,
        'can_export' => false,
    ];

    /** @var array<int, int> IDs des colonnes actuellement visibles */
    public array $visibleColumns = [];

    /** Panneau de sélection des colonnes ouvert/fermé */
    public bool $showColumnPicker = false;

    /** @var array<int, int> IDs des colonnes masquées par les droits (2.14) — non modifiable par l'utilisateur */
    public array $forbiddenColumns = [];

    // ── Listeners (événements remontés par les composants enfants) ────────────

    /** @return array<string, string> */
    protected function getListeners(): array
    {
        return [
            'row-updated' => 'resetRows',
            'row-deleted' => 'resetRows',
            'row-added' => 'resetRows',
        ];
    }

    public function resetRows(): void
    {
        unset($this->rows);
    }

    // ── Montage ───────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $initialFilters
     *  @param array<string, string> $initialSort */
    public function mount(DatagridTable $table, array $initialFilters = [], array $initialSort = []): void
    {
        if (! $table->canRead(auth()->user())) {
            abort(403);
        }

        $this->table = $table;
        $this->filters = $initialFilters;

        if (! empty($initialSort['column'])) {
            // Tri explicite passé en paramètre (vue sauvegardée, lien direct)
            $this->sortColumn    = $initialSort['column'];
            $this->sortDirection = $initialSort['direction'] ?? 'asc';
        } elseif ($table->default_sort_column !== null && $table->default_sort_column !== '') {
            // Tri par défaut configuré sur la grille par l'admin (2.16)
            $this->sortColumn    = $table->default_sort_column;
            $this->sortDirection = $table->default_sort_direction ?? 'asc';
        }

        foreach ($table->columns as $col) {
            $this->columnEdits[$col->id] = [
                'label' => $col->label,
                'type' => $col->type->value,
                'length' => $col->length,
                'required' => $col->required,
                'visible_by_default' => $col->visible_by_default,
                'is_rgpd_sensitive' => $col->is_rgpd_sensitive,
                'sort_order' => $col->sort_order,
            ];
        }

        // Valeurs distinctes pour BOOLEAN et SELECT
        foreach ($table->columns as $col) {
            if (in_array($col->type, [DatagridColumnType::BOOLEAN, DatagridColumnType::SELECT], true)) {
                $this->distinctValues[$col->name] = DB::connection('tenant')
                    ->table($table->mysql_table)
                    ->select($col->name)
                    ->whereNotNull($col->name)
                    ->distinct()
                    ->orderBy($col->name)
                    ->pluck($col->name)
                    ->toArray();
            }
        }

        // Droits résolus une seule fois
        $perms = app(DatagridPermissionService::class)->effectivePermissions(auth()->user(), $table);
        $this->userPerms = [
            'can_write' => $perms['can_write'],
            'can_delete' => $perms['can_delete'],
            'can_export' => $perms['can_export'],
        ];

        // Colonnes autorisées par les droits (2.14)
        $allColNames = $table->columns->pluck('name')->toArray();
        $allowedColNames = app(DatagridPermissionService::class)
            ->visibleColumns(auth()->user(), $table, $allColNames);

        // Colonnes visibles par défaut — intersectées avec les colonnes autorisées
        $this->visibleColumns = $table->columns
            ->whereIn('name', $allowedColNames)
            ->where('visible_by_default', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Colonnes interdites — ne peuvent jamais être affichées
        $this->forbiddenColumns = $table->columns
            ->whereNotIn('name', $allowedColNames)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    // ── Données ───────────────────────────────────────────────────────────────

    /** @return LengthAwarePaginator<int, object> */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $query = DB::connection('tenant')->table($this->table->mysql_table);

        foreach ($this->table->columns as $col) {
            $name = $col->name;

            if (! in_array($col->id, $this->visibleColumns, true)) {
                continue;
            }

            if ($col->type === DatagridColumnType::DATE) {
                $from = $this->filters[$name.'_from'] ?? '';
                $to = $this->filters[$name.'_to'] ?? '';
                if ($from !== '') {
                    $query->where($name, '>=', $from);
                }
                if ($to !== '') {
                    $query->where($name, '<=', $to);
                }

                continue;
            }

            if ($col->type === DatagridColumnType::NUMBER) {
                $min = $this->filters[$name.'_min'] ?? '';
                $max = $this->filters[$name.'_max'] ?? '';
                if ($min !== '') {
                    $query->where($name, '>=', $min);
                }
                if ($max !== '') {
                    $query->where($name, '<=', $max);
                }

                continue;
            }

            $val = $this->filters[$name] ?? '';
            if ($val === '') {
                continue;
            }

            if ($col->type === DatagridColumnType::BOOLEAN || $col->type === DatagridColumnType::SELECT) {
                $query->where($name, $val);

                continue;
            }

            $query->where($name, 'like', '%'.$val.'%');
        }

        if ($this->sortColumn !== '') {
            $query->orderBy($this->sortColumn, $this->sortDirection);
        }

        return $query->paginate($this->perPage);
    }

    // ── Filtres / tri / pagination ────────────────────────────────────────────

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function applyFilter(string $column, string $value): void
    {
        $this->filters[$column] = $value;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->filters = [];
        $this->activeViewId = null;
        $this->resetPage();
        $this->dispatch('$refresh');
    }

    public function sortBy(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    // ── Vues sauvegardées ─────────────────────────────────────────────────────

    public function updatedActiveViewId(mixed $value): void
    {
        $viewId = (int) $value;

        if ($viewId === 0) {
            $this->filters = [];
            $this->resetPage();

            return;
        }

        $view = DatagridSavedView::where('datagrid_table_id', $this->table->id)
            ->where('user_id', auth()->id())
            ->findOrFail($viewId);

        $this->filters = $view->filters ?? [];
        $this->resetPage();
    }

    public function saveCurrentView(): void
    {
        $this->validate(['newViewName' => 'required|string|max:100']);

        $view = DatagridSavedView::create([
            'datagrid_table_id' => $this->table->id,
            'user_id' => auth()->id(),
            'name' => $this->newViewName,
            'filters' => $this->filters,
        ]);

        $this->activeViewId = $view->id;
        $this->newViewName = '';

        $this->dispatch('view-saved');
    }

    public function deleteView(int $viewId): void
    {
        $view = DatagridSavedView::where('datagrid_table_id', $this->table->id)
            ->where('user_id', auth()->id())
            ->findOrFail($viewId);

        $view->delete();

        if ($this->activeViewId === $viewId) {
            $this->activeViewId = null;
        }
    }

    // ── Colonnes ──────────────────────────────────────────────────────────────

    public function updateColumn(int $columnId): void
    {
        $this->validate([
            "columnEdits.{$columnId}.label" => 'required|string|max:100',
            "columnEdits.{$columnId}.type" => 'required|in:'.implode(',', DatagridColumnType::values()),
            "columnEdits.{$columnId}.length" => 'nullable|integer|min:1|max:65535',
            "columnEdits.{$columnId}.required" => 'boolean',
            "columnEdits.{$columnId}.visible_by_default" => 'boolean',
            "columnEdits.{$columnId}.is_rgpd_sensitive" => 'boolean',
            "columnEdits.{$columnId}.sort_order" => 'integer|min:0',
        ]);

        $data = $this->columnEdits[$columnId];
        $column = DatagridColumn::findOrFail($columnId);
        $oldType = $column->type;
        $newType = DatagridColumnType::from($data['type']);
        $oldLength = $column->length;
        $newLength = $data['length'] ?? null;

        $column->fill([
            'label' => $data['label'],
            'visible_by_default' => $data['visible_by_default'],
            'required' => $data['required'],
            'is_rgpd_sensitive' => $data['is_rgpd_sensitive'],
            'sort_order' => $data['sort_order'],
            'type' => $newType,
            'length' => $newLength,
        ])->save();

        if ($newType !== $oldType) {
            if (! $this->typesCompatible($oldType, $newType)) {
                $this->addError("columnEdits.{$columnId}.type", 'Type incompatible avec les données existantes');
                $column->fill(['type' => $oldType, 'length' => $oldLength])->save();

                return;
            }

            Schema::connection('tenant')->table($this->table->mysql_table, function (Blueprint $t) use ($column, $newType, $newLength) {
                $this->applySchemaChange($t, $column->name, $newType, $newLength, ! $column->required);
            });
        } elseif ($newType->hasLength() && $newLength !== $oldLength && $newLength !== null) {
            $colName = $column->name;
            $nullable = $column->required ? '' : ' NULL';
            DB::connection('tenant')->statement(
                "ALTER TABLE `{$this->table->mysql_table}` MODIFY COLUMN `{$colName}` VARCHAR({$newLength}){$nullable}"
            );
        }

        app(DatagridPermissionService::class)->invalidateCacheForTable($this->table);
        $this->dispatch('column-updated', columnId: $columnId);
    }

    // ── Ouverture des modales enfants ─────────────────────────────────────────

    /**
     * Délègue l'ouverture de la modal d'édition au composant EditRowModal.
     * Le composant enfant écoute l'événement 'open-edit-modal'.
     */
    public function openEdit(int $rowId): void
    {
        $this->dispatch('open-edit-modal', rowId: $rowId);
    }

    /**
     * Délègue l'ouverture de la modal d'ajout au composant AddRowModal.
     * Le composant enfant écoute l'événement 'open-add-modal'.
     */
    public function openAdd(): void
    {
        if (! $this->userPerms['can_write']) {
            abort(403);
        }
        $this->dispatch('open-add-modal');
    }

    // ── Visibilité des colonnes ───────────────────────────────────────────────

    public function showAllColumns(): void
    {
        $this->visibleColumns = $this->table->columns
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => ! in_array($id, $this->forbiddenColumns, true))
            ->values()
            ->toArray();
    }

    public function resetColumnsToDefault(): void
    {
        $defaultIds = $this->table->columns
            ->where('visible_by_default', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $toHide = $this->table->columns->whereNotIn('id', $defaultIds);
        foreach ($toHide as $col) {
            unset(
                $this->filters[$col->name],
                $this->filters[$col->name.'_from'],
                $this->filters[$col->name.'_to'],
                $this->filters[$col->name.'_min'],
                $this->filters[$col->name.'_max'],
            );
        }

        $this->visibleColumns = $defaultIds;
        $this->resetPage();
    }

    public function toggleColumnPicker(): void
    {
        $this->showColumnPicker = ! $this->showColumnPicker;
    }

    public function toggleColumn(int $colId): void
    {
        // Empêcher d'afficher une colonne interdite par les droits
        if (in_array($colId, $this->forbiddenColumns, true)) {
            return;
        }

        if (in_array($colId, $this->visibleColumns, true)) {
            $this->visibleColumns = array_values(
                array_filter($this->visibleColumns, fn ($id) => $id !== $colId)
            );
            $col = $this->table->columns->firstWhere('id', $colId);
            if ($col) {
                unset(
                    $this->filters[$col->name],
                    $this->filters[$col->name.'_from'],
                    $this->filters[$col->name.'_to'],
                    $this->filters[$col->name.'_min'],
                    $this->filters[$col->name.'_max'],
                );
                $this->resetPage();
            }
        } else {
            $this->visibleColumns[] = $colId;
        }
    }

    // ── Exports ───────────────────────────────────────────────────────────────

    public function exportExcel(): BinaryFileResponse
    {
        if (! $this->userPerms['can_export']) {
            abort(403);
        }

        return Excel::download(
            new DatagridExport($this->table, $this->visibleColumns, $this->filters),
            $this->table->label.'.xlsx'
        );
    }

    public function exportOds(): BinaryFileResponse
    {
        if (! $this->userPerms['can_export']) {
            abort(403);
        }

        return Excel::download(
            new DatagridExport($this->table, $this->visibleColumns, $this->filters),
            $this->table->label.'.ods',
            \Maatwebsite\Excel\Excel::ODS
        );
    }

    // ── Rendu ─────────────────────────────────────────────────────────────────

    public function render(): View
    {
        $columns = $this->table->columns()->get();
        $savedViews = $this->table->savedViews()->where('user_id', auth()->id())->get();

        return view('livewire.tenant.datagrid.show-grid', [
            'columns'        => $columns->whereNotIn('id', $this->forbiddenColumns),
            'savedViews'     => $savedViews,
            'columnTypes'    => DatagridColumnType::options(),
            'visibleColumns' => $this->visibleColumns,
            'forbiddenColumns' => $this->forbiddenColumns,
        ]);
    }

    // ── Méthodes privées ─────────────────────────────────────────────────────

    private function typesCompatible(DatagridColumnType $from, DatagridColumnType $to): bool
    {
        $stringFamily = [DatagridColumnType::TEXT, DatagridColumnType::EMAIL, DatagridColumnType::PHONE, DatagridColumnType::SELECT, DatagridColumnType::SIRET, DatagridColumnType::POSTAL_CODE, DatagridColumnType::NOM_PERSONNE];
        $numericFamily = [DatagridColumnType::NUMBER];
        $dateFamily = [DatagridColumnType::DATE];
        $boolFamily = [DatagridColumnType::BOOLEAN];

        foreach ([$stringFamily, $numericFamily, $dateFamily, $boolFamily] as $family) {
            if (in_array($from, $family, true) && in_array($to, $family, true)) {
                return true;
            }
        }

        return false;
    }

    private function applySchemaChange(Blueprint $t, string $colName, DatagridColumnType $type, ?int $length, bool $nullable): void
    {
        $col = match ($type) {
            DatagridColumnType::NUMBER => $t->decimal($colName, 15, 4),
            DatagridColumnType::DATE => $t->date($colName),
            DatagridColumnType::BOOLEAN => $t->boolean($colName)->default(false),
            DatagridColumnType::EMAIL => $t->string($colName, $length ?? 255),
            DatagridColumnType::PHONE => $t->string($colName, $length ?? 30),
            DatagridColumnType::SIRET => $t->string($colName, 14),
            DatagridColumnType::POSTAL_CODE => $t->string($colName, 10),
            DatagridColumnType::SELECT => $t->string($colName, 100),
            default => $t->string($colName, $length ?? 255),
        };

        if ($nullable) {
            $col->nullable()->change();
        } else {
            $col->change();
        }
    }
}
