<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\DatagridColumnType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\DatagridAuditLog;
use App\Models\Tenant\DatagridColumn;
use App\Models\Tenant\DatagridPermission;
use App\Models\Tenant\DatagridSavedView;
use App\Models\Tenant\DatagridTable;
use App\Models\Tenant\DatagridUserPermission;
use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use App\Services\DatagridPermissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DatagridAdminController extends Controller
{
    public function index(): View
    {
        $tables = DatagridTable::withCount('columns')->orderBy('label')->get();

        return view('admin.datagrid.index', compact('tables'));
    }

    public function edit(DatagridTable $table): View
    {
        $columns = $table->columns()->orderBy('sort_order')->get();

        $perms = app(DatagridPermissionService::class)->permissionsFor($table);
        $departments = Department::orderBy('name')->get();
        $users = User::where('status', 'active')->orderBy('name')->get();
        $roles = UserRole::cases();

        return view('admin.datagrid.edit', compact(
            'table', 'columns', 'perms', 'departments', 'users', 'roles'
        ));
    }

    public function permissions(DatagridTable $table): View
    {
        $perms = app(DatagridPermissionService::class)->permissionsFor($table);
        $colPerms = app(DatagridPermissionService::class)->columnPermissionsFor($table);
        $departments = Department::orderBy('name')->get();
        $users = User::where('status', 'active')->orderBy('name')->get();
        $roles = UserRole::cases();
        $columns = $table->columns()->orderBy('sort_order')->get();

        return view('admin.datagrid.permissions', compact(
            'table', 'perms', 'colPerms', 'departments', 'users', 'roles', 'columns'
        ));
    }

    public function update(DatagridTable $table): JsonResponse
    {
        $data = request()->validate([
            'label' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'has_rgpd' => 'boolean',
        ]);

        $table->update($data);

        return response()->json(['success' => true]);
    }

    /**
     * Paramètres de présentation de la grille (2.16 + 2.17).
     *
     * Met à jour le tri par défaut et l'option numéro de ligne.
     * Appelé depuis admin/datagrid/edit.blade.php via fetch PATCH.
     */
    public function updateSettings(DatagridTable $table): JsonResponse
    {
        $columns = $table->columns->pluck('name')->toArray();

        $data = request()->validate([
            'default_sort_column' => ['nullable', 'string', 'max:100', 'in:'.implode(',', array_merge([''], $columns))],
            'default_sort_direction' => 'required|in:asc,desc',
            'show_row_number' => 'boolean',
        ]);

        // Normaliser : chaîne vide → NULL
        if (($data['default_sort_column'] ?? '') === '') {
            $data['default_sort_column'] = null;
        }

        $table->update([
            'default_sort_column' => $data['default_sort_column'],
            'default_sort_direction' => $data['default_sort_direction'],
            'show_row_number' => (bool) ($data['show_row_number'] ?? false),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Réordonnancement des colonnes par l'admin tenant (2.15).
     *
     * Reçoit un tableau ordonné d'IDs de colonnes et met à jour sort_order.
     * Aucun DDL — uniquement des UPDATE sur datagrid_columns.
     *
     * Body JSON attendu : { "order": [3, 1, 5, 2, 4] }
     */
    public function reorderColumns(DatagridTable $table): JsonResponse
    {
        $data = request()->validate([
            'order' => 'required|array',
            'order.*' => 'integer|min:1',
        ]);

        $columnIds = $table->columns->pluck('id')->toArray();

        foreach ($data['order'] as $position => $columnId) {
            if (! in_array((int) $columnId, $columnIds, true)) {
                return response()->json(['error' => "Colonne {$columnId} inconnue."], 422);
            }
            DatagridColumn::where('id', $columnId)->update(['sort_order' => $position]);
        }

        app(DatagridPermissionService::class)->invalidateCacheForTable($table);

        return response()->json(['success' => true]);
    }

    // ── Droits par rôle ───────────────────────────────────────────────────────

    public function storeRolePermission(DatagridTable $table): RedirectResponse
    {
        $data = request()->validate([
            'role' => 'required|in:'.implode(',', array_column(UserRole::cases(), 'value')),
            'can_read' => 'boolean',
            'can_write' => 'boolean',
            'can_delete' => 'boolean',
            'can_export' => 'boolean',
            'denied' => 'boolean',
        ]);

        app(DatagridPermissionService::class)->setRolePermission(
            $table,
            $data['role'],
            (bool) ($data['can_read'] ?? false),
            (bool) ($data['can_write'] ?? false),
            (bool) ($data['can_delete'] ?? false),
            (bool) ($data['can_export'] ?? false),
            (bool) ($data['denied'] ?? false),
        );

        return redirect()->route('admin.datagrid.permissions', $table)
            ->with('success', 'Règle par rôle enregistrée.');
    }

    public function destroyRolePermission(DatagridTable $table, DatagridPermission $permission): RedirectResponse
    {
        abort_unless($permission->datagrid_table_id === $table->id, 404);
        abort_unless($permission->subject_type === 'role', 404);

        $permission->delete();
        app(DatagridPermissionService::class)->invalidateCacheForTable($table);

        return redirect()->route('admin.datagrid.permissions', $table)
            ->with('success', 'Règle supprimée.');
    }

    // ── Droits par département ────────────────────────────────────────────────

    public function storeDeptPermission(DatagridTable $table): RedirectResponse
    {
        $data = request()->validate([
            'department_id' => 'required|integer|exists:departments,id',
            'can_read' => 'boolean',
            'can_write' => 'boolean',
            'can_delete' => 'boolean',
            'can_export' => 'boolean',
            'denied' => 'boolean',
        ]);

        $dept = Department::findOrFail($data['department_id']);

        app(DatagridPermissionService::class)->setDepartmentPermission(
            $table,
            $dept,
            (bool) ($data['can_read'] ?? false),
            (bool) ($data['can_write'] ?? false),
            (bool) ($data['can_delete'] ?? false),
            (bool) ($data['can_export'] ?? false),
            (bool) ($data['denied'] ?? false),
        );

        return redirect()->route('admin.datagrid.permissions', $table)
            ->with('success', 'Règle par département enregistrée.');
    }

    public function destroyDeptPermission(DatagridTable $table, DatagridPermission $permission): RedirectResponse
    {
        abort_unless($permission->datagrid_table_id === $table->id, 404);
        abort_unless($permission->subject_type === 'department', 404);

        $permission->delete();
        app(DatagridPermissionService::class)->invalidateCacheForTable($table);

        return redirect()->route('admin.datagrid.permissions', $table)
            ->with('success', 'Règle supprimée.');
    }

    // ── Droits par utilisateur ────────────────────────────────────────────────

    public function storeUserPermission(DatagridTable $table): RedirectResponse
    {
        $data = request()->validate([
            'user_id' => 'required|integer|exists:users,id',
            'can_read' => 'boolean',
            'can_write' => 'boolean',
            'can_delete' => 'boolean',
            'can_export' => 'boolean',
            'denied' => 'boolean',
        ]);

        $user = User::findOrFail($data['user_id']);

        app(DatagridPermissionService::class)->setUserPermission(
            $table,
            $user,
            (bool) ($data['can_read'] ?? false),
            (bool) ($data['can_write'] ?? false),
            (bool) ($data['can_delete'] ?? false),
            (bool) ($data['can_export'] ?? false),
            (bool) ($data['denied'] ?? false),
        );

        return redirect()->route('admin.datagrid.permissions', $table)
            ->with('success', 'Règle individuelle enregistrée.');
    }

    public function destroyUserPermission(DatagridTable $table, DatagridUserPermission $permission): RedirectResponse
    {
        abort_unless($permission->datagrid_table_id === $table->id, 404);

        $user = User::find($permission->user_id);
        $permission->delete();

        if ($user) {
            app(DatagridPermissionService::class)->invalidateCacheForUser($user, $table);
        }

        return redirect()->route('admin.datagrid.permissions', $table)
            ->with('success', 'Règle supprimée.');
    }

    // ── Droits par colonne ───────────────────────────────────────────────────────

    public function storeColumnPermission(DatagridTable $table): RedirectResponse
    {
        $data = request()->validate([
            'column_name' => 'required|string|max:64',
            'subject_type' => 'required|in:role,department,user',
            'role' => 'required_if:subject_type,role|nullable|in:'.implode(',', array_column(UserRole::cases(), 'value')),
            'department_id' => 'required_if:subject_type,department|nullable|integer|exists:departments,id',
            'user_id' => 'required_if:subject_type,user|nullable|integer|exists:users,id',
            'can_read' => 'boolean',
            'denied' => 'boolean',
        ]);

        $svc = app(DatagridPermissionService::class);

        match ((string) $data['subject_type']) {
            'role' => $svc->setRolePermission(
                $table, (string) $data['role'],
                (bool) ($data['can_read'] ?? false), false, false, false,
                (bool) ($data['denied'] ?? false),
                (string) $data['column_name']
            ),
            'department' => $svc->setDepartmentPermission(
                $table, Department::findOrFail($data['department_id']),
                (bool) ($data['can_read'] ?? false), false, false, false,
                (bool) ($data['denied'] ?? false),
                (string) $data['column_name']
            ),
            'user' => $svc->setUserPermission(
                $table, User::findOrFail($data['user_id']),
                (bool) ($data['can_read'] ?? false), false, false, false,
                (bool) ($data['denied'] ?? false),
                (string) $data['column_name']
            ),
            default => null,
        };

        return redirect()->route('admin.datagrid.permissions', $table)
            ->with('success', 'Droit colonne enregistré.');
    }

    public function destroyColumnPermission(DatagridTable $table, string $type, int $permId): RedirectResponse
    {
        $svc = app(DatagridPermissionService::class);

        if ($type === 'user') {
            $perm = DatagridUserPermission::findOrFail($permId);
            abort_unless($perm->datagrid_table_id === $table->id, 404);
            abort_unless($perm->column_name !== null, 404);
            $user = User::find($perm->user_id);
            $perm->delete();
            if ($user) {
                $svc->invalidateCacheForUser($user, $table);
            }
        } else {
            $perm = DatagridPermission::findOrFail($permId);
            abort_unless($perm->datagrid_table_id === $table->id, 404);
            abort_unless($perm->column_name !== null, 404);
            $perm->delete();
            $svc->invalidateCacheForTable($table);
        }

        return redirect()->route('admin.datagrid.permissions', $table)
            ->with('success', 'Droit colonne supprimé.');
    }

    // ── Colonnes ──────────────────────────────────────────────────────────────

    public function updateColumn(DatagridTable $table, DatagridColumn $column): JsonResponse
    {
        $data = request()->validate([
            'name' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'label' => 'required|string|max:100',
            'visible_by_default' => 'boolean',
            'required' => 'boolean',
            'is_rgpd_sensitive' => 'boolean',
            'sort_order' => 'integer|min:0',
            'type' => 'nullable|in:'.implode(',', DatagridColumnType::values()),
            'length' => 'nullable|integer|min:1|max:65535',
            'label_true' => 'nullable|string|max:50',
            'label_false' => 'nullable|string|max:50',
            'options' => 'nullable|array',
            'options.*' => 'string|max:255',
            'tab' => 'nullable|in:main,extra',
            'fuzzy_search' => 'boolean',
        ]);

        $oldName = $column->name;
        $oldType = $column->type;
        $newType = isset($data['type']) ? DatagridColumnType::from($data['type']) : $oldType;
        $oldLength = $column->length;
        $newLength = $data['length'] ?? $oldLength;

        $column->fill([
            'label' => $data['label'],
            'visible_by_default' => $data['visible_by_default'] ?? $column->visible_by_default,
            'required' => $data['required'] ?? $column->required,
            'is_rgpd_sensitive' => $data['is_rgpd_sensitive'] ?? $column->is_rgpd_sensitive,
            'sort_order' => $data['sort_order'] ?? $column->sort_order,
            'type' => $newType,
            'length' => $newLength,
            'tab' => $data['tab'] ?? $column->tab ?? 'main',
            'fuzzy_search' => (bool) ($data['fuzzy_search'] ?? false),
        ]);
        $column->save();

        $newName = $data['name'] ?? '';
        if ($newName !== '' && $newName !== $oldName) {
            if ($table->columns()->where('name', $newName)->where('id', '!=', $column->id)->exists()) {
                return response()->json(['errors' => ['name' => ['Ce nom technique est déjà utilisé.']]], 422);
            }
            Schema::connection('tenant')->table($table->mysql_table, function (Blueprint $t) use ($oldName, $newName) {
                $t->renameColumn($oldName, $newName);
            });
            $column->name = $newName;
            $column->saveQuietly();
        }

        if ($newType !== $oldType) {
            if (! $this->typesCompatible($oldType, $newType)) {
                // Conversions depuis TEXT autorisées avec avertissement (force=true requis)
                $forceAllowed = $this->typesForceable($oldType, $newType);
                $force = filter_var($data['force'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if (! $forceAllowed || ! $force) {
                    return response()->json([
                        'error' => 'Type incompatible avec les données existantes',
                        'forceable' => $forceAllowed,
                        'warning' => $forceAllowed
                            ? 'Cette conversion peut corrompre des valeurs non conformes au format '.$newType->label().'. Confirmez-vous la modification ?'
                            : null,
                    ], 422);
                }
            }

            Schema::connection('tenant')->table($table->mysql_table, function (Blueprint $t) use ($column, $newType, $newLength) {
                $this->applySchemaChange($t, $column->name, $newType, $newLength, ! $column->required);
            });
        } elseif ($newType->hasLength() && $newLength !== $oldLength && $newLength !== null) {
            $colName = $column->name;
            $nullable = $column->required ? '' : ' NULL';
            DB::connection('tenant')->statement(
                "ALTER TABLE `{$table->mysql_table}` MODIFY COLUMN `{$colName}` VARCHAR({$newLength}){$nullable}"
            );
        }

        app(DatagridPermissionService::class)->invalidateCacheForTable($table);

        return response()->json(['success' => true]);
    }

    public function destroyColumn(DatagridTable $table, DatagridColumn $column): RedirectResponse
    {
        DB::connection('tenant')->statement(
            "ALTER TABLE `{$table->mysql_table}` DROP COLUMN `{$column->name}`"
        );

        $column->delete();

        app(DatagridPermissionService::class)->invalidateCacheForTable($table);

        return redirect()->route('admin.datagrid.edit', $table)
            ->with('success', 'Colonne supprimée.');
    }

    public function editColumn(DatagridTable $table, DatagridColumn $column): View
    {
        return view('admin.datagrid.edit-column', compact('table', 'column'));
    }

    public function destroy(DatagridTable $table): RedirectResponse
    {
        DatagridAuditLog::where('datagrid_table_id', $table->id)->delete();
        DatagridSavedView::where('datagrid_table_id', $table->id)->delete();
        DatagridColumn::where('datagrid_table_id', $table->id)->delete();

        Schema::connection('tenant')->dropIfExists($table->mysql_table);

        $table->forceDelete();

        return redirect()->route('admin.datagrid.index')
            ->with('success', "Grille « {$table->label} » supprimée.");
    }

    // ── Helpers privés ───────────────────────────────────────────────────────

    private function typesCompatible(DatagridColumnType $from, DatagridColumnType $to): bool
    {
        $families = [
            [DatagridColumnType::TEXT, DatagridColumnType::EMAIL, DatagridColumnType::PHONE, DatagridColumnType::SELECT, DatagridColumnType::SIRET, DatagridColumnType::POSTAL_CODE],
            [DatagridColumnType::NUMBER],
            [DatagridColumnType::DATE],
            [DatagridColumnType::BOOLEAN],
        ];

        foreach ($families as $family) {
            if (in_array($from, $family, true) && in_array($to, $family, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Conversions autorisées avec avertissement (depuis TEXT vers un type structuré).
     * L'admin doit confirmer explicitement (force=true).
     */
    private function typesForceable(DatagridColumnType $from, DatagridColumnType $to): bool
    {
        if ($from !== DatagridColumnType::TEXT) {
            return false;
        }

        return in_array($to, [
            DatagridColumnType::DATE,
            DatagridColumnType::NUMBER,
            DatagridColumnType::BOOLEAN,
            DatagridColumnType::EMAIL,
            DatagridColumnType::PHONE,
            DatagridColumnType::POSTAL_CODE,
            DatagridColumnType::SIRET,
            DatagridColumnType::SELECT,
        ], true);
    }

    private function applySchemaChange(Blueprint $t, string $colName, DatagridColumnType $type, ?int $length, bool $nullable): void
    {
        $col = match ($type) {
            DatagridColumnType::NUMBER => $t->decimal($colName, 15, 4),
            DatagridColumnType::DATE => $t->date($colName),
            DatagridColumnType::BOOLEAN => $t->boolean($colName)->default(false),
            DatagridColumnType::EMAIL => $t->string($colName, $length ?? 255),
            DatagridColumnType::PHONE => $t->string($colName, $length ?? 30),
            DatagridColumnType::SIRET => $t->string($colName, 14),
            DatagridColumnType::POSTAL_CODE => $t->string($colName, 10),
            DatagridColumnType::SELECT => $t->string($colName, 100),
            default => $t->string($colName, $length ?? 255),
        };

        if ($nullable) {
            $col->nullable()->change();
        } else {
            $col->change();
        }
    }
}
