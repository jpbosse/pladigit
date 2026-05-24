<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Demande HTTPS</title></head>
<body style="font-family:sans-serif;background:#f9fafb;padding:32px;">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
    <div style="font-size:40px;text-align:center;margin-bottom:16px;">🔒</div>
    <h1 style="font-size:20px;color:#1f2937;text-align:center;margin:0 0 24px;">Demande d'activation HTTPS</h1>

    <p style="color:#374151;line-height:1.6;">
        L'administrateur de l'organisation <strong>{{ $organization->name }}</strong>
        demande l'activation du certificat HTTPS pour sa plateforme.
    </p>

    <div style="background:#fff7ed;border:1px solid #fb923c;border-radius:8px;padding:16px;margin:20px 0;">
        <div style="font-size:13px;color:#9a3412;line-height:1.8;">
            🏛️ Organisation : <strong>{{ $organization->name }}</strong><br>
            🌐 Domaine : <strong>{{ $organization->slug }}.{{ parse_url(config('app.url'), PHP_URL_HOST) }}</strong><br>
            👤 Demandé par : <strong>{{ $requestedBy->name }} ({{ $requestedBy->email }})</strong><br>
            📅 Le : <strong>{{ now()->format('d/m/Y à H:i') }}</strong>
        </div>
    </div>

    <p style="color:#374151;line-height:1.6;font-weight:600;">
        Action requise — connectez-vous au Super Admin et activez HTTPS depuis la fiche de cette organisation.
    </p>

    <div style="text-align:center;margin:24px 0;">
        <a href="{{ config('app.url') }}/super-admin/organizations"
           style="background:#7B1C1C;color:#fff;text-decoration:none;padding:12px 28px;
                  border-radius:8px;font-size:14px;font-weight:600;display:inline-block;">
            Ouvrir le Super Admin
        </a>
    </div>

    <p style="font-size:12px;color:#6b7280;line-height:1.6;">
        Ou lancez manuellement sur le serveur :<br>
        <code style="font-size:11px;background:#f3f4f6;padding:4px 8px;border-radius:4px;display:block;margin-top:6px;word-break:break-all;">
            sudo certbot --nginx -d {{ $organization->slug }}.{{ parse_url(config('app.url'), PHP_URL_HOST) }} --non-interactive --agree-tos --email {{ config('superadmin.email') }} --redirect
        </code>
    </p>

    <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
    <p style="font-size:12px;color:#9ca3af;text-align:center;">Pladigit — Plateforme de digitalisation des collectivités</p>
</div>
</body>
</html>
