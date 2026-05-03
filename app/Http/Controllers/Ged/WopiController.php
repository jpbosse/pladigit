<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedDocumentVersion;
use App\Models\Tenant\GedWopiToken;
use App\Services\AuditService;
use App\Services\Ged\GedPermissionService;
use App\Services\Ged\GedStorageInterface;
use App\Services\Ged\WopiLockService;
use App\Services\Ged\WopiTokenService;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoints WOPI pour Collabora Online.
 *
 * Ces routes sont publiques (pas d'auth Laravel) et hors du middleware tenant.
 * Le tenant est résolu depuis l'access_token au format "{org_slug}:{raw_token}".
 * Collabora n'a donc besoin que d'un seul aliasgroup fixe.
 *
 * Spec WOPI : https://learn.microsoft.com/en-us/microsoft-365/cloud-storage-partner-program/rest/
 *
 * Jalons implémentés :
 *   - CheckFileInfo  : GET  /wopi/files/{id}
 *   - GetFile        : GET  /wopi/files/{id}/contents
 *   - PutFile        : POST /wopi/files/{id}/contents
 *   - Lock / Unlock / RefreshLock / GetLock : POST /wopi/files/{id}
 */
class WopiController extends Controller
{
    public function __construct(
        private readonly WopiTokenService $tokens,
        private readonly TenantManager $tenantManager,
        private readonly AuditService $audit,
        private readonly WopiLockService $locks,
    ) {}

    // =========================================================================
    // CheckFileInfo
    // =========================================================================

    /**
     * CheckFileInfo — retourne les métadonnées du document.
     *
     * GET /wopi/{tenant}/files/{id}?access_token={raw_token}
     */
    public function checkFileInfo(string $tenant, int $fileId, Request $request): JsonResponse
    {
        $wopiToken = $this->resolveToken((string) $request->query('access_token', ''));

        if ($wopiToken === null || $wopiToken->document_id !== $fileId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $doc = $wopiToken->document;
        $user = $wopiToken->user;

        $folder = $doc->folder;
        $canWrite = $folder !== null && app(GedPermissionService::class)->canUpload($user, $folder);

        return response()->json([
            'BaseFileName' => $doc->name,
            'Size' => $doc->size_bytes,
            'Version' => (string) $doc->current_version,
            'OwnerId' => (string) $doc->created_by,
            'UserId' => (string) $user->id,
            'UserFriendlyName' => $user->name,
            'UserCanWrite' => $canWrite,
            'ReadOnly' => ! $canWrite,
            'SupportsLocks' => true,
            'SupportsUpdate' => true,
            'UserCanNotWriteRelative' => true,
            'PostMessageOrigin' => config('app.url'),
            'HostEditingUrl' => config('app.url'),
        ]);
    }

    // =========================================================================
    // GetFile
    // =========================================================================

    /**
     * GetFile — retourne le contenu binaire du document.
     *
     * GET /wopi/{tenant}/files/{id}/contents?access_token={raw_token}
     */
    public function getFile(string $tenant, int $fileId, Request $request): StreamedResponse
    {
        $wopiToken = $this->resolveToken((string) $request->query('access_token', ''));

        if ($wopiToken === null || $wopiToken->document_id !== $fileId) {
            abort(401);
        }

        $doc = $wopiToken->document;
        $path = $doc->disk_path;

        $storage = app(GedStorageInterface::class);

        abort_unless($storage->exists($path), 404);

        return response()->streamDownload(function () use ($storage, $path) {
            echo $storage->get($path);
        }, $doc->name, [
            'Content-Type' => $doc->mime_type ?? 'application/octet-stream',
            'Content-Length' => (string) $doc->size_bytes,
        ]);
    }

    // =========================================================================
    // PutFile
    // =========================================================================

    /**
     * PutFile — reçoit le contenu binaire de Collabora, crée une nouvelle version.
     *
     * POST /wopi/{tenant}/files/{id}/contents?access_token={raw_token}
     *
     * Si SupportsLocks=true, vérifie que X-WOPI-Lock correspond au verrou actif.
     */
    public function putFile(string $tenant, int $fileId, Request $request): Response|JsonResponse
    {
        $wopiToken = $this->resolveToken((string) $request->query('access_token', ''));

        if ($wopiToken === null || $wopiToken->document_id !== $fileId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $doc = $wopiToken->document;
        $user = $wopiToken->user;

        $folder = $doc->folder;
        if ($folder === null || ! app(GedPermissionService::class)->canUpload($user, $folder)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // ── Vérification du verrou ────────────────────────────────────────────
        $requestLockId = (string) $request->header('X-WOPI-Lock', '');
        if ($this->locks->isLockedByOther($doc->id, $requestLockId)) {
            $currentLock = $this->locks->getLock($doc->id);

            return response('', 409)->withHeaders([
                'X-WOPI-Lock' => $currentLock !== null ? $currentLock->lock_id : '',
            ]);
        }

        // ── Écriture ──────────────────────────────────────────────────────────
        $storage = app(GedStorageInterface::class);
        $content = $request->getContent();

        $ext = pathinfo($doc->disk_path, PATHINFO_EXTENSION);
        $newPath = dirname($doc->disk_path).'/'.Str::uuid().($ext ? '.'.$ext : '');

        if (! $storage->put($newPath, $content)) {
            return response()->json(['error' => 'Storage error'], 500);
        }

        // Archiver la version courante en conservant sa date d'origine
        GedDocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => $doc->current_version,
            'disk_path' => $doc->disk_path,
            'size_bytes' => $doc->size_bytes,
            'mime_type' => $doc->mime_type,
            'created_at' => $doc->updated_at ?? $doc->created_at,
            'uploaded_by' => $user->id,
        ]);

        $oldVersion = $doc->current_version;

        $doc->update([
            'disk_path' => $newPath,
            'size_bytes' => strlen($content),
            'current_version' => $oldVersion + 1,
        ]);

        $this->audit->log('ged.document.wopi.saved', $user, [
            'model_type' => GedDocument::class,
            'model_id' => $doc->id,
            'old' => ['version' => $oldVersion],
            'new' => ['version' => $oldVersion + 1, 'size_bytes' => strlen($content)],
        ]);

        return response('', 200);
    }

    // =========================================================================
    // Lock / Unlock / RefreshLock / GetLock
    // =========================================================================

    /**
     * Dispatch WOPI selon X-WOPI-Override.
     *
     * POST /wopi/{tenant}/files/{id}?access_token={raw_token}
     *
     * Override LOCK          → lock()
     * Override UNLOCK        → unlock()
     * Override REFRESH_LOCK  → refreshLock()
     * Override GET_LOCK      → getLock()
     */
    public function lockFile(string $tenant, int $fileId, Request $request): Response
    {
        $wopiToken = $this->resolveToken((string) $request->query('access_token', ''));

        if ($wopiToken === null || $wopiToken->document_id !== $fileId) {
            return response('', 401);
        }

        $override = (string) $request->header('X-WOPI-Override', '');
        $lockId = (string) $request->header('X-WOPI-Lock', '');
        $userId = $wopiToken->user->id;
        $docId = $wopiToken->document->id;

        return match ($override) {
            'LOCK' => $this->handleLock($docId, $lockId, $userId),
            'UNLOCK' => $this->handleUnlock($docId, $lockId),
            'REFRESH_LOCK' => $this->handleRefreshLock($docId, $lockId),
            'GET_LOCK' => $this->handleGetLock($docId),
            default => response('Unknown X-WOPI-Override', 400),
        };
    }

    // =========================================================================
    // Handlers privés (lock)
    // =========================================================================

    private function handleLock(int $docId, string $lockId, int $userId): Response
    {
        if ($lockId === '') {
            return response('Missing X-WOPI-Lock', 400);
        }

        $result = $this->locks->lock($docId, $lockId, $userId);

        if ($result['status'] === 'conflict') {
            return response('', 409)->withHeaders([
                'X-WOPI-Lock' => $result['current_lock_id'],
            ]);
        }

        return response('', 200);
    }

    private function handleUnlock(int $docId, string $lockId): Response
    {
        if ($lockId === '') {
            return response('Missing X-WOPI-Lock', 400);
        }

        $result = $this->locks->unlock($docId, $lockId);

        if ($result['status'] === 'conflict') {
            return response('', 409)->withHeaders([
                'X-WOPI-Lock' => $result['current_lock_id'],
            ]);
        }

        return response('', 200);
    }

    private function handleRefreshLock(int $docId, string $lockId): Response
    {
        if ($lockId === '') {
            return response('Missing X-WOPI-Lock', 400);
        }

        $result = $this->locks->refreshLock($docId, $lockId);

        if ($result['status'] === 'conflict') {
            return response('', 409)->withHeaders([
                'X-WOPI-Lock' => $result['current_lock_id'],
            ]);
        }

        return response('', 200);
    }

    private function handleGetLock(int $docId): Response
    {
        $lock = $this->locks->getLock($docId);

        return response('', 200)->withHeaders([
            'X-WOPI-Lock' => $lock !== null ? $lock->lock_id : '',
        ]);
    }

    // =========================================================================
    // Helper token
    // =========================================================================

    /**
     * Résout le tenant et valide le token WOPI.
     *
     * Deux formats supportés :
     *  - Nouveau (préféré) : tenant dans le paramètre de route {tenant},
     *    access_token = token brut 64 chars (sans colon).
     *    WOPISrc : /wopi/{tenant}/files/{id} — aucune query string dans le WOPISrc,
     *    ce qui évite que Collabora ajoute "?access_token=..." avec un double "?"
     *    (Collabora utilise toujours "?" même si le WOPISrc a déjà une query string).
     *  - Legacy : access_token au format "{slug}:{raw_token}" (rétrocompatibilité).
     *
     * Retourne null si le token est invalide, expiré ou le tenant introuvable.
     */
    private function resolveToken(string $accessToken): ?GedWopiToken
    {
        // Format nouveau : tenant dans le paramètre de route {tenant}
        $slug = (string) request()->route('tenant', '');

        if ($slug !== '') {
            $org = Organization::where('slug', $slug)
                ->where('status', 'active')
                ->first();

            if ($org === null) {
                return null;
            }

            $this->tenantManager->connectTo($org);

            return $this->tokens->validate($accessToken);
        }

        // Format legacy : "{slug}:{raw_token}"
        $parsed = $this->tokens->parseAccessToken($accessToken);

        if ($parsed === null) {
            return null;
        }

        $org = Organization::where('slug', $parsed['slug'])
            ->where('status', 'active')
            ->first();

        if ($org === null) {
            return null;
        }

        $this->tenantManager->connectTo($org);

        return $this->tokens->validate($parsed['token']);
    }
}
