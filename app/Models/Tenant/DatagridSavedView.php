<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Filtre sauvegardé sur une grille DataGrid.
 *
 * user_id NULL + department_id SET → vue partagée par le département.
 * user_id SET + department_id NULL → vue personnelle.
 * is_default = true                → activée automatiquement à l'ouverture.
 *
 * @property int $id
 * @property int $datagrid_table_id
 * @property int|null $user_id
 * @property int|null $department_id
 * @property string $name
 * @property array $filters
 * @property array|null $visible_columns
 * @property string|null $sort_column
 * @property string|null $sort_direction
 * @property bool $is_default
 */
class DatagridSavedView extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'datagrid_table_id',
        'user_id',
        'department_id',
        'name',
        'filters',
        'visible_columns',
        'sort_column',
        'sort_direction',
        'is_default',
    ];

    protected $casts = [
        'filters' => 'array',
        'visible_columns' => 'array',
        'is_default' => 'bool',
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

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {

        return $this->belongsTo(Department::class, 'department_id');
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

    public function scopeForDepartment($query, int $deptId)
    {

        return $query->where('department_id', $deptId);
    }

    public function scopeDefault($query)
    {

        return $query->where('is_default', true);
    }
}
