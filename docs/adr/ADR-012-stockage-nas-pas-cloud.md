# ADR-012 — Stockage médias sur NAS local (pas S3/cloud)

**Date :** Février 2026
**Statut :** Accepté

## Contexte

Les organisations clientes (collectivités, mairies, déchetteries) disposent toutes d'un NAS existant sur leur réseau local. Leurs agents copient déjà leurs photos directement sur ce NAS depuis leurs appareils. Migrer vers un stockage cloud (S3, Azure Blob) impliquerait une rupture d'usage importante et des coûts récurrents.

## Décision

Pladigit accède aux fichiers via une abstraction `NasConnectorInterface` qui supporte trois drivers : `local` (dossier local ou monté), `sftp` (connexion SSH) et `smb` (partage réseau Windows/Samba). Le driver est configuré par tenant dans `TenantSettings`. Les credentials (mot de passe SFTP/SMB) sont chiffrés en base via `Crypt::encryptString()`.

Deux drivers séparés existent dès maintenant : `photoDriver()` pour la photothèque et `gedDriver()` pour la GED (Phase 6), permettant de connecter deux NAS différents si besoin.

## Conséquences

- **Avantage :** zéro migration de données pour le client, continuité des habitudes existantes, coût de stockage nul.
- **Avantage :** fonctionne hors connexion internet (LAN uniquement).
- **Contrainte :** Pladigit n'est pas la source de vérité — le NAS peut être modifié en dehors de l'application. La synchronisation bidirectionnelle (tâche planifiée + drag-and-drop futur) atténue ce risque.
- **Contrainte :** performances dépendantes du réseau local ; le streaming Range HTTP (ADR-015) compense pour les vidéos.
