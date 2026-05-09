<?php

namespace App\Http\Controllers\Datagrid;

use App\Enums\DatagridAuditAction;
use App\Enums\DatagridColumnType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\DatagridAuditLog;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridFolder;
use App\Models\Tenant\DatagridSavedView;
use App\Models\Tenant\DatagridTable;
use App\Services\DatagridPermissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DatagridController extends Controller
{
    public function import(): View
    {
        return view('datagrid.import');
    }

    public function index(): View
    {
        $folders = DatagridFolder::with(['tables' => fn ($q) => $q->withCount('columns')])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $unfoldered = DatagridTable::withCount('columns')
            ->whereNull('folder_id')
            ->orderBy('label')
            ->get();

        $allFolders = DatagridFolder::orderBy('label')->get();

        $totalCount = DatagridTable::count();

        return view('datagrid.index', compact('folders', 'unfoldered', 'allFolders', 'totalCount'));
    }

    public function show(DatagridTable $table): View
    {
        $user = auth()->user();

        if (! $table->canRead($user)) {
            abort(403);
        }

        $columns = $table->columns()->get();
        $savedViews = $table->savedViews()->where('user_id', $user->id)->get();
        $filters = request()->input('filters', []);
        $sort = [
            'column' => request()->input('sort', ''),
            'direction' => request()->input('direction', 'asc'),
        ];

        $query = DB::connection('tenant')->table($table->mysql_table);

        foreach ($filters as $col => $val) {
            if ($val !== '' && $val !== null) {
                $query->where($col, 'like', '%'.$val.'%');
            }
        }

        if ($sort['column']) {
            $query->orderBy($sort['column'], $sort['direction'] === 'desc' ? 'desc' : 'asc');
        }

        $rows = $query->paginate(50);

        if ($table->has_rgpd) {
            DatagridAuditLog::create([
                'datagrid_table_id' => $table->id,
                'user_id' => $user->id,
                'action' => DatagridAuditAction::READ,
                'ip_address' => request()->ip(),
            ]);
        }

        return view('datagrid.show', compact('table', 'columns', 'rows', 'savedViews', 'filters', 'sort'));
    }

    public function updateColumn(DatagridTable $table, DatagridColumn $column): JsonResponse
    {
        $data = request()->validate([
            'label' => 'required|string|max:100',
            'visible_by_default' => 'boolean',
            'required' => 'boolean',
            'is_rgpd_sensitive' => 'boolean',
            'sort_order' => 'integer|min:0',
            'type' => 'nullable|in:'.implode(',', DatagridColumnType::values()),
            'length' => 'nullable|integer|min:1|max:65535',
        ]);

        $oldType = $column->type;
        $newType = isset($data['type']) ? DatagridColumnType::from($data['type']) : $oldType;
        $oldLength = $column->length;
        $newLength = $data['length'] ?? $oldLength;

        // Mise à jour des métadonnées
        $column->fill([
            'label' => $data['label'],
            'visible_by_default' => $data['visible_by_default'] ?? $column->visible_by_default,
            'required' => $data['required'] ?? $column->required,
            'is_rgpd_sensitive' => $data['is_rgpd_sensitive'] ?? $column->is_rgpd_sensitive,
            'sort_order' => $data['sort_order'] ?? $column->sort_order,
            'type' => $newType,
            'length' => $newLength,
        ]);
        $column->save();

        // Changement de type → ALTER TABLE
        if ($newType !== $oldType) {
            if (! $this->typesCompatible($oldType, $newType)) {
                return response()->json(['error' => 'Type incompatible avec les données existantes'], 422);
            }

            Schema::connection('tenant')->table($table->mysql_table, function (Blueprint $t) use ($column, $newType, $newLength) {
                $this->applySchemaChange($t, $column->name, $newType, $newLength, ! $column->required);
            });
        } elseif ($newType->hasLength() && $newLength !== $oldLength && $newLength !== null) {
            // Seule la longueur a changé
            $colName = $column->name;
            $nullable = $column->required ? '' : ' NULL';
            DB::connection('tenant')->statement(
                "ALTER TABLE `{$table->mysql_table}` MODIFY COLUMN `{$colName}` VARCHAR({$newLength}){$nullable}"
            );
        }

        app(DatagridPermissionService::class)->invalidateCacheForTable($table);

        return response()->json(['success' => true]);
    }

    public function storeView(DatagridTable $table): JsonResponse
    {
        $data = request()->validate([
            'name' => 'required|string|max:100',
            'filters' => 'array',
        ]);

        $view = DatagridSavedView::create([
            'datagrid_table_id' => $table->id,
            'user_id' => auth()->id(),
            'name' => $data['name'],
            'filters' => $data['filters'] ?? [],
        ]);

        return response()->json($view, 201);
    }

    public function destroyView(DatagridTable $table, DatagridSavedView $view): JsonResponse
    {
        if ($view->user_id !== auth()->id()) {
            abort(403);
        }

        $view->delete();

        return response()->json(['success' => true]);
    }

    // ── Helpers privés ───────────────────────────────────────────────────────

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
}
