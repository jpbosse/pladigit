<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use App\Services\Ged\GedPermissionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GedSearchController extends Controller
{
    public function __construct(private readonly GedPermissionService $permissions) {}

    /**
     * Recherche plein texte dans la GED (nom de document + nom de dossier).
     *
     * GET /ged/search?q=...
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = auth()->user();
        $q = trim($request->input('q', ''));

        $documents = collect();
        $folders = collect();

        if ($q !== '') {
            $documents = GedDocument::where('name', 'like', '%'.$q.'%')
                ->whereNull('deleted_at')
                ->with(['folder', 'creator:id,name'])
                ->get()
                ->filter(fn (GedDocument $doc) => $doc->folder !== null
                    && $this->permissions->canView($user, $doc->folder))
                ->values();

            $folders = GedFolder::where('name', 'like', '%'.$q.'%')
                ->visibleFor($user)
                ->whereNull('deleted_at')
                ->withCount(['children', 'documents'])
                ->orderBy('name')
                ->get();
        }

        $sidebarTree = GedFolder::roots()
            ->visibleFor($user)
            ->withCount(['children', 'documents'])
            ->orderBy('name')
            ->get();

        return view('ged.search', compact('q', 'documents', 'folders', 'sidebarTree'));
    }
}
