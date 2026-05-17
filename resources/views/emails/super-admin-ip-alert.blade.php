<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerte sécurité Pladigit</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding: 32px 16px; }
        .card { background: #fff; border-radius: 12px; max-width: 520px; margin: 0 auto; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .logo { color: #1E3A5F; font-size: 22px; font-weight: 700; margin-bottom: 32px; }
        .alert-banner { background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; }
        .alert-banner h1 { color: #991B1B; font-size: 18px; margin: 0 0 8px; }
        .alert-banner p { color: #7F1D1D; font-size: 14px; margin: 0; line-height: 1.5; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .info-table tr { border-bottom: 1px solid #e5e7eb; }
        .info-table tr:last-child { border: none; }
        .info-table td { padding: 10px 4px; font-size: 14px; vertical-align: top; }
        .info-table td:first-child { color: #6b7280; width: 40%; font-weight: 600; }
        .info-table td:last-child { color: #111827; word-break: break-all; }
        .action-box { background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; }
        .action-box p { color: #92400E; font-size: 14px; margin: 0; line-height: 1.6; }
        .action-box strong { color: #78350F; }
        hr { border: none; border-top: 1px solid #e5e7eb; margin: 24px 0; }
        .footer { font-size: 12px; color: #9ca3af; text-align: center; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">Pladigit</div>

        <div class="alert-banner">
            <h1>⚠️ Tentative d'accès non autorisée</h1>
            <p>Une tentative de connexion au panneau Super Admin a été détectée depuis une adresse IP non autorisée.</p>
        </div>

        <table class="info-table">
            <tr>
                <td>Date et heure</td>
                <td>{{ $detectedAt }}</td>
            </tr>
            <tr>
                <td>Adresse IP</td>
                <td><code>{{ $ip }}</code></td>
            </tr>
            <tr>
                <td>URL ciblée</td>
                <td><code>{{ $url }}</code></td>
            </tr>
            <tr>
                <td>Navigateur</td>
                <td>{{ $userAgent }}</td>
            </tr>
        </table>

        <div class="action-box">
            <p>
                <strong>Si vous êtes à l'origine de cette tentative :</strong>
                votre adresse IP a changé (connexion depuis un autre réseau). Ajoutez-la
                à la variable <code>SUPER_ADMIN_ALLOWED_IPS</code> dans votre fichier <code>.env</code>.
            </p>
            <br>
            <p>
                <strong>Si vous n'êtes pas à l'origine de cette tentative :</strong>
                quelqu'un cherche à accéder à votre panneau d'administration.
                Vérifiez les connexions SSH récentes (<code>last -20</code>) et
                consultez le plan de réponse à incident (ADR-041 §10).
            </p>
        </div>

        <hr>
        <p class="footer">
            Cet email a été envoyé automatiquement par Pladigit.<br>
            Il ne requiert pas de réponse.
        </p>
    </div>
</body>
</html>
