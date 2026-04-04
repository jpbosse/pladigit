<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Services\AuditService;
use App\Services\Ged\GedPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Interface de gouvernance GED (Admin/Président/DGS uniquement).
 *
 * Permet :
 *   - Transférer la propriété des ressources GED d'un utilisateur vers un autre
 *   - Lister les ressources orphelines (créateur supprimé)
 *   - Consulter un audit rapide des accès
 */
class AdminGedController extends Controller
{
    public function __construct(
        private readonly GedPermissionService $permissions,
        private readonly AuditService $audit,
    ) {}

    /**
     * Page principale de gouvernance GED.
     */
    public function index(): View
    {
        $this->authorizeAdmin();

        $users = User::orderBy('name')->get(['id', 'name', 'email', 'role']);
        $orphanFolders = $this->permissions->orphanedFolders();
        $orphanDocs = $this->permissions->orphanedDocuments();

        return view('admin.ged.governance', compact('users', 'orphanFolders', 'orphanDocs'));
    }

    /**
     * Transfère toutes les ressources GED d'un utilisateur vers un autre.
     * Utilisé lors d'un départ (démission, mutation).
     */
    public function transferOwnership(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'from_user_id' => ['required', 'integer', 'exists:tenant.users,id'],
            'to_user_id' => ['required', 'integer', 'exists:tenant.users,id', 'different:from_user_id'],
        ]);

        $from = User::findOrFail((int) $validated['from_user_id']);
        $to = User::findOrFail((int) $validated['to_user_id']);

        $count = $this->permissions->transferOwnership($from, $to);

        /** @var User $admin */
        $admin = auth()->user();
        $this->audit->log('ged.ownership.transferred', $admin, [
            'new' => [
                'from_user_id' => $from->id,
                'from_user_name' => $from->name,
                'to_user_id' => $to->id,
                'to_user_name' => $to->name,
                'resources' => $count,
            ],
        ]);

        return back()->with('success', "{$count} ressource(s) GED transférée(s) de « {$from->name} » vers « {$to->name} ».");
    }

    // ── Helpers privés ───────────────────────────────────────

    private function authorizeAdmin(): void
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->role || ! UserRole::from($user->role)->atLeast(UserRole::DGS)) {
            abort(403, 'Accès réservé aux administrateurs GED.');
        }
    }
}
