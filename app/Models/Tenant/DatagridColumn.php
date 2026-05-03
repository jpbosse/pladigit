<?php

namespace App\Models\Tenant;

use App\Enums\DatagridColumnType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Colonne d'une grille DataGrid.
 *
 * is_rgpd_sensitive : lecture et export de cette colonne seront tracés dans datagrid_audit_log.
 * is_role_column    : à l'import Excel, colonne transposée en lignes roles_titres
 *                     (ne jamais stocker en colonne directe sur personnes).
 * options           : JSON des valeurs possibles pour type=select.
 *
 * @property int $id
 * @property int $datagrid_table_id
 * @property string $name
 * @property string $label
 * @property DatagridColumnType $type
 * @property int|null $length
 * @property bool $required
 * @property bool $visible_by_default
 * @property bool $is_rgpd_sensitive
 * @property bool $is_role_column
 * @property string|null $default_value
 * @property array|null $options
 * @property int $sort_order
 */
class DatagridColumn extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'datagrid_table_id',
        'name',
        'label',
        'type',
        'length',
        'required',
        'visible_by_default',
        'is_rgpd_sensitive',
        'is_role_column',
        'default_value',
        'options',
        'sort_order',
    ];

    protected $casts = [
        'type' => DatagridColumnType::class,
        'length' => 'int',
        'required' => 'bool',
        'visible_by_default' => 'bool',
        'is_rgpd_sensitive' => 'bool',
        'is_role_column' => 'bool',
        'options' => 'array',
        'sort_order' => 'int',
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return BelongsTo<DatagridTable, $this> */
    public function datagridTable(): BelongsTo
    {
        return $this->belongsTo(DatagridTable::class, 'datagrid_table_id');
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeVisible($query)
    {
        return $query->where('visible_by_default', true);
    }

    public function scopeRgpd($query)
    {
        return $query->where('is_rgpd_sensitive', true);
    }

    public function scopeRoleColumns($query)
    {
        return $query->where('is_role_column', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('required', true);
    }
}
