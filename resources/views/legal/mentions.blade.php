<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentions légales — Pladigit</title>
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
        a { color: #1E3A5F; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px; }
        .badge { display: inline-block; background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 999px; margin-bottom: 24px; }
    </style>
</head>
<body>
<div class="container">

    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}" class="back">
        ← Retour
    </a>

    <div class="card">
        <span class="badge">Document légal</span>
        <h1>Mentions légales</h1>
        <p class="subtitle">Dernière mise à jour : {{ date('d/m/Y') }}</p>

        <h2>Éditeur de la plateforme</h2>
        <p><strong>Les Bézots</strong><br>
        Association loi 1901<br>
        Soullans (85300), Vendée, France<br>
        Email : <a href="mailto:contact@lesbezots.fr">contact@lesbezots.fr</a></p>

        <h2>Responsable de la publication</h2>
        <p>Jean-Pierre Bossé<br>
        Email : <a href="mailto:contact@lesbezots.fr">contact@lesbezots.fr</a></p>

        <h2>Hébergement</h2>
        <p>La plateforme Pladigit est hébergée sur des serveurs situés en France (Union Européenne).<br>
        Aucune donnée n'est transmise en dehors de l'Union Européenne.</p>

        <h2>Logiciel</h2>
        <p>Pladigit est un logiciel libre distribué sous licence
        <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" rel="noopener">GNU AGPL-3.0</a>.<br>
        Le code source est disponible sur
        <a href="https://github.com/jpbosse/pladigit" target="_blank" rel="noopener">github.com/jpbosse/pladigit</a>.</p>
        <p>En vertu de la licence AGPL-3.0, toute organisation utilisant ce logiciel — y compris
        via un service réseau (mode SaaS) — est tenue de rendre disponible le code source,
        y compris ses éventuelles modifications.</p>

        <h2>Propriété intellectuelle</h2>
        <p>Le code source de Pladigit est publié sous licence AGPL-3.0. Les contenus uploadés
        par les utilisateurs (photos, documents) restent la propriété de leurs auteurs.</p>

        <h2>Données personnelles</h2>
        <p>Consulter notre <a href="{{ route('legal.confidentialite') }}">politique de confidentialité</a>
        pour les informations relatives au traitement des données personnelles.</p>

        <h2>Contact</h2>
        <p>Pour toute question : <a href="mailto:contact@lesbezots.fr">contact@lesbezots.fr</a></p>
    </div>

</div>
</body>
</html>
