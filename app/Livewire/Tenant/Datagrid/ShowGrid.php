<?php

namespace App\Livewire\Tenant\Datagrid;

use App\Enums\DatagridAuditAction;
use App\Enums\DatagridColumnType;
use App\Models\Tenant\DatagridAuditLog;
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

class ShowGrid extends Component
{
    use WithPagination;

    public DatagridTable $table;

    /** @var array<string, mixed> */
    public array $filters = [];

    public string $sortColumn = '';

    public string $sortDirection = 'asc';

    public int $perPage = 10;

    /** Recherche globale multicolonne */
    public string $search = '';

    /** @var array<string, array<int, mixed>> Valeurs distinctes par colonne pour les selects de filtre */
    public array $distinctValues = [];

    public ?int $activeViewId = null;

    public string $newViewName = '';

    public bool $showColumnSettings = false;

    /** @var array<int, array<string, mixed>> États temporaires pour l'édition des colonnes (indexés par column->id) */
    public array $columnEdits = [];

    // ── Édition de ligne ─────────────────────────────────────────────────────

    /** ID de la ligne en cours d'édition (null = modal fermée) */
    public ?int $editingRowId = null;

    /** @var array<string, mixed> Valeurs du formulaire d'édition */
    public array $editForm = [];

    /** @var array{can_write: bool, can_delete: bool} Droits de l'utilisateur courant sur cette grille */
    public array $userPerms = [
        'can_write' => false,
        'can_delete' => false,
    ];

    /** @var array<int, int> IDs des colonnes actuellement visibles (initialisées depuis visible_by_default) */
    public array $visibleColumns = [];

    /** Panneau de sélection des colonnes ouvert/fermé */
    public bool $showColumnPicker = false;

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
            $this->sortColumn = $initialSort['column'];
            $this->sortDirection = $initialSort['direction'] ?? 'asc';
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

        // Charger les valeurs distinctes pour BOOLEAN et SELECT
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

        // Résoudre les droits une seule fois au mount
        $perms = app(DatagridPermissionService::class)->effectivePermissions(auth()->user(), $table);
        $this->userPerms = [
            'can_write' => $perms['can_write'],
            'can_delete' => $perms['can_delete'],
        ];

        // Initialiser les colonnes visibles depuis visible_by_default
        $this->visibleColumns = $table->columns
            ->where('visible_by_default', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /** @return LengthAwarePaginator<int, object> */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $query = DB::connection('tenant')->table($this->table->mysql_table);

        foreach ($this->table->columns as $col) {
            $name = $col->name;

            // Ne pas appliquer les filtres des colonnes masquées
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

        // Recherche globale multicolonne
        if ($this->search !== '') {
            $searchableTypes = [
                DatagridColumnType::TEXT, DatagridColumnType::EMAIL,
                DatagridColumnType::PHONE, DatagridColumnType::SELECT,
                DatagridColumnType::SIRET, DatagridColumnType::POSTAL_CODE,
            ];
            $term = $this->search;
            $query->where(function ($q) use ($term, $searchableTypes) {
                foreach ($this->table->columns as $col) {
                    if (! in_array($col->id, $this->visibleColumns, true)) {
                        continue;
                    }
                    if (in_array($col->type, $searchableTypes, true)) {
                        $q->orWhere($col->name, 'like', '%'.$term.'%');
                    }
                }
            });
        }

        if ($this->sortColumn !== '') {
            $query->orderBy($this->sortColumn, $this->sortDirection);
        }

        return $query->paginate($this->perPage);
    }

    /** @return int Nombre total de lignes sans aucun filtre */
    #[Computed]
    public function totalCount(): int
    {
        return DB::connection('tenant')->table($this->table->mysql_table)->count();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
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
        $this->search = '';
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
        ]);
        $column->save();

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

    // ── Édition / suppression de ligne ───────────────────────────────────────

    /**
     * Ouvre la modal d'édition pour la ligne donnée.
     * Accessible à tous les utilisateurs ayant can_read — les boutons d'action
     * sont conditionnés à can_write / can_delete dans la vue.
     */
    public function openEdit(int $rowId): void
    {
        $row = DB::connection('tenant')
            ->table($this->table->mysql_table)
            ->where('id', $rowId)
            ->first();

        if (! $row) {
            return;
        }

        $this->editingRowId = $rowId;
        $rawRow = (array) $row;

        // Formater les valeurs pour l'affichage dans le formulaire
        $formatted = $rawRow;
        foreach ($this->table->columns as $col) {
            $val = $rawRow[$col->name] ?? null;
            if ($val === null) {
                continue;
            }

            $formatted[$col->name] = match ($col->type) {
                DatagridColumnType::PHONE => $this->formatPhone((string) $val),
                DatagridColumnType::SIRET => $this->formatSiret((string) $val),
                DatagridColumnType::POSTAL_CODE => str_pad((string) preg_replace('/\D/', '', (string) $val), 5, '0', STR_PAD_LEFT),
                DatagridColumnType::BOOLEAN => in_array($val, ['1', 1, true, 'true', 'oui'], false) ? '1' : '0',
                // Supprimer les zéros trailing : 25.2500 → 25.25, 6254521.9900 → 6254521.99
                DatagridColumnType::NUMBER => rtrim(rtrim((string) $val, '0'), '.') ?: '0',
                default => $val,
            };
        }

        $this->editForm = $formatted;
    }

    private function formatPhone(string $val): string
    {
        $prefix = str_starts_with($val, '+') ? '+' : '';
        $digits = (string) preg_replace('/\D/', '', $val);

        if (! $prefix && strlen($digits) === 10) {
            return implode(' ', str_split($digits, 2));
        }

        return $prefix.$digits;
    }

    private function denormalizePhone(string $val): string
    {
        $prefix = str_starts_with($val, '+') ? '+' : '';
        $digits = (string) preg_replace('/\D/', '', $val);

        return $prefix.$digits;
    }

    private function formatSiret(string $val): string
    {
        $digits = (string) preg_replace('/\D/', '', $val);
        $padded = str_pad($digits, 14, '0', STR_PAD_LEFT);

        if (strlen($padded) === 14) {
            return substr($padded, 0, 3).' '.substr($padded, 3, 3).' '.substr($padded, 6, 3).' '.substr($padded, 9, 5);
        }

        return $val;
    }

    /** Ferme la modal sans sauvegarder. */
    public function closeEdit(): void
    {
        $this->editingRowId = null;
        $this->editForm = [];
        $this->resetValidation();
    }

    /**
     * Sauvegarde les modifications de la ligne en cours d'édition.
     * Trace chaque colonne modifiée dans l'audit log.
     */
    public function saveEdit(): void
    {
        if (! $this->userPerms['can_write']) {
            abort(403);
        }

        if ($this->editingRowId === null) {
            return;
        }

        // Construire les règles de validation dynamiquement
        $rules = [];
        foreach ($this->table->columns as $col) {
            if ($col->name === 'id') {
                continue;
            }
            $rule = $col->required ? 'required' : 'nullable';
            $rule .= match ($col->type) {
                DatagridColumnType::NUMBER => '|numeric',
                DatagridColumnType::DATE => '|date',
                DatagridColumnType::EMAIL => '|email|max:'.($col->length ?? 255),
                DatagridColumnType::BOOLEAN => '|boolean',
                DatagridColumnType::SIRET => '|max:19', // avec espaces : 553 279 879 00672
                DatagridColumnType::POSTAL_CODE => '|max:10',
                DatagridColumnType::PHONE => '|max:'.($col->length ?? 30),
                default => '|max:'.($col->length ?? 255),
            };
            $rules["editForm.{$col->name}"] = $rule;
        }

        $this->validate($rules);

        // Récupérer les anciennes valeurs pour l'audit
        $oldRow = (array) DB::connection('tenant')
            ->table($this->table->mysql_table)
            ->where('id', $this->editingRowId)
            ->first();

        // Construire le payload de mise à jour (sans id) + dénormaliser avant stockage
        $colTypeMap = $this->table->columns->keyBy('name');
        $updateData = collect($this->editForm)
            ->except(['id'])
            ->map(function ($v, $colName) use ($colTypeMap) {
                if ($v === '' || $v === null) {
                    return null;
                }
                $col = $colTypeMap->get($colName);
                if (! $col) {
                    return $v;
                }

                return match ($col->type) {
                    // Retirer les espaces de formatage avant stockage
                    DatagridColumnType::SIRET => preg_replace('/\D/', '', (string) $v),
                    DatagridColumnType::PHONE => $this->denormalizePhone((string) $v),
                    DatagridColumnType::POSTAL_CODE => str_pad((string) preg_replace('/\D/', '', (string) $v), 5, '0', STR_PAD_LEFT),
                    default => $v,
                };
            })
            ->toArray();

        DB::connection('tenant')
            ->table($this->table->mysql_table)
            ->where('id', $this->editingRowId)
            ->update($updateData);

        // Audit log — une entrée par colonne modifiée
        foreach ($updateData as $colName => $newVal) {
            $oldVal = $oldRow[$colName] ?? null;
            $oldStr = $oldVal !== null ? (string) $oldVal : null;
            $newStr = $newVal !== null ? (string) $newVal : null;

            if ($oldStr === $newStr) {
                continue;
            }

            DatagridAuditLog::create([
                'datagrid_table_id' => $this->table->id,
                'user_id' => auth()->id(),
                'action' => DatagridAuditAction::WRITE->value,
                'row_id' => $this->editingRowId,
                'column_name' => $colName,
                'old_value' => $oldStr,
                'new_value' => $newStr,
                'ip_address' => request()->ip(),
            ]);
        }

        $this->closeEdit();
        unset($this->rows);
        $this->dispatch('row-updated');
    }

    /**
     * Supprime la ligne en cours d'édition après confirmation.
     * Trace la suppression dans l'audit log (ligne entière, column_name null).
     */
    public function deleteRow(): void
    {
        if (! $this->userPerms['can_delete']) {
            abort(403);
        }

        if ($this->editingRowId === null) {
            return;
        }

        // Snapshot de la ligne pour l'audit
        $oldRow = (array) DB::connection('tenant')
            ->table($this->table->mysql_table)
            ->where('id', $this->editingRowId)
            ->first();

        DB::connection('tenant')
            ->table($this->table->mysql_table)
            ->where('id', $this->editingRowId)
            ->delete();

        DatagridAuditLog::create([
            'datagrid_table_id' => $this->table->id,
            'user_id' => auth()->id(),
            'action' => DatagridAuditAction::DELETE->value,
            'row_id' => $this->editingRowId,
            'column_name' => null,
            'old_value' => json_encode($oldRow, JSON_UNESCAPED_UNICODE),
            'new_value' => null,
            'ip_address' => request()->ip(),
        ]);

        $this->closeEdit();
        unset($this->rows);
        $this->dispatch('row-deleted');
    }

    // ── Méthodes privées ─────────────────────────────────────────────────────

    private function typesCompatible(DatagridColumnType $from, DatagridColumnType $to): bool
    {
        $stringFamily = [DatagridColumnType::TEXT, DatagridColumnType::EMAIL, DatagridColumnType::PHONE, DatagridColumnType::SELECT, DatagridColumnType::SIRET, DatagridColumnType::POSTAL_CODE];
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

    public function showAllColumns(): void
    {
        $this->visibleColumns = $this->table->columns
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    public function resetColumnsToDefault(): void
    {
        $defaultIds = $this->table->columns
            ->where('visible_by_default', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Effacer les filtres des colonnes qui vont être masquées
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
        if (in_array($colId, $this->visibleColumns, true)) {
            $this->visibleColumns = array_values(
                array_filter($this->visibleColumns, fn ($id) => $id !== $colId)
            );
            // Effacer les filtres de la colonne masquée
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

    public function render(): View
    {
        $columns = $this->table->columns()->get();
        $savedViews = $this->table->savedViews()->where('user_id', auth()->id())->get();

        return view('livewire.tenant.datagrid.show-grid', [
            'columns' => $columns,
            'savedViews' => $savedViews,
            'columnTypes' => DatagridColumnType::options(),
            'visibleColumns' => $this->visibleColumns,
        ]);
    }
}
