<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Department;
use App\Models\Tenant\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Gestion des directions et services par l'Admin Organisation.
 */
class DepartmentController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index()
    {
        $directions = Department::on('tenant')
            ->directions()
            ->with(['children.members', 'managers'])
            ->withCount('members')
            ->orderBy('name')
            ->get();

        $departmentsJson = $directions->map(function (Department $d): array {
            return [
                'id' => $d->id,
                'name' => $d->name,
                'type' => 'direction',
                'members' => $d->members->map(function (User $m): array {
                    return ['id' => $m->id, 'name' => $m->name, 'is_manager' => (bool) ($m->pivot->is_manager ?? false)];
                })->values(),
                'children' => $d->children->map(function (Department $s): array {
                    return [
                        'id' => $s->id,
                        'name' => $s->name,
                        'type' => 'service',
                        'members' => $s->members->map(function (User $m): array {
                            return ['id' => $m->id, 'name' => $m->name, 'is_manager' => (bool) ($m->pivot->is_manager ?? false)];
                        })->values(),
                    ];
                })->values(),
            ];
        })->values();

        return view('admin.departments.index', compact('directions', 'departmentsJson'));
    }


    public function organigramme()
    {
        // Charge toutes les directions avec leurs enfants (services ET sous-directions)
        $all = Department::on('tenant')
            ->directions()
            ->with([
                'children.members',
                'children.managers',
                'children.children.members',
                'children.children.managers',
                'members',
                'managers',
            ])
            ->withCount('members')
            ->orderBy('name')
            ->get();

        // Détecte la DGS
        $dgs = $all->first(fn($d) =>
            str_contains(strtolower($d->name), 'dgs') ||
            str_contains(strtolower($d->name), 'direction générale')
        );

        // Directions racines (sans parent) sauf la DGS
        $directions = $all->filter(fn($d) =>
            is_null($d->parent_id) && (!$dgs || $d->id !== $dgs->id)
        )->values();

        // Sous-directions rattachées à la DGS (via parent_id)
        $subDirections = $dgs
            ? $all->filter(fn($d) => $d->parent_id === $dgs->id)->values()
            : collect();

        return view('admin.departments.organigramme', compact('directions', 'dgs', 'subDirections'));
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:direction,service'],
        ]);

        if ($request->type === 'service') {
            if (! $request->parent_id) {
                return back()->withErrors(['parent_id' => 'Un service doit appartenir à une direction.']);
            }
        }

        if ($request->parent_id) {
            $parentExists = Department::on('tenant')->where('id', $request->parent_id)->exists();
            if (! $parentExists) {
                return back()->withErrors(['parent_id' => 'La direction parente sélectionnée est invalide.']);
            }
        }

        $dept = Department::on('tenant')->create([
            'name' => $request->name,
            'type' => $request->type,
            'parent_id' => $request->parent_id ?: null,
            'created_by' => auth()->id(),
        ]);

        $this->audit->log('department.created', auth()->user(), [
            'department_id' => $dept->id,
            'name' => $dept->name,
            'type' => $dept->type,
        ]);

        return back()->with('success', ucfirst($dept->type).' « '.$dept->name.' » créé(e).');
    }

    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($department->isService() && $request->parent_id) {
            $parentExists = Department::on('tenant')->where('id', $request->parent_id)->exists();
            if (! $parentExists) {
                return back()->withErrors(['parent_id' => 'La direction parente sélectionnée est invalide.']);
            }
        }

        $old = $department->only(['name', 'parent_id']);

        $department->update([
            'name' => $request->name,
            'parent_id' => $department->isService() ? $request->parent_id : null,
        ]);

        $this->audit->log('department.updated', auth()->user(), [
            'department_id' => $department->id,
            'old' => $old,
            'new' => $department->only(['name', 'parent_id']),
        ]);

        return back()->with('success', 'Nom mis à jour.');
    }

    public function destroy(Department $department)
    {
        if ($department->members()->count() > 0) {
            return back()->withErrors([
                'delete' => 'Impossible de supprimer « '.$department->name.' » : '
                    .$department->members()->count().' membre(s) y sont affectés.',
            ]);
        }

        if ($department->isDirection() && $department->children()->count() > 0) {
            return back()->withErrors([
                'delete' => "Impossible de supprimer cette direction : elle contient des services. Supprimez-les d'abord.",
            ]);
        }

        $this->audit->log('department.deleted', auth()->user(), [
            'department_id' => $department->id,
            'name' => $department->name,
            'type' => $department->type,
        ]);

        $department->delete();

        return back()->with('success', ucfirst($department->type).' supprimé(e).');
    }
}
