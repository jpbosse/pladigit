<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politique de confidentialité — Pladigit</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', system-ui, sans-serif; background: #f8fafc; color: #1f2937; line-height: 1.7; }
        .container { max-width: 760px; margin: 48px auto; padding: 0 24px 64px; }
        .back { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #6b7280; text-decoration: none; margin-bottom: 32px; }
        .back:hover { color: #1E3A5F; }
        h1 { font-size: 26px; font-weight: 700; color: #1E3A5F; margin-bottom: 8px; }
        .subtitle { font-size: 13px; color: #6b7280; margin-bottom: 40px; }
        h2 { font-size: 16px; font-weight: 600; color: #1f2937; margin: 32px 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e5e7eb; }
        p { font-size: 14px; color: #374151; margin-bottom: 10px; }
        ul { font-size: 14px; color: #374151; margin: 8px 0 10px 20px; }
        li { margin-bottom: 4px; }
        a { color: #1E3A5F; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px; }
        .badge { display: inline-block; background: #f0fdf4; color: #15803d; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 999px; margin-bottom: 24px; }
        .highlight { background: #eff6ff; border-left: 3px solid #1E3A5F; padding: 12px 16px; border-radius: 0 8px 8px 0; margin: 16px 0; font-size: 13px; color: #1d4ed8; }
    </style>
</head>
<body>
<div class="container">

    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}" class="back">
        ← Retour
    </a>

    <div class="card">
        <span class="badge">RGPD conforme</span>
        <h1>Politique de confidentialité</h1>
        <p class="subtitle">Dernière mise à jour : {{ date('d/m/Y') }}</p>

        <div class="highlight">
            Pladigit est une plateforme auto-hébergée. Vos données restent sur vos propres serveurs
            et ne transitent jamais vers des services tiers ou des clouds propriétaires.
        </div>

        <h2>Responsable du traitement</h2>
        <p><strong>Les Bézots</strong> — Soullans (85300), France<br>
        Email : <a href="mailto:contact@lesbezots.fr">contact@lesbezots.fr</a></p>

        <h2>Données collectées</h2>
        <p>Dans le cadre de l'utilisation de Pladigit, les données suivantes sont collectées :</p>
        <ul>
            <li>Nom, adresse email (compte utilisateur)</li>
            <li>Adresse IP et logs de connexion (sécurité)</li>
            <li>Fichiers uploadés (photos, documents) — stockés sur votre NAS</li>
            <li>Métadonnées EXIF des photos (date de prise, géolocalisation si présente)</li>
            <li>Journal des actions (audit log) — conservé 12 mois</li>
        </ul>

        <h2>Base légale du traitement</h2>
        <ul>
            <li><strong>Exécution du contrat</strong> — gestion des comptes utilisateurs</li>
            <li><strong>Intérêt légitime</strong> — sécurité, logs d'audit</li>
            <li><strong>Consentement</strong> — activation du 2FA, géolocalisation EXIF</li>
        </ul>

        <h2>Durée de conservation</h2>
        <ul>
            <li>Comptes utilisateurs : durée d'utilisation de la plateforme</li>
            <li>Logs d'audit : 12 mois (configurable par organisation)</li>
            <li>Sessions : selon la durée configurée par l'organisation</li>
            <li>Fichiers média : jusqu'à suppression par l'utilisateur</li>
        </ul>

        <h2>Vos droits (RGPD)</h2>
        <p>Conformément au Règlement Général sur la Protection des Données (RGPD), vous disposez des droits suivants :</p>
        <ul>
            <li><strong>Droit d'accès</strong> — export de vos données personnelles en JSON</li>
            <li><strong>Droit de rectification</strong> — modification via votre profil</li>
            <li><strong>Droit à l'effacement</strong> — désactivation + anonymisation des logs</li>
            <li><strong>Droit à la portabilité</strong> — export JSON disponible sur demande</li>
            <li><strong>Droit d'opposition</strong> — contactez votre administrateur</li>
        </ul>
        <p>Pour exercer vos droits : <a href="mailto:contact@lesbezots.fr">contact@lesbezots.fr</a></p>

        <h2>Sécurité des données</h2>
        <ul>
            <li>Mots de passe hachés bcrypt (coût 12)</li>
            <li>Secrets TOTP et clés LDAP chiffrés AES-256</li>
            <li>Sessions protégées CSRF, régénérées après authentification</li>
            <li>Hébergement en France — aucune donnée hors UE</li>
            <li>HTTPS obligatoire (Let's Encrypt)</li>
        </ul>

        <h2>Cookies</h2>
        <p>Pladigit utilise uniquement des cookies de session strictement nécessaires au fonctionnement
        de la plateforme. Aucun cookie publicitaire ou de traçage tiers n'est utilisé.</p>

        <h2>Sous-traitants</h2>
        <p>Pladigit n'utilise aucun service tiers pour le traitement des données.
        La plateforme est intégralement auto-hébergée sur vos propres serveurs.</p>

        <h2>Notification CNIL</h2>
        <p>En cas de violation de données, les personnes concernées et la CNIL seront notifiées
        dans un délai de 72 heures conformément à l'article 33 du RGPD.</p>

        <h2>Contact DPO</h2>
        <p>Pour toute question relative à vos données personnelles :<br>
        <a href="mailto:contact@lesbezots.fr">contact@lesbezots.fr</a></p>
    </div>

</div>
</body>
</html>
