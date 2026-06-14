# Annexe B — Architecture multi-tenant

## B.1 — Modèle retenu : base dédiée par organisation
Chaque organisation (tenant) dispose d'une base MySQL dédiée nommée pladigit_{slug} (ex: pladigit_mairie_soullans). La base centrale pladigit contient uniquement la table organizations avec les credentials de connexion à chaque base dédiée.

## B.2 — Justification du choix

Contrepartie : le nombre de bases MySQL croît avec le nombre de clients. Géré par le TenantManager qui configure dynamiquement la connexion à chaque requête, de façon totalement transparente.

## B.3 — TenantManager — Fonctionnement
- Le middleware "tenant" détecte l'organisation via le sous-domaine (ex: soullans.pladigit.fr).
- Il charge la configuration de connexion depuis la table organizations.
- Il configure dynamiquement la connexion Laravel "tenant" vers la bonne base.
- Toutes les requêtes Eloquent utilisant $connection = "tenant" sont automatiquement isolées.

## B.4 — Création d'un nouveau tenant
- Super Admin crée l'organisation via l'interface /super-admin.
- TenantManager crée la base MySQL pladigit_{slug}.
- Migrations tenant exécutées automatiquement sur la nouvelle base.
- Compte Admin Organisation créé avec invitation par email (token 72h).

## B.5 — Garanties d'isolation
- Un utilisateur authentifié sur le tenant A ne peut jamais accéder au tenant B.
- Les sessions sont liées au slug du tenant — pas de session cross-tenant.
- Les logs d'audit sont dans la base du tenant concerné.
- Le branding (logo, couleurs) est isolé par tenant dans tenant_settings.

## B.6 — Schéma de données utilisateur (Mars 2026)