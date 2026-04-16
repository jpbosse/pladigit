<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valide que la requête WOPI porte un access_token non vide.
 *
 * Le tenant est maintenant dans le chemin de la route ({tenant}), et
 * l'access_token est un token brut. La validation du format complet
 * (organisation + token) est déléguée à WopiController::resolveToken().
 * Appliqué sur toutes les routes du groupe wopi.*.
 */
class ValidateWopiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->query('access_token', '');

        if ($token === '') {
            return response()->json(['error' => 'Invalid access_token'], 401);
        }

        return $next($request);
    }
}
