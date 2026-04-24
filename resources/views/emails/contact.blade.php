<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 32px 0; }
        .wrapper { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1e3a5f; padding: 28px 32px; }
        .header h1 { margin: 0; color: #fff; font-size: 18px; font-weight: 600; }
        .header p { margin: 4px 0 0; color: rgba(255,255,255,.6); font-size: 13px; }
        .body { padding: 32px; }
        .field { margin-bottom: 20px; }
        .label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #9ca3af; margin-bottom: 4px; }
        .value { font-size: 15px; color: #111827; }
        .message-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px 16px; font-size: 14px; color: #374151; line-height: 1.6; white-space: pre-wrap; }
        .plan-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .plan-partenaire { background: rgba(59,130,246,.12); color: #3b82f6; }
        .plan-autre { background: rgba(107,114,128,.12); color: #6b7280; }
        .footer { padding: 20px 32px; border-top: 1px solid #f3f4f6; font-size: 12px; color: #9ca3af; }
        .reply-hint { background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 14px; font-size: 13px; color: #92400e; margin-top: 24px; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Nouvelle demande de démo</h1>
        <p>Reçue via le formulaire pladigit.fr</p>
    </div>
    <div class="body">

        <div class="field">
            <div class="label">Contact</div>
            <div class="value">{{ $firstName }} {{ $lastName }}</div>
        </div>

        <div class="field">
            <div class="label">Organisation</div>
            <div class="value">{{ $organization }}</div>
        </div>

        <div class="field">
            <div class="label">Email</div>
            <div class="value"><a href="mailto:{{ $email }}" style="color:#1e3a5f;">{{ $email }}</a></div>
        </div>

        <div class="field">
            <div class="label">Plan souhaité</div>
            <div class="value">
                @if($plan === 'partenaire')
                    <span class="plan-badge plan-partenaire">{{ ucfirst($plan) }}</span>
                @else
                    <span class="plan-badge plan-autre">{{ ucfirst($plan ?: 'Non précisé') }}</span>
                @endif
            </div>
        </div>

        @if($messageText)
        <div class="field">
            <div class="label">Message</div>
            <div class="message-box">{{ $messageText }}</div>
        </div>
        @endif

        <div class="reply-hint">
            Pour répondre directement à {{ $firstName }}, utilisez Répondre — l'email est pré-rempli avec {{ $email }}.
        </div>

    </div>
    <div class="footer">
        Pladigit · Les Bézots · Soullans 85 · <a href="https://pladigit.fr" style="color:#9ca3af;">pladigit.fr</a>
    </div>
</div>
</body>
</html>
