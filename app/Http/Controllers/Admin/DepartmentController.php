<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Department;
use App\Services\AuditService;
use Illuminate\Http\Request;

/**
 * Gestion des entités organisationnelles (Directions, Services, Pôles, Bureaux…)
 * Structure libre : pas de contrainte direction|service — l'admin définit
 * librement le label, la hiérarchie et les options de chaque nœud.
 */
class DepartmentController extends Controller
{
    public function __construct(private AuditService $audit) {}

    // ─────────────────────────────────────────────────────────────
    //  INDEX
    // ─────────────────────────────────────────────────────────────

    public function index()
    {
        // Tous les nœuds racines (sans parent), triés par sort_order puis name
        $roots = Department::on('tenant')
            ->whereNull('parent_id')
            ->with(['allChildren.members', 'allChildren.managers', 'members', 'managers'])
            ->withCount('members')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Tous les départements (pour le sélecteur "Rattaché à")
        $allDepts = Department::on('tenant')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Statistiques pour les compteurs
        $stats = [
            'total' => Department::on('tenant')->count(),
            'roots' => $roots->count(),
            'members' => Department::on('tenant')->withCount('members')->get()->sum('members_count'),
        ];

        return view('admin.departments.index', compact('roots', 'allDepts', 'stats'));
    }

    // ─────────────────────────────────────────────────────────────
    //  ORGANIGRAMME
    // ─────────────────────────────────────────────────────────────

    public function organigramme()
    {
        $roots = Department::on('tenant')
            ->whereNull('parent_id')
            ->with([
                'allChildren.members',
                'allChildren.managers',
                'members',
                'managers',
            ])
            ->withCount('members')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.departments.organigramme', compact('roots'));
    }

    // ─────────────────────────────────────────────────────────────
    //  STORE
    // ─────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'parent_id' => ['nullable', 'integer'],
            'is_transversal' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Vérification souple : si parent_id fourni, il doit exister
        if (! empty($data['parent_id'])) {
            $parentExists = Department::on('tenant')->where('id', $data['parent_id'])->exists();
            if (! $parentExists) {
                return back()
                    ->withInput()
                    ->withErrors(['parent_id' => 'Le nœud parent sélectionné est invalide.']);
            }
        } else {
            $data['parent_id'] = null;
        }

        // Dérive le type legacy pour compatibilité (direction si racine, service sinon)
        $data['type'] = empty($data['parent_id']) ? 'direction' : 'service';
        $data['is_transversal'] = (bool) ($data['is_transversal'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['created_by'] = auth()->id();

        $dept = Department::on('tenant')->create($data);

        $this->audit->log('department.created', auth()->user(), [
            'department_id' => $dept->id,
            'name' => $dept->name,
            'label' => $dept->label,
            'parent_id' => $dept->parent_id,
        ]);

        $displayLabel = $dept->label ?: ($dept->parent_id ? 'Entité' : 'Entité racine');

        return back()->with('success', "{$displayLabel} « {$dept->name} » créé(e).");
    }

    // ─────────────────────────────────────────────────────────────
    //  UPDATE
    // ─────────────────────────────────────────────────────────────

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'parent_id' => ['nullable', 'integer'],
            'is_transversal' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Vérifie que le parent existe si fourni
        if (! empty($data['parent_id'])) {
            // Anti-boucle : on ne peut pas se rattacher à soi-même ou à un descendant
            if ((int) $data['parent_id'] === $department->id) {
                return back()->withErrors(['parent_id' => 'Un nœud ne peut pas être son propre parent.']);
            }
            $parentExists = Department::on('tenant')->where('id', $data['parent_id'])->exists();
            if (! $parentExists) {
                return back()->withErrors(['parent_id' => 'Le nœud parent sélectionné est invalide.']);
            }
        } else {
            $data['parent_id'] = null;
        }

        // Recalcule le type legacy
        $data['type'] = empty($data['parent_id']) ? 'direction' : 'service';
        $data['is_transversal'] = (bool) ($data['is_transversal'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        // Efface la couleur si envoyée vide
        if (empty($data['color'])) {
            $data['color'] = null;
        }

        $old = $department->only(['name', 'label', 'color', 'parent_id', 'is_transversal', 'sort_order']);

        $department->update($data);

        $this->audit->log('department.updated', auth()->user(), [
            'department_id' => $department->id,
            'old' => $old,
            'new' => $department->only(['name', 'label', 'color', 'parent_id', 'is_transversal', 'sort_order']),
        ]);

        return back()->with('success', '« '.$department->name.' » mis à jour.');
    }

    // ─────────────────────────────────────────────────────────────
    //  DESTROY
    // ─────────────────────────────────────────────────────────────

    public function destroy(Department $department)
    {
        $membersCount = $department->members()->count();
        if ($membersCount > 0) {
            return back()->withErrors([
                'delete' => "Impossible de supprimer « {$department->name} » : {$membersCount} membre(s) y sont affectés.",
            ]);
        }

        $childrenCount = $department->children()->count();
        if ($childrenCount > 0) {
            return back()->withErrors([
                'delete' => "Impossible de supprimer « {$department->name} » : elle contient {$childrenCount} entité(s) enfant(s). Supprimez-les d'abord.",
            ]);
        }

        $this->audit->log('department.deleted', auth()->user(), [
            'department_id' => $department->id,
            'name' => $department->name,
            'label' => $department->label,
        ]);

        $department->delete();

        return back()->with('success', '« '.$department->name.' » supprimé(e).');
    }
}
