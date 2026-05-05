<?php

namespace App\Http\Controllers\Datagrid;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DatagridTable;
use Illuminate\View\View;

class DatagridController extends Controller
{
    public function index(): View
    {
        $tables = DatagridTable::withCount('columns')
            ->orderBy('label')
            ->get();

        return view('datagrid.index', compact('tables'));
    }
}
