# ADR-034 — Mécanisme de mise à jour depuis le Super Admin

**Date :** Mai 2026  
**Statut :** Accepté  
**Auteur :** Jean-Pierre Bossé

---

## Contexte

Pladigit est déployé chez des collectivités sans informaticien dédié. Les mises à jour doivent être déclenchables sans accès SSH, directement depuis l'interface Super Admin, en une seule action.

La mise à jour concerne l'ensemble de l'instance — code, dépendances, migrations, assets — et s'applique à tous les tenants simultanément puisque l'architecture est mono-instance multi-tenant.

---

## Décision

Implémenter un mécanisme de mise à jour déclenché depuis le Super Admin, exécuté via un script shell sécurisé avec les droits nécessaires (pattern sudoers, identique à ADR-031).

---

## Périmètre

La mise à jour est **globale** — elle s'applique à l'instance entière et donc à tous les tenants en une seule opération. Il n'existe pas de mise à jour par tenant.

Seul le **Super Admin** peut déclencher une mise à jour.

---

## Séquence d'exécution

```
Super Admin clique "Mettre à jour"
    └── Vérification version GitHub disponible
    └── Confirmation avec numéro de version cible
    └── Activation mode maintenance (toutes organisations)
    └── Script update.sh (via sudo, identique au pattern install-collabora.sh)
            ├── git pull origin main
            ├── composer install --no-dev --optimize-autoloader
            ├── npm ci && npm run build
            ├── php artisan migrate --force          ← toutes les bases tenant
            ├── php artisan config:cache
            ├── php artisan route:cache
            ├── php artisan view:cache
            └── php artisan queue:restart
    └── Désactivation mode maintenance
    └── Log de la mise à jour (version, date, durée, statut)
```

---

## Gestion des erreurs

- Si `git pull` échoue (pas de réseau, conflit) → arrêt immédiat, mode maintenance levé, log d'erreur
- Si `composer install` échoue → arrêt, rollback impossible mais maintenance levée, log d'erreur
- Si une migration échoue → arrêt, maintenance conservée, log d'erreur avec stacktrace — intervention SSH requise
- Le Super Admin voit le statut en temps réel via polling (même pattern que le wizard d'installation)

---

## Migrations multi-tenant

Les migrations tenant sont appliquées sur **toutes les bases tenant existantes** en séquence, dans le même script. Un échec sur une base spécifique est loggé sans bloquer les autres — la base concernée est signalée dans le rapport de mise à jour.

---

## Sécurité

- Déclenchement réservé au Super Admin authentifié (session + IP restriction ADR-027)
- Exécution via sudoers sans mot de passe, script path fixe (identique à ADR-031)
- Log horodaté conservé dans `storage/logs/updates/`
- La version courante est affichée dans le Super Admin (config('app.version'))
- La version disponible sur GitHub est vérifiée via l'API GitHub avant de proposer la mise à jour

---

## Interface Super Admin

- Page dédiée "Mises à jour" dans le Super Admin
- Affichage version courante vs version disponible
- Bouton "Mettre à jour vers vX.Y.Z" avec confirmation
- Log en temps réel pendant l'exécution (polling SSE ou simple refresh)
- Historique des mises à jour précédentes

---

## Ce qui est hors périmètre

- Rollback automatique — trop complexe, une migration ne se défait pas facilement
- Mise à jour partielle par tenant — non pertinent, architecture mono-instance
- Mise à jour automatique planifiée — décision humaine obligatoire pour une collectivité

---

## Conséquences

- Un script `update.sh` est créé dans `install/`
- Une règle sudoers est ajoutée pour `www-data` sur ce script (même pattern que `install-collabora.sh`)
- Une page "Mises à jour" est ajoutée au Super Admin
- La checklist de mise en production est complétée avec la procédure de mise à jour
- Ce mécanisme remplace définitivement la nécessité d'un accès SSH pour les mises à jour courantes
