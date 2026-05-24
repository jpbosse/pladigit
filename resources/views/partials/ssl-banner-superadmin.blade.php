{{--
    Bandeau SSL Super Admin — affiché dans layouts/super-admin.blade.php
    quand le certificat principal (APP_URL) est auto-signé ou absent.
--}}
@php
    $saRootHost   = parse_url(config('app.url'), PHP_URL_HOST);
    $saCertExists = file_exists('/etc/letsencrypt/live/' . $saRootHost . '/fullchain.pem');
    $saSelfSigned = ! $saCertExists && str_starts_with(config('app.url'), 'https://');
    // On affiche aussi si on est encore en HTTP
    $saIsHttp     = str_starts_with(config('app.url'), 'http://');
    $saBannerShow = ! $saCertExists;
@endphp

@if($saBannerShow)
<div id="sa-ssl-banner"
     style="background:#fff7ed;border-bottom:2px solid #fb923c;padding:14px 24px;
            display:flex;align-items:center;gap:16px;flex-wrap:wrap;">

    <div style="font-size:22px;flex-shrink:0;">🔓</div>

    <div style="flex:1;min-width:280px;">
        <div style="font-size:13px;font-weight:700;color:#c2410c;margin-bottom:2px;">
            HTTPS non activé sur ce serveur
        </div>
        <div style="font-size:12px;color:#9a3412;">
            Le certificat Let's Encrypt pour <strong>{{ $saRootHost }}</strong>
            n'est pas encore actif. Les connexions ne sont pas chiffrées.
        </div>
    </div>

    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <button onclick="activateSslSuperAdmin(this)"
                id="sa-ssl-btn"
                style="background:#ea580c;color:#fff;border:none;border-radius:8px;
                       padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer;
                       white-space:nowrap;">
            🔒 Activer HTTPS maintenant
        </button>
        <span id="sa-ssl-result" style="font-size:12px;display:none;"></span>
    </div>
</div>

<script>
function activateSslSuperAdmin(btn) {
    btn.disabled = true;
    btn.textContent = '⏳ Activation en cours...';
    btn.style.opacity = '0.7';

    const result = document.getElementById('sa-ssl-result');
    result.style.display = 'inline';
    result.style.color = '#6b7280';
    result.textContent = 'Contacting Let\'s Encrypt...';

    fetch('/super-admin/ssl/activate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            result.textContent = '✅ ' + (data.message || 'HTTPS activé ! Rechargement...');
            result.style.color = '#15803d';
            document.getElementById('sa-ssl-banner').style.background = '#f0fdf4';
            document.getElementById('sa-ssl-banner').style.borderColor = '#86efac';
            setTimeout(() => location.reload(), 2500);
        } else {
            result.textContent = '❌ ' + (data.message || 'Échec. Voir le journal.');
            result.style.color = '#b91c1c';
            btn.disabled = false;
            btn.textContent = '🔒 Réessayer';
            btn.style.opacity = '1';
        }
    })
    .catch(() => {
        result.textContent = '❌ Erreur réseau.';
        result.style.color = '#b91c1c';
        btn.disabled = false;
        btn.textContent = '🔒 Réessayer';
        btn.style.opacity = '1';
    });
}
</script>
@endif
