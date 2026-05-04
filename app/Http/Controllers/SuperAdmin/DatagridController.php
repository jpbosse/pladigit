<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Services\TenantManager;
use Illuminate\Support\Facades\DB;

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
}
