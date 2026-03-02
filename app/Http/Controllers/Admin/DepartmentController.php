<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Department;
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

        return view('admin.departments.index', compact('directions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'type'      => ['required', 'in:direction,service'],
            'parent_id' => ['nullable', 'exists:tenant.departments,id'],
        ]);

        if ($request->type === 'service' && ! $request->parent_id) {
            return back()->withErrors(['parent_id' => 'Un service doit appartenir à une direction.']);
        }

        $dept = Department::on('tenant')->create([
            'name'       => $request->name,
            'type'       => $request->type,
            'parent_id'  => $request->type === 'service' ? $request->parent_id : null,
            'created_by' => auth()->id(),
        ]);

        $this->audit->log('department.created', auth()->user(), [
            'department_id' => $dept->id,
            'name'          => $dept->name,
            'type'          => $dept->type,
        ]);

        return back()->with('success', ucfirst($dept->type).' « '.$dept->name.' » créé(e).');
    }

    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:tenant.departments,id'],
        ]);

        $old = $department->only(['name', 'parent_id']);

        $department->update([
            'name'      => $request->name,
            'parent_id' => $department->isService() ? $request->parent_id : null,
        ]);

        $this->audit->log('department.updated', auth()->user(), [
            'department_id' => $department->id,
            'old'           => $old,
            'new'           => $department->only(['name', 'parent_id']),
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
                'delete' => 'Impossible de supprimer cette direction : elle contient des services. Supprimez-les d\'abord.',
            ]);
        }

        $this->audit->log('department.deleted', auth()->user(), [
            'department_id' => $department->id,
            'name'          => $department->name,
            'type'          => $department->type,
        ]);

        $department->delete();

        return back()->with('success', ucfirst($department->type).' supprimé(e).');
    }
}
