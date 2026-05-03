<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Droit par rôle ou département sur une grille (ou une colonne spécifique).
 *
 * column_name NULL  → droit s'applique à toute la table.
 * subject_type      : 'role' | 'department'
 * denied = true     : exception explicite — prime sur tout héritage hiérarchique.
 *
 * @property int $id
 * @property int $datagrid_table_id
 * @property string|null $column_name
 * @property string $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_role
 * @property bool $can_read
 * @property bool $can_write
 * @property bool $can_delete
 * @property bool $can_export
 * @property bool $denied
 */
class DatagridPermission extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'datagrid_table_id',
        'column_name',
        'subject_type',
        'subject_id',
        'subject_role',
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

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'subject_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeForTable($query, int $tableId)
    {
        return $query->where('datagrid_table_id', $tableId);
    }

    public function scopeForColumn($query, ?string $columnName)
    {
        return $columnName === null
            ? $query->whereNull('column_name')
            : $query->where('column_name', $columnName);
    }

    public function scopeForRole($query, string $role)
    {
        return $query->where('subject_type', 'role')
            ->where('subject_role', $role);
    }

    public function scopeForDepartment($query, int $deptId)
    {
        return $query->where('subject_type', 'department')
            ->where('subject_id', $deptId);
    }
}
