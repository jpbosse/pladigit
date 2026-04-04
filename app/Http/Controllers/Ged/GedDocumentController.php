<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedDocumentVersion;
use App\Models\Tenant\GedFolder;
use App\Models\Tenant\User;
use App\Services\AuditService;
use App\Services\Ged\GedStorageInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GedDocumentController extends Controller
{
    public function __construct(
        private readonly GedStorageInterface $storage,
        private readonly AuditService $audit,
    ) {}

    // =========================================================================
    // Upload
    // =========================================================================

    /**
     * Upload d'un ou plusieurs fichiers dans un dossier GED.
     *
     * Si un document avec le même nom existe déjà dans le dossier, une nouvelle
     * version est créée (l'ancienne est archivée dans ged_document_versions).
     *
     * Accepte multipart/form-data avec :
     *   - files[]   : fichier(s) uploadé(s)
     *   - folder_id : identifiant du dossier cible
     *
     * Retourne JSON {ok, stored, documents[], errors[]} pour le D&D multi-fichiers.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $request->validate([
            'folder_id' => ['required', 'integer', 'exists:tenant.ged_folders,id'],
        ]);

        $folder = GedFolder::findOrFail((int) $request->input('folder_id'));

        $this->authorize('upload', $folder);

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => [
                'required',
                'file',
                'max:'.(int) (config('ged.max_file_size', 50 * 1024 * 1024) / 1024),
                'mimetypes:'.implode(',', config('ged.allowed_mimes', [])),
            ],
        ]);

        $stored = 0;
        $errors = [];
        $documents = [];

        foreach ($request->file('files', []) as $file) {
            try {
                $ext = strtolower($file->getClientOriginalExtension());
                $uuid = Str::uuid()->toString();
                $storedName = $ext ? "{$uuid}.{$ext}" : $uuid;
                $folderDir = ltrim($folder->path, '/');
                $diskPath = $folderDir ? "{$folderDir}/{$storedName}" : $storedName;

                $this->storage->put($diskPath, file_get_contents($file->getRealPath()));

                $existing = GedDocument::where('folder_id', $folder->id)
                    ->where('name', $file->getClientOriginalName())
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing !== null) {
                    // ── Re-upload : archiver la version courante ──────────
                    GedDocumentVersion::create([
                        'document_id' => $existing->id,
                        'version_number' => $existing->current_version,
                        'disk_path' => $existing->disk_path,
                        'size_bytes' => $existing->size_bytes,
                        'mime_type' => $existing->mime_type,
                        'uploaded_by' => $user->id,
                    ]);

                    $existing->update([
                        'disk_path' => $diskPath,
                        'size_bytes' => $file->getSize(),
                        'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                        'current_version' => $existing->current_version + 1,
                    ]);

                    $this->audit->log('ged.document.version.created', $user, [
                        'model_type' => GedDocument::class,
                        'model_id' => $existing->id,
                        'new' => ['version' => $existing->current_version],
                    ]);

                    $documents[] = ['id' => $existing->id, 'name' => $existing->name, 'version' => $existing->current_version];
                } else {
                    // ── Nouveau document ──────────────────────────────────
                    $doc = GedDocument::create([
                        'folder_id' => $folder->id,
                        'name' => $file->getClientOriginalName(),
                        'disk_path' => $diskPath,
                        'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                        'size_bytes' => $file->getSize(),
                        'created_by' => $user->id,
                    ]);

                    $this->audit->log('ged.document.uploaded', $user, [
                        'model_type' => GedFolder::class,
                        'model_id' => $folder->id,
                        'new' => ['name' => $file->getClientOriginalName()],
                    ]);

                    $documents[] = ['id' => $doc->id, 'name' => $doc->name, 'version' => 1];
                }

                $stored++;

            } catch (\Throwable $e) {
                $errors[] = [
                    'file' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'ok' => $stored > 0,
            'stored' => $stored,
            'documents' => $documents,
            'errors' => $errors,
        ], $stored > 0 ? 200 : 422);
    }

    // =========================================================================
    // Download (force téléchargement)
    // =========================================================================

    /**
     * Force le téléchargement du document (version courante).
     */
    public function download(GedDocument $document): StreamedResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $this->authorize('downloadDocument', $document);

        $stream = $this->storage->readStream($document->disk_path);

        if ($stream === false) {
            abort(404, 'Fichier introuvable sur le stockage.');
        }

        $this->audit->log('ged.document.downloaded', $user, [
            'model_type' => GedDocument::class,
            'model_id' => $document->id,
        ]);

        return response()->streamDownload(
            callback: function () use ($stream): void {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            name: $document->name,
            headers: [
                'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                'Content-Length' => $document->size_bytes,
            ],
        );
    }

    // =========================================================================
    // Serve (inline pour PDF et images)
    // =========================================================================

    /**
     * Affiche le document inline dans le navigateur (PDF, images).
     * Redirige vers download() pour les autres types.
     */
    public function serve(GedDocument $document): StreamedResponse|RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $this->authorize('viewDocument', $document);

        if (! $document->isPreviewable()) {
            return redirect()->route('ged.documents.download', $document);
        }

        $stream = $this->storage->readStream($document->disk_path);

        if ($stream === false) {
            abort(404, 'Fichier introuvable sur le stockage.');
        }

        return response()->stream(
            callback: function () use ($stream): void {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            status: 200,
            headers: [
                'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                'Content-Length' => $document->size_bytes,
                'Content-Disposition' => 'inline; filename="'.addslashes($document->name).'"',
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }

    // =========================================================================
    // Versions
    // =========================================================================

    /**
     * Retourne l'historique complet des versions d'un document (JSON).
     *
     * Format :
     *   {
     *     current:  { version_number, size_bytes, uploaded_by_name, created_at },
     *     versions: [ { version_number, size_bytes, mime_type, uploaded_by_name, created_at, download_url }, … ]
     *   }
     */
    public function versions(GedDocument $document): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $this->authorize('viewDocument', $document);

        $archived = $document->versions()
            ->with('uploader:id,name')
            ->get()
            ->map(fn (GedDocumentVersion $v) => [
                'version_number' => $v->version_number,
                'size_bytes' => $v->size_bytes,
                'mime_type' => $v->mime_type,
                'uploaded_by_name' => $v->uploader->name ?? '—',
                'created_at' => $v->created_at->format('d/m/Y H:i'),
                'download_url' => route('ged.documents.versions.download', [$document, $v->version_number]),
            ]);

        $current = [
            'version_number' => $document->current_version,
            'size_bytes' => $document->size_bytes,
            'uploaded_by_name' => $document->creator->name ?? '—',
            'created_at' => $document->updated_at?->format('d/m/Y H:i') ?? $document->created_at->format('d/m/Y H:i'),
        ];

        return response()->json([
            'current' => $current,
            'versions' => $archived,
        ]);
    }

    /**
     * Télécharge une version archivée spécifique.
     *
     * @param  int  $version  Numéro de version archivée
     */
    public function downloadVersion(GedDocument $document, int $version): StreamedResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $this->authorize('downloadDocument', $document);

        $archivedVersion = GedDocumentVersion::where('document_id', $document->id)
            ->where('version_number', $version)
            ->firstOrFail();

        $stream = $this->storage->readStream($archivedVersion->disk_path);

        if ($stream === false) {
            abort(404, 'Fichier de cette version introuvable sur le stockage.');
        }

        return response()->streamDownload(
            callback: function () use ($stream): void {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            name: "v{$version}_{$document->name}",
            headers: [
                'Content-Type' => $archivedVersion->mime_type ?? 'application/octet-stream',
                'Content-Length' => $archivedVersion->size_bytes,
            ],
        );
    }

    /**
     * Restaure une version archivée comme version courante (stratégie "revert forward").
     *
     * La version courante est d'abord archivée, puis le document est mis à jour
     * avec les données de la version cible. current_version est incrémenté.
     *
     * @param  int  $version  Numéro de version à restaurer
     */
    public function restoreVersion(GedDocument $document, int $version): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $this->authorize('manageDocument', $document);

        $targetVersion = GedDocumentVersion::where('document_id', $document->id)
            ->where('version_number', $version)
            ->firstOrFail();

        // Archiver la version courante
        GedDocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $document->current_version,
            'disk_path' => $document->disk_path,
            'size_bytes' => $document->size_bytes,
            'mime_type' => $document->mime_type,
            'uploaded_by' => $user->id,
        ]);

        // Mettre à jour le document avec la version cible
        $document->update([
            'disk_path' => $targetVersion->disk_path,
            'size_bytes' => $targetVersion->size_bytes,
            'mime_type' => $targetVersion->mime_type,
            'current_version' => $document->current_version + 1,
        ]);

        // Supprimer la version archivée restaurée (maintenant version courante)
        $targetVersion->delete();

        $this->audit->log('ged.document.version.restored', $user, [
            'model_type' => GedDocument::class,
            'model_id' => $document->id,
            'new' => [
                'restored_from' => $version,
                'current_version' => $document->current_version,
            ],
        ]);

        return response()->json([
            'ok' => true,
            'current_version' => $document->current_version,
        ]);
    }

    // =========================================================================
    // Suppression
    // =========================================================================

    /**
     * Supprime un document (soft delete + suppression physique du fichier courant).
     * Les versions archivées sont supprimées en cascade par la BDD.
     */
    public function destroy(GedDocument $document): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $this->authorize('manageDocument', $document);

        $name = $document->name;
        $path = $document->disk_path;
        $folderId = $document->folder_id;

        $this->audit->log('ged.document.deleted', $user, [
            'model_type' => GedDocument::class,
            'model_id' => $document->id,
            'old' => ['name' => $name, 'disk_path' => $path],
        ]);

        // Collecter les chemins des versions archivées avant le soft delete
        $archivedPaths = $document->versions()->pluck('disk_path')->all();

        // Soft delete en base d'abord
        $document->delete();

        // Suppression physique du fichier courant (non bloquant)
        try {
            $this->storage->delete($path);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('GedDocument — suppression fichier courant échouée', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        // Suppression physique des fichiers des versions archivées (non bloquant)
        foreach ($archivedPaths as $archivedPath) {
            try {
                $this->storage->delete($archivedPath);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('GedDocument — suppression fichier version archivée échouée', [
                    'path' => $archivedPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()
            ->route('ged.folders.show', $folderId)
            ->with('success', "Document « {$name} » supprimé.");
    }
}
