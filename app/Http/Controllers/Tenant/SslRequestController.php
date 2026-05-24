<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Mail\SslRequestMail;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class SslRequestController extends Controller
{
    public function __construct(
        private TenantManager $tenantManager,
    ) {}

    /**
     * L'admin tenant demande l'activation HTTPS au super-admin.
     * Envoie un email au super-admin avec les infos de l'organisation.
     */
    public function request(): JsonResponse
    {
        $org = $this->tenantManager->current();
        $user = Auth::user();

        if (! $org || ! $user) {
            return response()->json(['ok' => false, 'message' => 'Session invalide.'], 401);
        }

        $superAdminEmail = config('superadmin.email');
        if (! $superAdminEmail) {
            return response()->json([
                'ok' => false,
                'message' => 'Aucune adresse Super Admin configurée. Contactez votre prestataire directement.',
            ]);
        }

        try {
            Mail::to($superAdminEmail)->send(new SslRequestMail($org, $user));

            return response()->json([
                'ok' => true,
                'message' => 'Demande envoyée à l\'administrateur. Il recevra un email sous peu.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Impossible d\'envoyer l\'email. Contactez votre administrateur directement.',
            ]);
        }
    }
}
