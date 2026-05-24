<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>HTTPS activé</title></head>
<body style="font-family:sans-serif;background:#f9fafb;padding:32px;">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;border:1px solid #e5e7eb;">
    <div style="font-size:40px;text-align:center;margin-bottom:16px;">✅</div>
    <h1 style="font-size:20px;color:#15803d;text-align:center;margin:0 0 24px;">HTTPS activé automatiquement</h1>
    <p style="color:#374151;line-height:1.6;">
        Le certificat HTTPS Let's Encrypt a été activé avec succès pour l'organisation
        <strong>{{ $organization->name }}</strong>.
    </p>
    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px;margin:20px 0;">
        <div style="font-size:13px;color:#15803d;">
            🔒 Domaine sécurisé : <strong>https://{{ $domain }}</strong><br>
            📅 Activé le : {{ now()->format('d/m/Y à H:i') }}
        </div>
    </div>
    <p style="font-size:13px;color:#6b7280;">
        Le renouvellement automatique est en place. Aucune action requise.
    </p>
    <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
    <p style="font-size:12px;color:#9ca3af;text-align:center;">Pladigit — Plateforme de digitalisation des collectivités</p>
</div>
</body>
</html>
