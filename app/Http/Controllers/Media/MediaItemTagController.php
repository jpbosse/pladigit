<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MediaItem;
use App\Models\Tenant\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des tags manuels sur les médias.
 */
class MediaItemTagController extends Controller
{
    /**
     * Attache un tag à un média (crée le tag s'il n'existe pas).
     */
    public function store(Request $request, MediaItem $item): JsonResponse
    {
        $this->authorize('manage', $item);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[^,;]+$/'],
        ]);

        $name = mb_strtolower(trim($validated['name']));

        $tag = Tag::firstOrCreate(['name' => $name]);

        if (! $item->tags()->where('tag_id', $tag->id)->exists()) {
            $item->tags()->attach($tag->id);
        }

        return response()->json([
            'id' => $tag->id,
            'name' => $tag->name,
        ]);
    }

    /**
     * Détache un tag d'un média.
     */
    public function destroy(MediaItem $item, Tag $tag): JsonResponse
    {
        $this->authorize('manage', $item);

        $item->tags()->detach($tag->id);

        // Supprimer le tag s'il n'est plus utilisé
        if ($tag->items()->count() === 0) {
            $tag->delete();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Autocomplete : retourne les tags existants contenant la chaîne.
     */
    public function suggest(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));

        $tags = Tag::when(strlen($q) >= 1, fn ($query) => $query->where('name', 'like', '%'.$q.'%'))
            ->orderBy('name')
            ->limit(15)
            ->pluck('name');

        return response()->json($tags);
    }
}
