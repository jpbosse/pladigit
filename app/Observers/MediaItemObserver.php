<?php

namespace App\Observers;

use App\Models\Tenant\MediaItem;
use App\Services\MediaService;

/**
 * Observer sur MediaItem.
 *
 * Recalcule automatiquement le flag is_duplicate après chaque suppression,
 * quelle que soit la source (interface, API, CLI, sync NAS).
 *
 * Règle :
 *   - 1 seul item avec ce SHA-256 → is_duplicate = false
 *   - 2+ items avec ce SHA-256 → is_duplicate = true sur tous
 */
class MediaItemObserver
{
    public function __construct(private MediaService $mediaService) {}

    /**
     * Après soft-delete : recalculer le flag pour les survivants.
     */
    public function deleted(MediaItem $item): void
    {
        if ($item->sha256_hash) {
            $this->mediaService->recalculateDuplicateFlag($item->sha256_hash);
        }
    }

    /**
     * Après restauration (undelete) : recalculer aussi.
     */
    public function restored(MediaItem $item): void
    {
        if ($item->sha256_hash) {
            $this->mediaService->recalculateDuplicateFlag($item->sha256_hash);
        }
    }
}
