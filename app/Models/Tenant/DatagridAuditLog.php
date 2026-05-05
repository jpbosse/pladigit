<?php

namespace App\Models\Tenant;

use App\Enums\DatagridAuditAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'audit RGPD — immuable, jamais effaçable (pas de SoftDeletes).
 *
 * Toute action sur une grille has_rgpd=true est enregistrée ici.
 * Les enregistrements ne doivent jamais être modifiés ou supprimés.
 *
 * @property int $id
 * @property int $datagrid_table_id
 * @property int $user_id
 * @property DatagridAuditAction $action
 * @property int|null $row_id
 * @property string|null $column_name
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string $ip_address
 */
class DatagridAuditLog extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'datagrid_audit_log';

    /**
     * Pas de mise à jour — toutes les colonnes sont remplies à la création.
     */
    public $timestamps = true;

    protected $fillable = [
        'datagrid_table_id',
        'user_id',
        'action',
        'row_id',
        'column_name',
        'old_value',
        'new_value',
        'ip_address',
    ];

    protected $casts = [
        'action' => DatagridAuditAction::class,
        'row_id' => 'int',
    ];

    // ── Surcharges pour rendre le modèle immuable ─────────────

    public function update(array $attributes = [], array $options = []): bool
    {
        return false;
    }

    public function delete(): ?bool
    {
        return false;
    }

    public function forceDelete(): ?bool
    {
        return false;
    }

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

    public function scopeForRow($query, int $rowId)
    {
        return $query->where('row_id', $rowId);
    }

    public function scopeOfAction($query, DatagridAuditAction $action)
    {
        return $query->where('action', $action->value);
    }
}
