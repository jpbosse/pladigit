<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\DatagridColumnType;
use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridTable;
use App\Services\TenantManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatagridController extends Controller
{
    public function index()
    {
        $orgs = Organization::orderBy('name')->get();
        $manager = app(TenantManager::class);
        $rows = [];

        foreach ($orgs as $org) {
            try {
                $manager->connectTo($org);

                $grids = DB::connection('tenant')
                    ->table('datagrid_tables')
                    ->get();

                $gridRows = [];
                foreach ($grids as $grid) {
                    $nbColonnes = (int) DB::connection('tenant')
                        ->table('datagrid_columns')
                        ->where('datagrid_table_id', $grid->id)
                        ->count();

                    $nbLignes = null;
                    try {
                        $mysqlTable = $grid->mysql_table;
                        $hasDeletedAt = DB::connection('tenant')
                            ->getSchemaBuilder()
                            ->hasColumn($mysqlTable, 'deleted_at');

                        $query = DB::connection('tenant')->table($mysqlTable);
                        if ($hasDeletedAt) {
                            $query->whereNull('deleted_at');
                        }
                        $nbLignes = (int) $query->count();
                    } catch (\Throwable) {
                    }

                    $gridRows[] = [
                        'id' => $grid->id,
                        'name' => $grid->name,
                        'label' => $grid->label,
                        'mysql_table' => $grid->mysql_table,
                        'nb_colonnes' => $nbColonnes,
                        'nb_lignes' => $nbLignes,
                        'supprimee' => $grid->deleted_at !== null,
                    ];
                }

                $rows[] = [
                    'org' => $org,
                    'grids' => $gridRows,
                    'error' => false,
                ];
            } catch (\Throwable) {
                $rows[] = [
                    'org' => $org,
                    'grids' => [],
                    'error' => true,
                ];
            }
        }

        return view('super-admin.datagrids.index', compact('rows'));
    }

    public function import(Organization $organization): View
    {
        $org = $organization;
        $manager = app(TenantManager::class);

        try {
            $manager->connectTo($org);
            $grids = DB::connection('tenant')->table('datagrid_tables')->get();
        } catch (\Throwable) {
            $grids = collect();
        }

        return view('super-admin.datagrids.import', compact('org', 'grids'));
    }

    public function edit(Organization $organization, string $table): View
    {
        app(TenantManager::class)->connectTo($organization);
        $grid = DatagridTable::findOrFail((int) $table);
        $columns = $grid->columns()->orderBy('sort_order')->get();

        return view('super-admin.datagrids.edit', [
            'org' => $organization,
            'table' => $grid,
            'columns' => $columns,
        ]);
    }

    public function update(Organization $organization, string $table): JsonResponse
    {
        app(TenantManager::class)->connectTo($organization);
        $grid = DatagridTable::findOrFail((int) $table);

        $data = request()->validate([
            'label' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'has_rgpd' => 'boolean',
        ]);

        $grid->update($data);

        return response()->json(['success' => true]);
    }

    public function destroy(Organization $organization, string $table): RedirectResponse
    {
        app(TenantManager::class)->connectTo($organization);
        $grid = DatagridTable::findOrFail((int) $table);

        $mysqlTable = $grid->mysql_table;
        $grid->delete();
        Schema::connection('tenant')->dropIfExists($mysqlTable);

        return redirect()->route('super-admin.datagrids.index')
            ->with('success', "Grille « {$grid->label} » supprimée.");
    }

    public function destroyColumn(Organization $organization, string $table, string $column): RedirectResponse
    {
        app(TenantManager::class)->connectTo($organization);
        $grid = DatagridTable::findOrFail((int) $table);
        $col = DatagridColumn::findOrFail((int) $column);

        DB::connection('tenant')->statement(
            "ALTER TABLE `{$grid->mysql_table}` DROP COLUMN `{$col->name}`"
        );
        $col->delete();

        return redirect()->route('super-admin.datagrids.edit', [$organization, $grid->id])
            ->with('success', "Colonne « {$col->name} » supprimée.");
    }

    public function editColumn(Organization $organization, string $table, string $column): View
    {
        app(TenantManager::class)->connectTo($organization);
        $grid = DatagridTable::findOrFail((int) $table);
        $col = DatagridColumn::findOrFail((int) $column);

        return view('super-admin.datagrids.edit-column', [
            'org' => $organization,
            'table' => $grid,
            'column' => $col,
        ]);
    }

    public function updateColumn(Organization $organization, string $table, string $column): JsonResponse
    {
        app(TenantManager::class)->connectTo($organization);
        $grid = DatagridTable::findOrFail((int) $table);
        $col = DatagridColumn::findOrFail((int) $column);

        $data = request()->validate([
            'name' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label' => 'required|string|max:100',
            'visible_by_default' => 'boolean',
            'required' => 'boolean',
            'is_rgpd_sensitive' => 'boolean',
            'sort_order' => 'integer|min:0',
            'type' => 'nullable|in:'.implode(',', DatagridColumnType::values()),
            'length' => 'nullable|integer|min:1|max:65535',
            'tab' => 'nullable|in:main,extra',
        ]);

        $oldName = $col->name;
        $oldType = $col->type;
        $newType = isset($data['type']) ? DatagridColumnType::from($data['type']) : $oldType;
        $oldLength = $col->length;
        $newLength = $data['length'] ?? $oldLength;

        $col->fill([
            'label' => $data['label'],
            'visible_by_default' => $data['visible_by_default'] ?? $col->visible_by_default,
            'required' => $data['required'] ?? $col->required,
            'is_rgpd_sensitive' => $data['is_rgpd_sensitive'] ?? $col->is_rgpd_sensitive,
            'sort_order' => $data['sort_order'] ?? $col->sort_order,
            'type' => $newType,
            'length' => $newLength,
            'tab' => $data['tab'] ?? $col->tab ?? 'main',
        ]);
        $col->save();

        $newName = $data['name'] ?? '';
        if ($newName !== '' && $newName !== $oldName) {
            if ($grid->columns()->where('name', $newName)->where('id', '!=', $col->id)->exists()) {
                return response()->json(['errors' => ['name' => ['Ce nom technique est déjà utilisé.']]], 422);
            }
            Schema::connection('tenant')->table($grid->mysql_table, function ($t) use ($oldName, $newName) {
                $t->renameColumn($oldName, $newName);
            });
            $col->name = $newName;
            $col->saveQuietly();
        }

        if ($newType !== $oldType) {
            $families = [
                [DatagridColumnType::TEXT, DatagridColumnType::EMAIL, DatagridColumnType::PHONE, DatagridColumnType::SELECT, DatagridColumnType::SIRET, DatagridColumnType::POSTAL_CODE],
                [DatagridColumnType::NUMBER],
                [DatagridColumnType::DATE],
                [DatagridColumnType::BOOLEAN],
            ];
            $compatible = false;
            foreach ($families as $family) {
                if (in_array($oldType, $family, true) && in_array($newType, $family, true)) {
                    $compatible = true;
                    break;
                }
            }
            if (! $compatible) {
                return response()->json(['error' => 'Type incompatible avec les données existantes'], 422);
            }

            Schema::connection('tenant')->table($grid->mysql_table, function ($t) use ($col, $newType, $newLength) {
                $nullable = ! $col->required;
                $colObj = match ($newType) {
                    DatagridColumnType::NUMBER => $t->decimal($col->name, 15, 4),
                    DatagridColumnType::DATE => $t->date($col->name),
                    DatagridColumnType::BOOLEAN => $t->boolean($col->name)->default(false),
                    DatagridColumnType::EMAIL => $t->string($col->name, $newLength ?? 255),
                    DatagridColumnType::PHONE => $t->string($col->name, $newLength ?? 30),
                    DatagridColumnType::SIRET => $t->string($col->name, 14),
                    DatagridColumnType::POSTAL_CODE => $t->string($col->name, 10),
                    DatagridColumnType::SELECT => $t->string($col->name, 100),
                    default => $t->string($col->name, $newLength ?? 255),
                };
                $nullable ? $colObj->nullable()->change() : $colObj->change();
            });
        } elseif ($newType->hasLength() && $newLength !== $oldLength && $newLength !== null) {
            $nullable = $col->required ? '' : ' NULL';
            DB::connection('tenant')->statement(
                "ALTER TABLE `{$grid->mysql_table}` MODIFY COLUMN `{$col->name}` VARCHAR({$newLength}){$nullable}"
            );
        }

        return response()->json(['success' => true]);
    }
}
