<?php

namespace App\Livewire\Tenant\Datagrid;

use App\Enums\DatagridColumnType;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridSavedView;
use App\Models\Tenant\DatagridTable;
use App\Services\DatagridPermissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ShowGrid extends Component
{
    use WithPagination;

    public DatagridTable $table;

    public array $filters = [];

    public string $sortColumn = '';

    public string $sortDirection = 'asc';

    public int $perPage = 10;

    /** Valeurs distinctes par colonne pour les selects de filtre */
    public array $distinctValues = [];

    public ?int $activeViewId = null;

    public string $newViewName = '';

    public bool $showColumnSettings = false;

    // États temporaires pour l'édition des colonnes (indexés par column->id)
    public array $columnEdits = [];

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
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $query = DB::connection('tenant')->table($this->table->mysql_table);

        foreach ($this->table->columns as $col) {
            $name = $col->name;

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

    public function updatedPerPage(): void
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

    public function render()
    {
        $columns = $this->table->columns()->get();
        $savedViews = $this->table->savedViews()->where('user_id', auth()->id())->get();

        return view('livewire.tenant.datagrid.show-grid', [
            'columns' => $columns,
            'savedViews' => $savedViews,
            'columnTypes' => DatagridColumnType::options(),
        ]);
    }
}
