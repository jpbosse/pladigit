{{--
    Bandeau SSL — affiché uniquement :
    - à l'utilisateur de rôle 'admin'
    - quand le certificat est auto-signé (ssl_type = 'self_signed')
    - ou quand APP_URL est en https mais que le cert letsencrypt est absent

    Ce partial est inclus dans layouts/app.blade.php juste avant @yield('content').
--}}
@auth
@php
    $sslBannerVisible = false;
    $sslTenant = app(\App\Services\TenantManager::class)->current();
    $sslUser   = Auth::user();

    if ($sslTenant && \App\Enums\UserRole::tryFrom($sslUser?->role ?? '') === \App\Enums\UserRole::ADMIN) {
        // Certificat auto-signé en base, ou Let's Encrypt absent alors qu'on est en HTTPS
        $sslBannerVisible = ($sslTenant->ssl_type === 'self_signed')
            || ($sslTenant->ssl_type === 'none'
                && str_starts_with(config('app.url'), 'https://')
                && ! file_exists('/etc/letsencrypt/live/' . $sslTenant->slug . '.' . parse_url(config('app.url'), PHP_URL_HOST) . '/fullchain.pem'));

        // Enregistrer la première connexion admin (déclencheur J+7)
        if ($sslBannerVisible && is_null($sslTenant->ssl_requested_at)) {
            $sslTenant->update(['ssl_requested_at' => now()]);
        }
    }
@endphp

@if($sslBannerVisible)
<div id="ssl-banner"
     style="margin:16px 20px 0;background:#fff7ed;border:2px solid #fb923c;border-radius:12px;padding:20px 24px;position:relative;">

    <div style="display:flex;align-items:flex-start;gap:16px;">

        {{-- Icône --}}
        <div style="font-size:32px;flex-shrink:0;line-height:1;">🔓</div>

        <div style="flex:1;">
            <div style="font-size:15px;font-weight:700;color:#c2410c;margin-bottom:6px;">
                Votre plateforme n'est pas encore sécurisée (HTTPS non activé)
            </div>
            <p style="font-size:13px;color:#9a3412;line-height:1.7;margin:0 0 14px;">
                Actuellement, les connexions à cette plateforme <strong>ne sont pas chiffrées</strong>.
                Cela signifie que les mots de passe et les documents échangés pourraient être
                interceptés sur le réseau.<br>
                <strong>Pour corriger cela, contactez votre administrateur technique (Super Admin)
                afin qu'il active le certificat HTTPS.</strong>
            </p>

            @php
                $sslAttempts = $sslTenant->ssl_attempt_count ?? 0;
                $sslRequestedAt = $sslTenant->ssl_requested_at;
                $sslDaysElapsed = $sslRequestedAt ? now()->diffInDays($sslRequestedAt) : 0;
                $sslNextAttempt = $sslRequestedAt
                    ? $sslRequestedAt->addDays(7 * ($sslAttempts + 1))->format('d/m/Y')
                    : null;
            @endphp

            @if($sslNextAttempt && $sslAttempts < 3)
            <div style="font-size:12px;color:#78350f;background:#fed7aa;border-radius:6px;padding:8px 12px;margin-bottom:14px;display:inline-block;">
                ⏳ Tentative automatique prévue le <strong>{{ $sslNextAttempt }}</strong>
                (si le domaine est correctement configuré)
            </div>
            @endif

            {{-- Bouton → envoie email au super-admin --}}
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <button onclick="requestSslFromSuperAdmin(this)"
                        style="background:#ea580c;color:#fff;border:none;border-radius:8px;
                               padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;
                               display:flex;align-items:center;gap:8px;">
                    <span>📧</span>
                    <span>Demander l'activation HTTPS à l'administrateur</span>
                </button>
                <span id="ssl-request-feedback" style="font-size:12px;color:#15803d;display:none;">
                    ✅ Demande envoyée à l'administrateur.
                </span>
            </div>
        </div>
    </div>
</div>

<script>
function requestSslFromSuperAdmin(btn) {
    btn.disabled = true;
    btn.style.opacity = '0.6';
    fetch('/tenant/ssl/request', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        const fb = document.getElementById('ssl-request-feedback');
        if (data.ok) {
            fb.textContent = '✅ ' + (data.message || 'Demande envoyée à l\'administrateur.');
            fb.style.color = '#15803d';
        } else {
            fb.textContent = '⚠️ ' + (data.message || 'Erreur lors de l\'envoi.');
            fb.style.color = '#b91c1c';
            btn.disabled = false;
            btn.style.opacity = '1';
        }
        fb.style.display = 'inline';
    })
    .catch(() => {
        btn.disabled = false;
        btn.style.opacity = '1';
    });
}
</script>
@endif
@endauth
