<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DatagridTable;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DatagridPdfController extends Controller
{
    public function fiche(Request $request, DatagridTable $table, int $rowId): Response
    {
        ini_set('memory_limit', '512M');
        $visibleColumns = $request->query('cols') ? explode(',', $request->query('cols')) : [];
        $columns = $visibleColumns
            ? $table->columns()->whereIn('id', $visibleColumns)->get()
            : $table->columns()->get();

        $row = collect((array) DB::connection('tenant')
            ->table($table->mysql_table)
            ->where('id', $rowId)
            ->first())
            ->map(fn ($v) => is_string($v) ? mb_convert_encoding($v, 'UTF-8', 'auto') : $v)
            ->toArray();

        $pdf = Pdf::loadView('pdf.datagrid-fiche', [
            'table' => $table,
            'columns' => $columns,
            'row' => $row,
        ])->setOption(['defaultFont' => 'serif']);

        return $pdf->download($table->label.'-fiche-'.$rowId.'.pdf');
    }

    public function liste(Request $request, DatagridTable $table): Response
    {
        ini_set('memory_limit', '512M');
        $visibleColumns = $request->query('cols') ? explode(',', $request->query('cols')) : [];
        $columns = $visibleColumns
            ? $table->columns()->whereIn('id', $visibleColumns)->get()
            : $table->columns()->get();

        $filtersRaw = $request->query('filters', '[]');
        $filters = is_array(json_decode($filtersRaw, true)) ? array_filter((array) json_decode($filtersRaw, true)) : [];

        $query = DB::connection('tenant')->table($table->mysql_table);
        foreach ($filters as $col => $val) {
            if ($val !== '' && $val !== null) {
                $query->where($col, 'like', '%'.$val.'%');
            }
        }

        $rows = $query->limit(100)->get()->map(fn ($row) => (object) collect((array) $row)
            ->map(fn ($v) => is_string($v) ? mb_convert_encoding($v, 'UTF-8', 'auto') : $v)
            ->toArray()
        );

        $filtres = collect($filters)
            ->filter(fn ($v) => filled($v))
            ->map(fn ($v, $k) => $k.' : '.$v)
            ->implode(', ');

        $pdf = Pdf::loadView('pdf.datagrid-liste', [
            'table' => $table,
            'columns' => $columns,
            'rows' => $rows,
            'total' => $rows->count(),
            'filtres' => $filtres,
        ])->setPaper('a4', 'landscape')->setOption(['defaultFont' => 'serif']);

        return $pdf->download($table->label.'-liste.pdf');
    }
}
