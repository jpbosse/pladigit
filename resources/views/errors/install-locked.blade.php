<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pladigit — Installation déjà effectuée</title>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;background:#F4F6F9;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem}
        .box{background:#fff;border-radius:10px;padding:2.5rem;max-width:520px;width:100%;text-align:center;border:1px solid rgba(30,58,95,.1);box-shadow:0 4px 16px rgba(30,58,95,.08)}
        .icon{font-size:3rem;margin-bottom:1.25rem}
        h1{color:#1E3A5F;font-size:1.35rem;margin-bottom:.75rem;font-family:serif}
        p{color:#6B7A8D;font-size:.9rem;line-height:1.6;margin-bottom:1.25rem}
        .warn{background:#FEF3C7;border:1px solid #FDE68A;border-radius:6px;padding:.875rem 1rem;font-size:.82rem;color:#92400E;margin-bottom:1.5rem;text-align:left;line-height:1.6}
        .btn{display:inline-block;background:#1E3A5F;color:#fff;padding:.7rem 1.75rem;border-radius:6px;text-decoration:none;font-weight:700;font-size:.875rem;transition:background .2s}
        .btn:hover{background:#162D4A}
        code{background:#F4F6F9;padding:.15rem .4rem;border-radius:3px;font-size:.8rem;color:#1E3A5F}
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">🔒</div>
        <h1>Pladigit est déjà installé sur ce serveur</h1>
        <p>
            L'assistant d'installation a déjà été utilisé. Pour protéger votre installation,
            le téléchargement du wizard est bloqué sur ce serveur.
        </p>
        <div class="warn">
            <strong>⚠️ Vous souhaitez réinstaller ?</strong><br><br>
            Supprimez manuellement le fichier <code>install/.lock</code> sur votre serveur,
            puis rechargez cette page.<br><br>
            <strong>Attention :</strong> cette opération réécrit votre fichier <code>.env</code>.
            Vos données ne seront pas supprimées mais votre configuration sera réinitialisée.
        </div>
        <a href="/" class="btn">← Retourner à l'accueil</a>
    </div>
</body>
</html>
