<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Droit individuel par utilisateur — prioritaire sur toutes les règles de groupe.
 *
 * column_name NULL → droit sur toute la table.
 * denied = true    → bloque définitivement, même si une règle de groupe accorde l'accès.
 *
 * @property int $id
 * @property int $datagrid_table_id
 * @property string|null $column_name
 * @property int $user_id
 * @property bool $can_read
 * @property bool $can_write
 * @property bool $can_delete
 * @property bool $can_export
 * @property bool $denied
 */
class DatagridUserPermission extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'datagrid_table_id',
        'column_name',
        'user_id',
        'can_read',
        'can_write',
        'can_delete',
        'can_export',
        'denied',
    ];

    protected $casts = [
        'can_read' => 'bool',
        'can_write' => 'bool',
        'can_delete' => 'bool',
        'can_export' => 'bool',
        'denied' => 'bool',
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

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForColumn($query, ?string $columnName)
    {
        return $columnName === null
            ? $query->whereNull('column_name')
            : $query->where('column_name', $columnName);
    }
}
