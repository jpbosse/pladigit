<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaAlbum;
use App\Models\Tenant\MediaShareLink;
use App\Models\Tenant\User;
use Illuminate\Http\Request;

/**
 * Gestion des liens de partage temporaires d'un album.
 * Accès réservé aux utilisateurs ayant la permission `manage` sur l'album.
 */
class MediaShareLinkController extends Controller
{
    /**
     * Retourne la liste des liens actifs de l'album (JSON).
     */
    public function index(MediaAlbum $album)
    {
        $this->authorize('manage', $album);

        $links = MediaShareLink::where('album_id', $album->id)
            ->with('creator')
            ->latest()
            ->get();

        return response()->json($links->map(fn ($l) => [
            'id' => $l->id,
            'url' => route('media.shared.show', $l->token),
            'expires_at' => $l->expires_at?->format('d/m/Y'),
            'is_expired' => $l->isExpired(),
            'allow_download' => $l->allow_download,
            'has_password' => $l->password_hash !== null,
            'created_by' => $l->creator?->name,
            'created_at' => $l->created_at->format('d/m/Y'),
        ]));
    }

    /**
     * Crée un nouveau lien de partage.
     */
    public function store(Request $request, MediaAlbum $album)
    {
        $this->authorize('manage', $album);

        $validated = $request->validate([
            'expires_in_days' => ['nullable', 'integer', 'in:1,7,30,90'],
            'allow_download' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:4', 'max:100'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        $link = MediaShareLink::generate(
            $album,
            $user,
            (bool) ($validated['allow_download'] ?? true),
            isset($validated['expires_in_days']) ? (int) $validated['expires_in_days'] : 7,
            $validated['password'] ?? null,
        );

        return response()->json([
            'id' => $link->id,
            'url' => route('media.shared.show', $link->token),
            'expires_at' => $link->expires_at?->format('d/m/Y'),
            'allow_download' => $link->allow_download,
            'has_password' => $link->password_hash !== null,
            'created_by' => $user->name,
            'created_at' => $link->created_at->format('d/m/Y'),
        ]);
    }

    /**
     * Révoque un lien de partage.
     */
    public function destroy(MediaAlbum $album, MediaShareLink $link)
    {
        $this->authorize('manage', $album);

        if ($link->album_id !== $album->id) {
            abort(403);
        }

        $link->delete();

        return response()->json(['ok' => true]);
    }
}
