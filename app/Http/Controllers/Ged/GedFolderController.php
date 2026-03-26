<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GedFolderController extends Controller
{
    public function __construct(private AuditService $audit) {}

    /**
     * Page d'accueil GED : dossiers racine + documents racine du tenant.
     */
    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $folders = GedFolder::roots()
            ->visibleFor($user)
            ->withCount(['children', 'documents'])
            ->orderBy('name')
            ->get();

        $sidebarTree = $this->buildSidebarTree($user);

        return view('ged.index', compact('folders', 'sidebarTree'));
    }

    /**
     * Contenu d'un dossier : sous-dossiers + documents.
     */
    public function show(GedFolder $folder): View
    {
        /** @var User $user */
        $user = auth()->user();

        $this->authorizeView($folder, $user);

        $subFolders = GedFolder::where('parent_id', $folder->id)
            ->visibleFor($user)
            ->withCount(['children', 'documents'])
            ->orderBy('name')
            ->get();

        $documents = $folder->documents()
            ->with('creator:id,name')
            ->orderBy('name')
            ->get();

        $ancestors = $folder->ancestors();
        $ancestorIds = array_map(fn (GedFolder $f) => $f->id, $ancestors);
        $sidebarTree = $this->buildSidebarTree($user);

        return view('ged.folders.show', compact(
            'folder', 'subFolders', 'documents',
            'ancestors', 'ancestorIds', 'sidebarTree'
        ));
    }

    /**
     * Lazy-load des enfants d'un dossier (sidebar AJAX).
     * Retourne [{id, name, slug, path, has_children, is_private}]
     */
    public function children(GedFolder $folder): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $children = GedFolder::where('parent_id', $folder->id)
            ->visibleFor($user)
            ->withCount(['children', 'documents'])
            ->orderBy('name')
            ->get();

        return response()->json($children->map(fn (GedFolder $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'slug' => $f->slug,
            'path' => $f->path,
            'url' => route('ged.folders.show', $f),
            'has_children' => ($f->children_count ?? 0) > 0,
            'is_private' => (bool) $f->is_private,
            'doc_count' => $f->documents_count ?? 0,
        ])->values());
    }

    /**
     * Créer un dossier.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:tenant.ged_folders,id'],
            'is_private' => ['boolean'],
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;
        $isPrivate = (bool) ($validated['is_private'] ?? false);

        if ($parentId !== null) {
            $parent = GedFolder::findOrFail($parentId);
            $this->authorizeView($parent, $user);
        }

        $slug = GedFolder::uniqueSlug($validated['name'], $parentId);

        $folder = GedFolder::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'path' => $this->computePath($slug, $parentId),
            'parent_id' => $parentId,
            'is_private' => $isPrivate,
            'created_by' => $user->id,
        ]);

        $this->audit->log('ged.folder.created', $user, [
            'model_type' => GedFolder::class,
            'model_id' => $folder->id,
            'new' => ['name' => $folder->name, 'path' => $folder->path],
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'folder' => [
                'id' => $folder->id,
                'name' => $folder->name,
                'path' => $folder->path,
                'url' => route('ged.folders.show', $folder),
            ]]);
        }

        $redirect = $parentId
            ? route('ged.folders.show', $parentId)
            : route('ged.index');

        return redirect($redirect)->with('success', "Dossier « {$folder->name} » créé.");
    }

    /**
     * Renommer un dossier.
     */
    public function update(Request $request, GedFolder $folder): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $this->authorizeManage($folder, $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_private' => ['boolean'],
        ]);

        $oldName = $folder->name;
        $newSlug = GedFolder::uniqueSlug($validated['name'], $folder->parent_id, $folder->id);

        // Recalculer le path et propager aux descendants
        $oldPath = $folder->path;
        $newPath = $this->computePath($newSlug, $folder->parent_id);

        $folder->update([
            'name' => $validated['name'],
            'slug' => $newSlug,
            'path' => $newPath,
            'is_private' => (bool) ($validated['is_private'] ?? $folder->is_private),
        ]);

        if ($oldPath !== $newPath) {
            $this->propagatePath($folder, $oldPath, $newPath);
        }

        $this->audit->log('ged.folder.updated', $user, [
            'model_type' => GedFolder::class,
            'model_id' => $folder->id,
            'old' => ['name' => $oldName],
            'new' => ['name' => $folder->name],
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'name' => $folder->name, 'path' => $folder->path]);
        }

        return back()->with('success', "Dossier renommé en « {$folder->name} ».");
    }

    /**
     * Déplacer un dossier dans un autre parent.
     */
    public function move(Request $request, GedFolder $folder): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $this->authorizeManage($folder, $user);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:tenant.ged_folders,id'],
        ]);

        $newParentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;

        // Interdire de se mettre soi-même comme parent
        if ($newParentId === $folder->id) {
            return response()->json(['error' => 'Un dossier ne peut pas être son propre parent.'], 422);
        }

        // Protection anti-boucle circulaire
        if ($newParentId !== null && $folder->isAncestorOf(GedFolder::findOrFail($newParentId))) {
            return response()->json(['error' => 'Impossible de déplacer un dossier dans l\'un de ses descendants.'], 422);
        }

        // Déjà à la bonne position
        if ($folder->parent_id === $newParentId) {
            return response()->json(['ok' => true, 'message' => 'Aucun changement.']);
        }

        $oldPath = $folder->path;
        $newPath = $this->computePath($folder->slug, $newParentId);

        $folder->update(['parent_id' => $newParentId, 'path' => $newPath]);

        if ($oldPath !== $newPath) {
            $this->propagatePath($folder, $oldPath, $newPath);
        }

        $this->audit->log('ged.folder.moved', $user, [
            'model_type' => GedFolder::class,
            'model_id' => $folder->id,
            'old' => ['parent_id' => $folder->getOriginal('parent_id'), 'path' => $oldPath],
            'new' => ['parent_id' => $newParentId, 'path' => $newPath],
        ]);

        return response()->json(['ok' => true, 'new_path' => $newPath]);
    }

    /**
     * Supprimer un dossier (soft delete).
     * Refuse si le dossier contient des documents ou sous-dossiers non supprimés.
     */
    public function destroy(GedFolder $folder): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $this->authorizeManage($folder, $user);

        $docCount = $folder->documents()->count();
        $childCount = GedFolder::where('parent_id', $folder->id)->count();

        if ($docCount > 0 || $childCount > 0) {
            $msg = 'Impossible de supprimer un dossier non vide.';
            if (request()->wantsJson()) {
                return response()->json(['error' => $msg], 422);
            }

            return back()->withErrors(['folder' => $msg]);
        }

        $name = $folder->name;

        $this->audit->log('ged.folder.deleted', $user, [
            'model_type' => GedFolder::class,
            'model_id' => $folder->id,
            'old' => ['name' => $name],
        ]);

        $folder->delete();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        $redirect = $folder->parent_id
            ? route('ged.folders.show', $folder->parent_id)
            : route('ged.index');

        return redirect($redirect)->with('success', "Dossier « {$name} » supprimé.");
    }

    // ── Helpers privés ───────────────────────────────────────

    /**
     * Calcule le path complet d'un dossier à partir de son slug et de son parent.
     */
    private function computePath(string $slug, ?int $parentId): string
    {
        if ($parentId === null) {
            return '/'.$slug;
        }

        $parent = GedFolder::findOrFail($parentId);

        return rtrim($parent->path, '/').'/'.$slug;
    }

    /**
     * Propage un changement de path à tous les descendants.
     */
    private function propagatePath(GedFolder $folder, string $oldPath, string $newPath): void
    {
        $oldPrefix = $oldPath.'/';
        $newPrefix = $newPath.'/';

        GedFolder::where('path', 'like', $oldPrefix.'%')
            ->each(function (GedFolder $desc) use ($oldPrefix, $newPrefix): void {
                $desc->update([
                    'path' => $newPrefix.substr($desc->path, strlen($oldPrefix)),
                ]);
            });
    }

    /**
     * Construit l'arbre des dossiers racine pour la sidebar.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, GedFolder>
     */
    private function buildSidebarTree(User $user)
    {
        return GedFolder::roots()
            ->visibleFor($user)
            ->withCount(['children', 'documents'])
            ->orderBy('name')
            ->get();
    }

    /** Vérifie qu'un utilisateur peut voir un dossier. */
    private function authorizeView(GedFolder $folder, User $user): void
    {
        if ($folder->is_private && ! $this->isDgs($user) && $folder->created_by !== $user->id) {
            abort(403, 'Accès refusé à ce dossier privé.');
        }
    }

    /** Vérifie qu'un utilisateur peut administrer un dossier. */
    private function authorizeManage(GedFolder $folder, User $user): void
    {
        if (! $this->isDgs($user) && $folder->created_by !== $user->id) {
            abort(403, 'Vous ne pouvez pas modifier ce dossier.');
        }
    }

    /** Retourne true si l'utilisateur est Admin/Président/DGS ou supérieur. */
    private function isDgs(User $user): bool
    {
        return $user->role !== null
            && \App\Enums\UserRole::from($user->role)->atLeast(\App\Enums\UserRole::DGS);
    }
}
