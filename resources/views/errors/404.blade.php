<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page introuvable · Pladigit</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #F4F6F9; color: #334455; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 48px; text-align: center; max-width: 480px; width: 100%; }
        .code { font-size: 72px; font-weight: 800; color: #2D7DD2; line-height: 1; }
        h1 { font-size: 22px; font-weight: 700; color: #0F2A4A; margin: 16px 0 8px; }
        p { font-size: 15px; color: #8899AA; line-height: 1.6; margin-bottom: 28px; }
        a { display: inline-block; padding: 10px 28px; background: #1E3A5F; color: #fff; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; }
        a:hover { background: #2D7DD2; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">404</div>
        <h1>Page introuvable</h1>
        <p>La page que vous recherchez n'existe pas ou a été déplacée.</p>
        <a href="{{ url('/') }}">← Retour à l'accueil</a>
    </div>
</body>
</html>
