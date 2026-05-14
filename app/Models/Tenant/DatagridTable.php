<?php

namespace App\Models\Tenant;

use App\Services\DatagridPermissionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Définition d'une grille DataGrid — méta-table no-code.
 *
 * mysql_table            : table MySQL tenant sous-jacente (ex: "personnes").
 * is_persons_view        : grille liée à personnes+roles_titres ;
 *                          l'import Excel transposera les colonnes "rôles" en lignes roles_titres.
 * role_categories        : filtre JSON des RoleTitreCategorie visibles dans cette vue.
 * has_rgpd               : active l'audit trail complet et le registre des traitements.
 * default_sort_column    : nom MySQL de la colonne triée par défaut. NULL = ordre naturel.
 * default_sort_direction : sens du tri par défaut ('asc' ou 'desc').
 * show_row_number        : affiche une colonne # numérotant les lignes (utile registres).
 *
 * @property int $id
 * @property string $name
 * @property string $label
 * @property string|null $description
 * @property string $mysql_table
 * @property bool $has_rgpd
 * @property bool $is_persons_view
 * @property array|null $role_categories
 * @property int|null $created_by
 * @property string|null $default_sort_column
 * @property string $default_sort_direction
 * @property bool $show_row_number
 */
class DatagridTable extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'label',
        'description',
        'mysql_table',
        'has_rgpd',
        'is_persons_view',
        'role_categories',
        'created_by',
        'folder_id',
        'default_sort_column',
        'default_sort_direction',
        'show_row_number',
    ];

    protected $casts = [
        'has_rgpd' => 'bool',
        'is_persons_view' => 'bool',
        'role_categories' => 'array',
        'deleted_at' => 'datetime',
        'show_row_number' => 'bool',
    ];

    // ── Relations ────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<DatagridColumn, $this> */
    public function columns(): HasMany
    {
        return $this->hasMany(DatagridColumn::class, 'datagrid_table_id')->orderBy('sort_order');
    }

    /** @return HasMany<DatagridPermission, $this> */
    public function permissions(): HasMany
    {
        return $this->hasMany(DatagridPermission::class, 'datagrid_table_id');
    }

    /** @return HasMany<DatagridUserPermission, $this> */
    public function userPermissions(): HasMany
    {
        return $this->hasMany(DatagridUserPermission::class, 'datagrid_table_id');
    }

    /** @return HasMany<DatagridSavedView, $this> */
    public function savedViews(): HasMany
    {
        return $this->hasMany(DatagridSavedView::class, 'datagrid_table_id');
    }

    /** @return HasMany<DatagridAuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(DatagridAuditLog::class, 'datagrid_table_id');
    }

    /** @return HasMany<DatagridRgpdRegistry, $this> */
    public function rgpdRegistry(): HasMany
    {
        return $this->hasMany(DatagridRgpdRegistry::class, 'datagrid_table_id');
    }

    // ── Droits (délégués au service) ─────────────────────────

    public function canRead(User $user): bool
    {
        return app(DatagridPermissionService::class)->canRead($user, $this);
    }

    public function canWrite(User $user): bool
    {
        return app(DatagridPermissionService::class)->canWrite($user, $this);
    }

    public function canDelete(User $user): bool
    {
        return app(DatagridPermissionService::class)->canDelete($user, $this);
    }

    public function canExport(User $user): bool
    {
        return app(DatagridPermissionService::class)->canExport($user, $this);
    }

    /**
     * Résume les droits effectifs d'un utilisateur sous forme de tableau.
     *
     * @return array{can_read: bool, can_write: bool, can_delete: bool, can_export: bool}
     */
    public function permissionsFor(User $user): array
    {
        return app(DatagridPermissionService::class)->effectivePermissions($user, $this);
    }
}
