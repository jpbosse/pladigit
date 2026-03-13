<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation Pladigit</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding: 32px 16px; }
        .card { background: #fff; border-radius: 12px; max-width: 520px; margin: 0 auto; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .logo { color: #1E3A5F; font-size: 22px; font-weight: 700; margin-bottom: 32px; }
        h1 { color: #1E3A5F; font-size: 20px; margin: 0 0 16px; }
        p { color: #4b5563; font-size: 15px; line-height: 1.6; margin: 0 0 16px; }
        .btn { display: inline-block; background: #1E3A5F; color: #fff !important; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 15px; margin: 8px 0 24px; }
        .info { background: #f9fafb; border-radius: 8px; padding: 16px; font-size: 13px; color: #6b7280; margin-bottom: 24px; }
        .info strong { color: #374151; }
        .expiry { color: #dc2626; font-size: 13px; margin-bottom: 24px; }
        .url { word-break: break-all; font-size: 12px; color: #9ca3af; }
        hr { border: none; border-top: 1px solid #e5e7eb; margin: 24px 0; }
        .footer { font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">Pladigit</div>

        <h1>Vous avez été invité(e) à rejoindre {{ $orgName }}</h1>

        <p>Bonjour <strong>{{ $user->name }}</strong>,</p>

        <p>
	   <strong>{{ $invitedByName }}</strong> vous a créé un compte sur la plateforme <strong>Pladigit</strong>.

        </p>

        <p>Cliquez sur le bouton ci-dessous pour choisir votre mot de passe et activer votre compte :</p>

        <a href="{{ $activationUrl }}" class="btn">Activer mon compte</a>

        <p class="expiry">⏳ Ce lien est valable <strong>72 heures</strong> et ne peut être utilisé qu'une seule fois.</p>

        <div class="info">
            <strong>Votre email de connexion :</strong> {{ $user->email }}<br>
            <strong>Organisation :</strong> {{ $orgName }}
        </div>

        <hr>

        <p style="font-size:13px; color:#6b7280;">
            Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :
        </p>
        <p class="url">{{ $activationUrl }}</p>

        <hr>

        <p class="footer">
            Vous recevez cet email car un administrateur de {{ $orgName }} a créé un compte à votre adresse.<br>
            Si vous n'attendiez pas cette invitation, ignorez simplement cet email.
        </p>
    </div>
</body>
</html>
