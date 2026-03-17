<?php

namespace App\Jobs;

use App\Models\Platform\Organization;
use App\Models\Tenant\MediaItem;
use App\Services\MediaService;
use App\Services\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMediaUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private readonly int $mediaItemId,
        private readonly string $orgSlug,
        private readonly string $nasPath,
        private readonly string $mimeType,
    ) {}

    public function handle(TenantManager $tenantManager, MediaService $mediaService): void
    {
        // Reconnecter au bon tenant
        $org = Organization::where('slug', $this->orgSlug)->firstOrFail();
        $tenantManager->connectTo($org);

        $item = MediaItem::find($this->mediaItemId);
        if (! $item) {
            Log::warning('ProcessMediaUpload — MediaItem introuvable', ['id' => $this->mediaItemId]);

            return;
        }

        try {
            $nas = app(\App\Services\Nas\NasManager::class)->driver();
            $contents = $nas->readFile($this->nasPath);

            if ($contents === false || $contents === null) {
                throw new \RuntimeException("Impossible de lire le fichier NAS : {$this->nasPath}");
            }

            // Miniature
            $thumbPath = null;
            if (str_starts_with($this->mimeType, 'image/')) {
                $thumbPath = $mediaService->generateThumbnail($contents, $this->nasPath, $nas);
            }

            // EXIF + dimensions
            $tmpPath = tempnam(sys_get_temp_dir(), 'pladigit_');
            file_put_contents($tmpPath, $contents);

            $exifData = $mediaService->extractExif($tmpPath, $this->mimeType);
            [$width, $height] = $mediaService->getImageDimensions($tmpPath, $this->mimeType);

            @unlink($tmpPath);

            $item->update([
                'thumb_path' => $thumbPath,
                'exif_data' => $exifData ?: null,
                'width_px' => $width,
                'height_px' => $height,
                'processing_status' => 'done',
            ]);

        } catch (\Throwable $e) {
            Log::error('ProcessMediaUpload — échec traitement', [
                'item_id' => $this->mediaItemId,
                'nas_path' => $this->nasPath,
                'error' => $e->getMessage(),
            ]);
            $item->update(['processing_status' => 'failed']);
            $this->fail($e);
        }
    }
}
