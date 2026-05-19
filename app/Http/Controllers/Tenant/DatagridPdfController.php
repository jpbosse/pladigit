<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DatagridTable;
use App\Models\Tenant\User;
use App\Services\DatagridPermissionService;
use App\Traits\LogsDatagridExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DatagridPdfController extends Controller
{
    use LogsDatagridExport;

    public function __construct(private DatagridPermissionService $permService) {}

    /**
     * PDF d'une fiche (une ligne).
     */
    public function fiche(DatagridTable $table, int $rowId): Response
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($this->permService->canExport($user, $table), 403);

        $columns = $table->columns()
            ->where('visible_by_default', true)
            ->orderBy('sort_order')
            ->get();

        $row = DB::connection('tenant')
            ->table($table->mysql_table)
            ->where('id', $rowId)
            ->first();

        abort_if($row === null, 404);

        $row = (array) $row;

        $this->logExport($table, 'pdf_fiche', $rowId);

        $pdf = Pdf::loadView('pdf.datagrid-fiche', compact('table', 'columns', 'row'));

        return $pdf->download("{$table->label}-fiche-{$rowId}.pdf");
    }

    /**
     * PDF de la liste complète (toutes les lignes visibles).
     */
    public function liste(DatagridTable $table): Response
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($this->permService->canExport($user, $table), 403);

        $columns = $table->columns()
            ->where('visible_by_default', true)
            ->orderBy('sort_order')
            ->get();

        $rows = DB::connection('tenant')
            ->table($table->mysql_table)
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        $this->logExport($table, 'pdf_liste');

        $pdf = Pdf::loadView('pdf.datagrid-liste', compact('table', 'columns', 'rows'))
            ->setPaper('a4', 'landscape');

        return $pdf->download("{$table->label}-liste.pdf");
    }
}
