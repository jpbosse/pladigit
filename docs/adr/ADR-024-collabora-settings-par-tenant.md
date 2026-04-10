# ADR-024 — Configuration Collabora Online par tenant (TenantSettings)

**Date :** Avril 2026  
**Statut :** Accepté et livré

## Contexte

La configuration Collabora (URL, URL WOPI, TTL token) était stockée uniquement dans `.env` — globale à toute l'instance. Or dans un déploiement multi-tenant, certaines organisations pourraient avoir leur propre instance Collabora ou des TTL différents. De plus, l'Admin Organisation doit pouvoir configurer Collabora sans accès SSH au serveur.

## Décision

Ajouter les colonnes Collabora dans `tenant_settings` :
- `collabora_url` — URL de l'instance Collabora du tenant (surcharge `COLLABORA_URL` du `.env`)
- `wopi_url` — URL WOPI de base (surcharge `WOPI_URL` du `.env`)
- `collabora_token_ttl_minutes` — TTL du token en minutes (surcharge `COLLABORA_TOKEN_TTL`)

La résolution suit l'ordre de priorité : `tenant_settings` > `.env` > valeur par défaut.

Une page d'administration dédiée (Admin > GED > Collabora) permet à l'Admin Organisation de configurer ces valeurs et de tester la connexion via un ping sur `{collabora_url}/hosting/capabilities`.

## Conséquences

- Chaque tenant peut avoir sa propre instance Collabora ou partager celle de l'instance principale.
- L'Admin Organisation peut configurer et tester Collabora sans intervention du Super Admin ni accès serveur.
- Si `collabora_url` est vide dans `tenant_settings` ET dans `.env`, le bouton "Ouvrir dans Collabora" n'apparaît pas — pas d'erreur pour l'utilisateur.
- Cohérent avec le modèle de configuration tenant existant (NAS, LDAP, SMTP configurables par tenant).
