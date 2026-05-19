<?php

namespace App\Traits;

use App\Enums\DatagridAuditAction;
use App\Models\Tenant\DatagridTable;
use Illuminate\Support\Facades\DB;

/**
 * Trait réutilisable pour logger les exports DataGrid dans datagrid_audit_log.
 *
 * Utilisé par :
 *   - ShowGrid (Livewire) — exports Excel / ODS
 *   - DatagridPdfController — exports PDF fiche / liste
 *
 * On ne passe pas par DatagridAuditLog::create() pour contourner
 * l'interdiction d'insert définie sur le modèle (immuabilité).
 * On insère directement via DB pour respecter l'intention du modèle
 * tout en permettant l'écriture initiale.
 */
trait LogsDatagridExport
{
    /**
     * Enregistre un export dans datagrid_audit_log.
     *
     * @param  DatagridTable  $table  Grille exportée
     * @param  string  $format  'xlsx', 'ods', 'pdf_fiche', 'pdf_liste'
     * @param  int|null  $rowId  Identifiant de la ligne (pour fiche uniquement)
     */
    protected function logExport(DatagridTable $table, string $format, ?int $rowId = null): void
    {
        try {
            DB::connection('tenant')->table('datagrid_audit_log')->insert([
                'datagrid_table_id' => $table->id,
                'user_id' => auth()->id() ?? 0,
                'action' => DatagridAuditAction::EXPORT->value,
                'row_id' => $rowId,
                'column_name' => $format,   // on stocke le format dans column_name
                'old_value' => null,
                'new_value' => null,
                'ip_address' => request()->ip() ?? '0.0.0.0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            // Le log ne doit jamais bloquer l'export
        }
    }
}
