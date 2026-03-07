<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Sauvegarde les préférences d'affichage de la photothèque pour l'utilisateur courant.
 */
class MediaPreferenceController extends Controller
{
    /**
     * Sauvegarde le nombre de colonnes choisi par l'utilisateur.
     * Appelé en AJAX depuis la galerie album.
     */
    public function setCols(Request $request)
    {
        $request->validate([
            'cols' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        /** @var \App\Models\Tenant\User $user */
        $user = auth()->user();
        $user->update(['media_cols' => (int) $request->cols]);

        return response()->json(['ok' => true]);
    }
}
