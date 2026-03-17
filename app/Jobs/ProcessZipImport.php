<?php

namespace App\Jobs;

use App\Models\Platform\Organization;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\User;
use App\Services\MediaService;
use App\Services\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessZipImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600; // 10 min — ZIPs peuvent être volumineux

    public function __construct(
        private readonly string $zipStoragePath, // chemin dans storage/app/tmp/
        private readonly int $albumId,
        private readonly int $uploaderId,
        private readonly string $orgSlug,
    ) {}

    public function handle(TenantManager $tenantManager, MediaService $mediaService): void
    {
        // Reconnecter au bon tenant
        $org = Organization::where('slug', $this->orgSlug)->firstOrFail();
        $tenantManager->connectTo($org);

        $album = MediaAlbum::findOrFail($this->albumId);
        $uploader = User::findOrFail($this->uploaderId);

        $zipPath = storage_path('app/private/'.$this->zipStoragePath);

        if (! file_exists($zipPath)) {
            Log::error('ProcessZipImport — fichier ZIP introuvable', ['path' => $zipPath]);

            return;
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            Log::error('ProcessZipImport — impossible d\'ouvrir le ZIP', ['path' => $zipPath]);
            @unlink(storage_path('app/private/'.$this->zipStoragePath));

            return;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'pdf'];
        $tmpDir = sys_get_temp_dir().'/pladigit_zip_'.uniqid();
        mkdir($tmpDir, 0775, true);

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (! $stat) {
                    continue;
                }

                $name = basename($stat['name']);

                // Ignorer les dossiers, fichiers cachés et non supportés
                if (str_ends_with($stat['name'], '/')) {
                    continue;
                }
                if (str_starts_with($name, '.')) {
                    continue;
                }
                if (str_starts_with($name, '__MACOSX')) {
                    continue;
                }

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (! in_array($ext, $allowed)) {
                    $skipped++;

                    continue;
                }

                // Extraire dans un fichier temporaire
                $tmpFile = $tmpDir.'/'.$name;
                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    $errors++;

                    continue;
                }
                file_put_contents($tmpFile, $content);

                try {
                    $uploaded = new UploadedFile(
                        path: $tmpFile,
                        originalName: $name,
                        mimeType: mime_content_type($tmpFile) ?: 'application/octet-stream',
                        error: UPLOAD_ERR_OK,
                        test: true, // bypass is_uploaded_file()
                    );
                    $mediaService->upload($uploaded, $album, $uploader);
                    $imported++;
                } catch (\RuntimeException $e) {
                    // Doublon ou quota — on logue et on continue
                    Log::info('ProcessZipImport — fichier ignoré', [
                        'file' => $name,
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                } catch (\Throwable $e) {
                    Log::error('ProcessZipImport — erreur inattendue', [
                        'file' => $name,
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                } finally {
                    @unlink($tmpFile);
                }
            }
        } finally {
            $zip->close();
            @rmdir($tmpDir);
            Storage::delete($this->zipStoragePath); // nettoyage ZIP temporaire
        }

        Log::info('ProcessZipImport — terminé', [
            'album_id' => $this->albumId,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }
}
