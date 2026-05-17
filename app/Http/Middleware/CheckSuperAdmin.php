<?php

namespace App\Http\Middleware;

use App\Mail\SuperAdminIpAlertMail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class CheckSuperAdmin
{
    /**
     * Durée d'inactivité en secondes avant expiration de la session Super Admin.
     * 30 minutes — indépendant du SESSION_LIFETIME global (qui couvre les agents tenant).
     */
    private const INACTIVITY_TIMEOUT = 1800;

    public function handle(Request $request, Closure $next): mixed
    {
        // ── 1. Vérification IP — avant toute autre chose ──────────────────────
        $allowedIps = config('superadmin.allowed_ips', ['127.0.0.1', '::1']);

        if (! in_array($request->ip(), $allowedIps, true)) {
            $this->handleUnauthorizedIp($request);
            abort(403, 'Accès refusé — adresse IP non autorisée.');
        }

        // ── 2. Rate limiting — 10 tentatives par minute par IP ────────────────
        $key = 'super-admin:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            abort(429, "Trop de tentatives. Réessayez dans {$seconds} secondes.");
        }
        RateLimiter::hit($key, 60);

        // ── 3. Vérification session ───────────────────────────────────────────
        $email = session('super_admin_email');
        $verified = session('super_admin_verified');

        if (! $verified || $email !== config('superadmin.email')) {
            return redirect()->route('super-admin.login');
        }

        // ── 4. Timeout d'inactivité — 30 minutes ─────────────────────────────
        $lastActivity = session('super_admin_last_activity');

        if ($lastActivity && (time() - $lastActivity) > self::INACTIVITY_TIMEOUT) {
            $request->session()->forget([
                'super_admin_email',
                'super_admin_verified',
                'super_admin_last_activity',
            ]);

            return redirect()
                ->route('super-admin.login')
                ->withErrors(['session' => 'Session expirée après 30 minutes d\'inactivité.']);
        }

        // Mettre à jour le timestamp d'activité à chaque requête
        session(['super_admin_last_activity' => time()]);

        RateLimiter::clear($key);

        return $next($request);
    }

    // =========================================================================

    /**
     * Log structuré + alerte email lors d'une tentative depuis IP non autorisée.
     * Envoi synchrone (pas de queue) pour garantir la réception même si les
     * workers sont arrêtés ou compromis.
     */
    private function handleUnauthorizedIp(Request $request): void
    {
        $context = [
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'user_agent' => $request->userAgent() ?? 'inconnu',
            'detected_at' => now()->format('d/m/Y H:i:s'),
        ];

        Log::warning('Super Admin : tentative d\'accès depuis IP non autorisée.', $context);

        $superAdminEmail = config('superadmin.email');

        if (! $superAdminEmail) {
            return;
        }

        // Vérifier que le mailer est configuré avant d'essayer d'envoyer
        if (empty(config('mail.mailers.smtp.host')) && config('mail.default') === 'smtp') {
            Log::warning('Super Admin IP alert : mailer SMTP non configuré — email non envoyé.', [
                'ip' => $request->ip(),
            ]);

            return;
        }

        try {
            Mail::to($superAdminEmail)->send(new SuperAdminIpAlertMail(
                ip: $context['ip'],
                userAgent: $context['user_agent'],
                url: $context['url'],
                detectedAt: $context['detected_at'],
            ));
        } catch (\Throwable $e) {
            // Ne jamais bloquer le abort(403) à cause d'un échec d'envoi mail
            Log::error('Super Admin IP alert : échec envoi email.', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
        }
    }
}
