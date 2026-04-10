<?php

namespace App\Jobs;

use App\Models\Platform\Organization;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\GedFolder;
use App\Services\Ged\GedStorageInterface;
use App\Services\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Job de traitement d'un upload GED.
 *
 * Flux :
 *   1. Le contrôleur stocke le fichier uploadé en zone temporaire (disk local)
 *   2. Dispatch de ce job avec les métadonnées
 *   3. Le job déplace le fichier vers son emplacement définitif GED
 *   4. Crée l'enregistrement GedDocument en base
 *   5. Supprime le fichier temporaire
 *
 * En cas d'échec, le fichier temporaire est nettoyé dans failed().
 */
class ProcessGedUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        private readonly string $tempPath,      // chemin relatif sur disk local (zone tmp)
        private readonly int $folderId,
        private readonly string $originalName,
        private readonly string $mimeType,
        private readonly int $sizeBytes,
        private readonly string $orgSlug,
        private readonly int $userId,
    ) {}

    public function handle(TenantManager $tenantManager): void
    {
        // 1. Reconnecter au bon tenant
        $org = Organization::where('slug', $this->orgSlug)->firstOrFail();
        $tenantManager->connectTo($org);

        // Résolution du driver APRÈS connectTo() — TenantSettings::first() requiert
        // la connexion tenant configurée. Injecter GedStorageInterface directement
        // dans handle() ferait résoudre GedStorageManager avant que le tenant soit prêt.
        $storage = app(GedStorageInterface::class);

        // 2. Construire le chemin définitif (relatif à la racine du driver GED)
        //    Structure : {chemin-dossier}/{uuid}.{ext}
        //    Ex : mairie/rh/contrats/550e8400-e29b-41d4-a716-446655440000.pdf
        $folder = GedFolder::findOrFail($this->folderId);
        $ext = pathinfo($this->originalName, PATHINFO_EXTENSION);
        $destPath = $this->buildDestPath($ext, $folder->path);

        // 3. Lire le fichier temporaire et le stocker à destination
        $tmpContents = Storage::disk('local')->get($this->tempPath);

        if ($tmpContents === null) {
            Log::error('ProcessGedUpload — fichier temporaire introuvable', [
                'tempPath' => $this->tempPath,
            ]);
            $this->fail(new \RuntimeException("Fichier temporaire introuvable : {$this->tempPath}"));

            return;
        }

        $stored = $storage->put($destPath, $tmpContents);

        if (! $stored) {
            $this->fail(new \RuntimeException("Impossible d'écrire le fichier GED : {$destPath}"));

            return;
        }

        // 4. Créer l'enregistrement GedDocument
        GedDocument::create([
            'folder_id' => $this->folderId,
            'name' => $this->originalName,
            'disk_path' => $destPath,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'current_version' => 1,
            'created_by' => $this->userId,
        ]);

        // 5. Supprimer le fichier temporaire
        Storage::disk('local')->delete($this->tempPath);

        Log::info('ProcessGedUpload — document créé', [
            'org' => $this->orgSlug,
            'folder' => $this->folderId,
            'name' => $this->originalName,
            'destPath' => $destPath,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        // Nettoyage du fichier temporaire en cas d'échec définitif
        Storage::disk('local')->delete($this->tempPath);

        Log::error('ProcessGedUpload — échec définitif', [
            'tempPath' => $this->tempPath,
            'folder' => $this->folderId,
            'name' => $this->originalName,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Construit le chemin de stockage définitif, relatif à la racine du driver GED.
     * Structure : {chemin-dossier}/{uuid}.{ext}
     * Ex : mairie/rh/contrats/550e8400-…-000.pdf
     *
     * Le chemin reflète directement l'arborescence GED (pas de date/org dans le path).
     */
    private function buildDestPath(string $ext, string $folderPath): string
    {
        $uuid = Str::uuid()->toString();
        $filename = $ext ? "{$uuid}.{$ext}" : $uuid;
        $dir = trim($folderPath, '/');

        return $dir !== '' ? "{$dir}/{$filename}" : $filename;
    }
}
