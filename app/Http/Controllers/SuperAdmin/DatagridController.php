<?php

namespace App\Http\Controllers\SuperAdmin;

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
                        'id'          => $grid->id,
                        'name'        => $grid->name,
                        'label'       => $grid->label,
                        'mysql_table' => $grid->mysql_table,
                        'nb_colonnes' => $nbColonnes,
                        'nb_lignes'   => $nbLignes,
                        'supprimee'   => $grid->deleted_at !== null,
                    ];
                }

                $rows[] = [
                    'org'    => $org,
                    'grids'  => $gridRows,
                    'error'  => false,
                ];
            } catch (\Throwable) {
                $rows[] = [
                    'org'   => $org,
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
        $grid    = DatagridTable::findOrFail((int) $table);
        $columns = $grid->columns()->orderBy('sort_order')->get();

        return view('super-admin.datagrids.edit', [
            'org'     => $organization,
            'table'   => $grid,
            'columns' => $columns,
        ]);
    }

    public function update(Organization $organization, string $table): JsonResponse
    {
        app(TenantManager::class)->connectTo($organization);
        $grid = DatagridTable::findOrFail((int) $table);

        $data = request()->validate([
            'label'       => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'has_rgpd'    => 'boolean',
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
        $grid   = DatagridTable::findOrFail((int) $table);
        $col    = DatagridColumn::findOrFail((int) $column);

        DB::connection('tenant')->statement(
            "ALTER TABLE `{$grid->mysql_table}` DROP COLUMN `{$col->name}`"
        );
        $col->delete();

        return redirect()->route('super-admin.datagrids.edit', [$organization, $grid->id])
            ->with('success', "Colonne « {$col->name} » supprimée.");
    }
}
