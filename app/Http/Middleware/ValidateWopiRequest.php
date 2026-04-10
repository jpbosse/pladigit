<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valide que la requête WOPI porte un access_token au format attendu.
 *
 * Format : "{org_slug}:{raw_token}" — rejeté en 401 si absent ou malformé.
 * Appliqué sur toutes les routes du groupe wopi.*.
 */
class ValidateWopiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->query('access_token', '');
        $pos = strpos($token, ':');

        if ($pos === false || $pos === 0 || $pos === strlen($token) - 1) {
            return response()->json(['error' => 'Invalid access_token'], 401);
        }

        return $next($request);
    }
}
