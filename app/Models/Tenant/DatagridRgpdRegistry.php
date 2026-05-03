<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registre des traitements RGPD liés aux grilles DataGrid.
 *
 * Chaque import ou traitement de masse est consigné ici.
 * file_hash : empreinte SHA-256 du fichier importé (traçabilité source).
 *
 * @property int $id
 * @property int $datagrid_table_id
 * @property int $user_id
 * @property string $event_type
 * @property string|null $source
 * @property string|null $legal_basis
 * @property int|null $record_count
 * @property array|null $sensitive_columns
 * @property string|null $operator_decision
 * @property string|null $file_hash
 */
class DatagridRgpdRegistry extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'datagrid_table_id',
        'user_id',
        'event_type',
        'source',
        'legal_basis',
        'record_count',
        'sensitive_columns',
        'operator_decision',
        'file_hash',
    ];

    protected $casts = [
        'sensitive_columns' => 'array',
        'record_count' => 'int',
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return BelongsTo<DatagridTable, $this> */
    public function datagridTable(): BelongsTo
    {
        return $this->belongsTo(DatagridTable::class, 'datagrid_table_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForTable($query, int $tableId)
    {
        return $query->where('datagrid_table_id', $tableId);
    }

    public function scopeOfEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}
