<?php

namespace App\Models\Concerns;

use App\Models\Tenant\Share;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait à ajouter sur tout modèle pouvant être partagé.
 * Usage : use Shareable; dans MediaAlbum, GedDocument, GedFolder, etc.
 */
trait Shareable
{
    public function shares(): MorphMany
    {
        return $this->morphMany(Share::class, 'shareable');
    }

    public function sharesForRole(string $role): MorphMany
    {
        return $this->shares()
            ->where('shared_with_type', 'role')
            ->where('shared_with_role', $role);
    }

    public function sharesForUser(int $userId): MorphMany
    {
        return $this->shares()
            ->where('shared_with_type', 'user')
            ->where('shared_with_id', $userId);
    }

    public function sharesForDepartment(int $deptId): MorphMany
    {
        return $this->shares()
            ->where('shared_with_type', 'department')
            ->where('shared_with_id', $deptId);
    }
}
