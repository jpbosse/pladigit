<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Échec HTTPS</title></head>
<body style="font-family:sans-serif;background:#f9fafb;padding:32px;">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
    <div style="font-size:40px;text-align:center;margin-bottom:16px;">⚠️</div>
    <h1 style="font-size:20px;color:#b91c1c;text-align:center;margin:0 0 24px;">Échec d'activation HTTPS</h1>
    <p style="color:#374151;line-height:1.6;">
        La tentative automatique d'activation HTTPS pour <strong>{{ $organization->name }}</strong>
        a échoué (tentative n°{{ $organization->ssl_attempt_count }}/3).
    </p>
    <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:16px;margin:20px 0;">
        <div style="font-size:13px;color:#b91c1c;">
            🌐 Domaine : <strong>{{ $domain }}</strong><br>
            📅 Tentative le : {{ now()->format('d/m/Y à H:i') }}
        </div>
    </div>
    @if($organization->ssl_attempt_count < 3)
    <p style="color:#374151;line-height:1.6;">
        Une nouvelle tentative automatique aura lieu dans 7 jours.
    </p>
    @else
    <p style="color:#b91c1c;font-weight:600;">
        ⚠️ Nombre maximum de tentatives atteint. Une intervention manuelle est requise.
    </p>
    @endif
    <div style="background:#f9fafb;border-radius:8px;padding:12px;margin:16px 0;">
        <p style="font-size:12px;color:#6b7280;margin:0 0 8px;font-weight:600;">Détail de l'erreur :</p>
        <code style="font-size:11px;color:#374151;word-break:break-all;">{{ Str::limit($errorDetails, 300) }}</code>
    </div>
    <p style="font-size:13px;color:#6b7280;">
        Vérifiez que le DNS du domaine pointe bien sur ce serveur, puis lancez manuellement :<br>
        <code style="font-size:12px;background:#f3f4f6;padding:2px 6px;border-radius:4px;">
            sudo certbot --nginx -d {{ $domain }} --non-interactive --agree-tos --email {{ config('superadmin.email') }}
        </code>
    </p>
    <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
    <p style="font-size:12px;color:#9ca3af;text-align:center;">Pladigit — Plateforme de digitalisation des collectivités</p>
</div>
</body>
</html>
