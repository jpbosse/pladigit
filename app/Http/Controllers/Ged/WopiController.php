<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedDocumentVersion;
use App\Services\AuditService;
use App\Services\Ged\GedPermissionService;
use App\Services\Ged\GedStorageInterface;
use App\Services\Ged\WopiTokenService;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
 * Implémenté au Jalon 1 :
 *   - CheckFileInfo  : GET  /wopi/files/{id}
 *   - GetFile        : GET  /wopi/files/{id}/contents
 *
 * Réservé au Jalon 2 :
 *   - PutFile        : POST /wopi/files/{id}/contents
 */
class WopiController extends Controller
{
    public function __construct(
        private readonly WopiTokenService $tokens,
        private readonly TenantManager $tenantManager,
        private readonly AuditService $audit,
    ) {}

    /**
     * CheckFileInfo — retourne les métadonnées du document.
     *
     * GET /wopi/files/{id}?access_token={org_slug}:{raw_token}
     */
    public function checkFileInfo(int $fileId, Request $request): JsonResponse
    {
        $wopiToken = $this->resolveToken((string) $request->query('access_token', ''));

        if ($wopiToken === null || $wopiToken->document_id !== $fileId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $doc = $wopiToken->document;
        $user = $wopiToken->user;

        $folder   = $doc->folder;
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
            'SupportsLocks' => false,
        ]);
    }

    /**
     * GetFile — retourne le contenu binaire du document.
     *
     * GET /wopi/files/{id}/contents?access_token={org_slug}:{raw_token}
     */
    public function getFile(int $fileId, Request $request): StreamedResponse
    {
        $wopiToken = $this->resolveToken((string) $request->query('access_token', ''));

        if ($wopiToken === null || $wopiToken->document_id !== $fileId) {
            abort(401);
        }

        $doc = $wopiToken->document;
        $path = $doc->disk_path;

        // Le tenant est maintenant connecté (résolu dans resolveToken()),
        // on peut résoudre le driver de stockage avec les bons settings.
        $storage = app(GedStorageInterface::class);

        abort_unless($storage->exists($path), 404);

        return response()->streamDownload(function () use ($storage, $path) {
            echo $storage->get($path);
        }, $doc->name, [
            'Content-Type' => $doc->mime_type ?? 'application/octet-stream',
            'Content-Length' => (string) $doc->size_bytes,
        ]);
    }

    /**
     * PutFile — reçoit le contenu binaire de Collabora, crée une nouvelle version.
     *
     * POST /wopi/files/{id}/contents?access_token={org_slug}:{raw_token}
     */
    public function putFile(int $fileId, Request $request): \Illuminate\Http\Response|JsonResponse
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

        $storage = app(GedStorageInterface::class);
        $content = $request->getContent();

        // Nouveau chemin UUID pour conserver l'ancienne version intacte sur disque
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

        // Mettre à jour le document avec la nouvelle version
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

    /**
     * Parse l'access_token, résout le tenant, valide le token WOPI.
     * Retourne null si le token est invalide, expiré ou le tenant introuvable.
     */
    private function resolveToken(string $accessToken): ?\App\Models\Tenant\GedWopiToken
    {
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
