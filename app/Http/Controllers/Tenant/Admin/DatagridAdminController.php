<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\DatagridColumnType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\DatagridAuditLog;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridSavedView;
use App\Models\Tenant\DatagridTable;
use App\Services\DatagridPermissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DatagridAdminController extends Controller
{
    public function index(): View
    {
        $tables = DatagridTable::withCount('columns')->orderBy('label')->get();

        return view('admin.datagrid.index', compact('tables'));
    }

    public function edit(DatagridTable $table): View
    {
        $columns = $table->columns()->orderBy('sort_order')->get();

        return view('admin.datagrid.edit', compact('table', 'columns'));
    }

    public function update(DatagridTable $table): JsonResponse
    {
        $data = request()->validate([
            'label'       => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'has_rgpd'    => 'boolean',
        ]);

        $table->update($data);

        return response()->json(['success' => true]);
    }

    public function updateColumn(DatagridTable $table, DatagridColumn $column): JsonResponse
    {
        $data = request()->validate([
            'label'              => 'required|string|max:100',
            'visible_by_default' => 'boolean',
            'required'           => 'boolean',
            'is_rgpd_sensitive'  => 'boolean',
            'sort_order'         => 'integer|min:0',
            'type'               => 'nullable|in:' . implode(',', DatagridColumnType::values()),
            'length'             => 'nullable|integer|min:1|max:65535',
        ]);

        $oldType   = $column->type;
        $newType   = isset($data['type']) ? DatagridColumnType::from($data['type']) : $oldType;
        $oldLength = $column->length;
        $newLength = $data['length'] ?? $oldLength;

        $column->fill([
            'label'              => $data['label'],
            'visible_by_default' => $data['visible_by_default'] ?? $column->visible_by_default,
            'required'           => $data['required'] ?? $column->required,
            'is_rgpd_sensitive'  => $data['is_rgpd_sensitive'] ?? $column->is_rgpd_sensitive,
            'sort_order'         => $data['sort_order'] ?? $column->sort_order,
            'type'               => $newType,
            'length'             => $newLength,
        ]);
        $column->save();

        if ($newType !== $oldType) {
            if (! $this->typesCompatible($oldType, $newType)) {
                return response()->json(['error' => 'Type incompatible avec les données existantes'], 422);
            }

            Schema::connection('tenant')->table($table->mysql_table, function (Blueprint $t) use ($column, $newType, $newLength) {
                $this->applySchemaChange($t, $column->name, $newType, $newLength, ! $column->required);
            });
        } elseif ($newType->hasLength() && $newLength !== $oldLength && $newLength !== null) {
            $colName  = $column->name;
            $nullable = $column->required ? '' : ' NULL';
            DB::connection('tenant')->statement(
                "ALTER TABLE `{$table->mysql_table}` MODIFY COLUMN `{$colName}` VARCHAR({$newLength}){$nullable}"
            );
        }

        app(DatagridPermissionService::class)->invalidateCacheForTable($table);

        return response()->json(['success' => true]);
    }

    public function destroyColumn(DatagridTable $table, DatagridColumn $column): RedirectResponse
    {
        DB::connection('tenant')->statement(
            "ALTER TABLE `{$table->mysql_table}` DROP COLUMN `{$column->name}`"
        );

        $column->delete();

        app(DatagridPermissionService::class)->invalidateCacheForTable($table);

        return redirect()->route('admin.datagrid.edit', $table)
            ->with('success', 'Colonne supprimée.');
    }

    public function editColumn(DatagridTable $table, DatagridColumn $column): View
    {
        return view('admin.datagrid.edit-column', compact('table', 'column'));
    }

    public function destroy(DatagridTable $table): RedirectResponse
    {
        DatagridAuditLog::where('datagrid_table_id', $table->id)->delete();
        DatagridSavedView::where('datagrid_table_id', $table->id)->delete();
        DatagridColumn::where('datagrid_table_id', $table->id)->delete();

        Schema::connection('tenant')->dropIfExists($table->mysql_table);

        $table->forceDelete();

        return redirect()->route('admin.datagrid.index')
            ->with('success', "Grille « {$table->label} » supprimée.");
    }

    // ── Helpers privés ───────────────────────────────────────────────────────

    private function typesCompatible(DatagridColumnType $from, DatagridColumnType $to): bool
    {
        $families = [
            [DatagridColumnType::TEXT, DatagridColumnType::EMAIL, DatagridColumnType::PHONE, DatagridColumnType::SELECT, DatagridColumnType::SIRET, DatagridColumnType::POSTAL_CODE],
            [DatagridColumnType::NUMBER],
            [DatagridColumnType::DATE],
            [DatagridColumnType::BOOLEAN],
        ];

        foreach ($families as $family) {
            if (in_array($from, $family, true) && in_array($to, $family, true)) {
                return true;
            }
        }

        return false;
    }

    private function applySchemaChange(Blueprint $t, string $colName, DatagridColumnType $type, ?int $length, bool $nullable): void
    {
        $col = match ($type) {
            DatagridColumnType::NUMBER      => $t->decimal($colName, 15, 4),
            DatagridColumnType::DATE        => $t->date($colName),
            DatagridColumnType::BOOLEAN     => $t->boolean($colName)->default(false),
            DatagridColumnType::EMAIL       => $t->string($colName, $length ?? 255),
            DatagridColumnType::PHONE       => $t->string($colName, $length ?? 30),
            DatagridColumnType::SIRET       => $t->string($colName, 14),
            DatagridColumnType::POSTAL_CODE => $t->string($colName, 10),
            DatagridColumnType::SELECT      => $t->string($colName, 100),
            default                         => $t->string($colName, $length ?? 255),
        };

        if ($nullable) {
            $col->nullable()->change();
        } else {
            $col->change();
        }
    }
}
