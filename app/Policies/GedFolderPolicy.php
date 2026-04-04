<?php

namespace App\Policies;

use App\Enums\GedPermissionLevel;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use App\Services\Ged\GedPermissionService;

class GedFolderPolicy
{
    public function __construct(private readonly GedPermissionService $permissions) {}

    /** Peut voir le contenu du dossier */
    public function view(User $user, GedFolder $folder): bool
    {
        return $this->permissions->canView($user, $folder);
    }

    /** Peut créer un sous-dossier ou uploader un document dans ce dossier */
    public function upload(User $user, GedFolder $folder): bool
    {
        return $this->permissions->canUpload($user, $folder);
    }

    /** Peut renommer, déplacer ou modifier les propriétés du dossier */
    public function update(User $user, GedFolder $folder): bool
    {
        return $this->permissions->canAdmin($user, $folder);
    }

    /** Peut supprimer le dossier */
    public function delete(User $user, GedFolder $folder): bool
    {
        return $this->permissions->canAdmin($user, $folder);
    }

    /** Peut gérer les droits du dossier */
    public function managePermissions(User $user, GedFolder $folder): bool
    {
        return $this->permissions->canAdmin($user, $folder);
    }

    /** Peut télécharger les documents du dossier */
    public function download(User $user, GedFolder $folder): bool
    {
        return $this->permissions->canDownload($user, $folder);
    }

    // ── Documents ────────────────────────────────────────────────────────────

    /** Peut lire / prévisualiser le document */
    public function viewDocument(User $user, GedDocument $document): bool
    {
        $folder = $document->folder;

        if ($folder === null) {
            return false;
        }

        return $this->permissions->can($user, $folder, GedPermissionLevel::View);
    }

    /** Peut télécharger le document */
    public function downloadDocument(User $user, GedDocument $document): bool
    {
        $folder = $document->folder;

        if ($folder === null) {
            return false;
        }

        return $this->permissions->can($user, $folder, GedPermissionLevel::Download);
    }

    /** Peut supprimer ou restaurer le document */
    public function manageDocument(User $user, GedDocument $document): bool
    {
        $folder = $document->folder;

        if ($folder === null) {
            return false;
        }

        // Créateur du document → peut gérer ses propres documents si upload minimum
        if ($document->created_by === $user->id) {
            return $this->permissions->can($user, $folder, GedPermissionLevel::Upload);
        }

        return $this->permissions->canAdmin($user, $folder);
    }
}
