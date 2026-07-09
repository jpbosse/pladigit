# Checklist de test fonctionnel — Pladigit

> Campagne de test manuelle, hors authentification LDAP/Active Directory (non utilisée).

- `[x]` : Ca fonctionne.
- `[?]` : Il y un soucis. Voir les annotations.
- `[P]` : paramétre à revoir.
- `[ ]` : pas encore vue.

> Environnement de test : 
>> VM Ubuntu 26.04, domaine `pladigit.test`.

---

## 1. Installation & infrastructure

- [X] L'installeur va au bout sans erreur bloquante
- [X] Le check final indique « tout est OK »
- [X] Nginx démarré, écoute sur 80 et 443 — vérifié 2026-07-09 sur `pladigit.vm` (redirection 80→443 active, vhost sert bien l'app une fois le DNS local corrigé) et sur le VPS (`https://toto.pladigit.fr`, `https://titi.pladigit.fr` réellement servis, cf. tests `curl` isolation)
- [X] PHP-FPM 8.4 actif (et non 8.5)
- [X] Worker de queue `RUNNING` (les deux process), sans FATAL
- [X] Le watchdog relance le worker s'il tombe (test : `supervisorctl stop`, attendre 2 min)
- [X] Le cron Laravel (`schedule:run`) est actif
- [X] Certificat présent, HTTPS accessible — VPS confirmé (Let's Encrypt valide jusqu'au 30/08/2026, cf. `certbot certificates`) ; **mais voir 1.9 du plan de travail : ne couvre que `pladigit.fr` + `demo.pladigit.fr`, pas de wildcard réel malgré la doc et lancer à la main**

L'installation est à refaire et valider sans intervention humaine sur vm 22.04, 24.04 et 26.04 LTS.


## 2. Authentification & accès

- [X] Connexion super-admin
- [X] Restriction IP super-admin (`SUPER_ADMIN_ALLOWED_IPS`) effective
- [X] Déconnexion Superadmin et tenant
- [X] Connexion d'un utilisateur de tenant
- [X] Mauvais mot de passe → refus clair
- [X] Changement de mot de passe (PasswordChange)
- [X] Activation de la double authentification (2FA / TwoFactor)
- [X] Connexion avec 2FA active
- [X] Invitation d'un utilisateur (Invitation) → e-mail reçu → activation du compte
- [X] Limitation des tentatives (brute force / lockout)

## 3. Super-admin (plateforme)

- [X] Création d'une organisation (tenant) — slug, base créée
- [X] Accès au tenant via son sous-domaine `slug.pladigit.test`
- [X] Liste / édition / suspension d'une organisation
- [X] Statistiques plateforme (Stats)
- [P] Tableau de bord sécurité (Security) - mettre la rétention max par default à 12 mois. Tester le chiffrement GPG. Je ne vois pas de workers, ni de clés SSH.
- [?] Gestion SSL par tenant (Ssl). Dans tests pas de certificat.
- [X] Sauvegardes (Backup) : déclenchement, présence du fichier, chiffrement GPG. **Vérifié 2026-07-09** — chiffrement réel confirmé (`file` → PGP symmetric key encrypted data, AES-256) ; permissions corrigées `644` → `600` (`BackupService::sendLocal`, chmod explicite après `copy()`) ; validé de bout en bout sur VPS : création (`www-data`), permissions, visible et inspectable dans Super Admin (SHA-256 + contenu tar affichés). Le souci initial de lecture « sans problème » venait d'un test sur poste local où le fichier était créé en `deploy` et lu en `www-data` — pas un cas réel de prod (VPS : même identité pour création et lecture). Pour forcer une sauvegarde hors planning (tests) : `pladigit:backup --slug=xxx --force`.
- [-] Test la sauvegarde : manque explication
- [-] Test de restauration de sauvegarde : manque explication pour les utilisateurs
- [-] DataGrid au niveau super-admin : voir datagrid
- [-] Mise à jour plateforme (Update) - a tester avec nouvelle version
- [X] Tester de la sauvegarde

## 4. Administration du tenant

- [X] Réglages de l'organisation (Settings)
- [X] Gestion des utilisateurs (création, rôles, désactivation)
- [X] Gestion des services / départements (Department)
- [-] Journal d'audit (Audit) — les actions sont tracées : si modification hiearchie affichage "structure modifié".
- [P] Purge de données (AdminPurge) avec confirmation. Pas 3 mois min passé
- [P] Administration GED (AdminGed). A voir en détail
- [X] Réassignation de projets (ProjectReassign)
- [P] Demande d'activation SSL (SslRequest) côté tenant. Il s'agit du bandeau orange ?

## 5. Gestion de projet

- [X] Création d'un projet
- [ ] Édition / suppression d'un projet
- [X] Vue Kanban (déplacement de cartes)
- [X] Création / édition de tâches (Task)
- [X] Commentaires de tâche (TaskComment)
- [X] Dépendances entre tâches (TaskDependency)
- [X] Jalons (Milestone)
- [X] Budget de projet (ProjectBudget)
- [?] Membres du projet (ProjectMember) et rôles : Peut être faire un lien dans le projet vers l'affectation des membres.
- [X] Parties prenantes (Stakeholder)
- [X] Événements / calendrier (ProjectEvent)
- [X] Observations (ProjectObservation)
- [?] Documents liés au projet (ProjectDocument) : erreur réseau
- [?] Lien projet ↔ GED (ProjectGedLink) : c'est pas fait ?
- [X] Historique des modifications (ProjectHistory / ProjectChange)
- [?] Modèles de projet (ProjectTemplate) : lors de la reprise d'un modèle -> il ne faut pas de date. Pas de historique, document, partie prenante, comm, risque, obs.
- [X] Transfert de projet (ProjectTransfer)

## 6. Photothèque (Media / NAS)

- [ ] Téléversement d'une photo via l'interface
- [ ] Extraction EXIF automatique sur un JPEG (worker actif)
- [ ] Génération de la miniature
- [ ] Dépôt direct sur le NAS + ingestion par `nas:sync` (jusqu'à 1 h, ou forcé)
- [ ] Création / édition d'album (MediaAlbum)
- [ ] Permissions d'album (AlbumPermission)
- [ ] Album partagé (SharedAlbum)
- [ ] Recherche de médias (MediaSearch)
- [ ] Tags sur média (MediaItemTag)
- [ ] Détection de doublons (MediaDuplicate)
- [ ] Vérification d'intégrité (MediaIntegrity)
- [ ] Partage d'un média (MediaItemShare)
- [ ] Lien de partage public (MediaShareLink)
- [ ] Préférences d'affichage (MediaPreference)

## 7. GED & édition collaborative

- [ ] Création d'un dossier (GedFolder)
- [ ] Téléversement d'un document (GedDocument)
- [ ] Ouverture / édition dans Collabora (GedEditor / WOPI)
- [ ] Enregistrement depuis Collabora → version mise à jour
- [ ] Permissions sur dossier/document (GedPermission)
- [ ] Recherche documentaire (GedSearch)
- [ ] Vérification d'intégrité (GedIntegrity)
- [ ] Affichage des options « Ouvrir dans… » selon le pilote actif (OFFICE_DRIVER)

## 8. DataGrid / DataPilot

- [ ] Affichage d'un DataGrid (Tabulator)
- [ ] Tri / filtre / recherche dans le tableau
- [ ] Masquage / affichage de colonnes par l'utilisateur
- [ ] Sauvegarde d'une vue personnelle nommée (par `user_id`)
- [ ] Colonnes par défaut configurées par l'admin
- [ ] Organisation en dossiers (DatagridFolder)
- [ ] Export / impression PDF (DatagridPdf)

## 9. Tableau de bord & transverse

- [ ] Tableau de bord (Dashboard) — chiffres cohérents
- [ ] Notifications (Notification)
- [X] Profil utilisateur (Profile) — édition, avatar
- [-] Formulaire de contact (Contact).
- [ ] Pages légales / RGPD (Legal — mentions, confidentialité)
- [ ] Endpoint de santé (Health — `/health/ping`)

## 10. Isolation multi-tenant (sécurité)

- [X] Un utilisateur du tenant A **ne voit pas** les données du tenant B
- [X] Les bases `pladigit_{slug}` sont bien séparées
- [X] L'accès à un sous-domaine d'un autre tenant est refusé/redirigé — **corrigé 2026-07-09** (faille réelle : session partagée entre sous-domaines via `SESSION_DOMAIN` wildcard + collision d'ID entre bases tenant ; corrigé par `GuardTenantSession`, validé en local, VM et VPS production)
- [ ] Un fichier (média/GED) d'un tenant n'est pas accessible depuis un autre
- [ ] Les URL d'un tenant ne fuient pas sur un autre

## 11. Robustesse / « comme un utilisateur qui veut casser »

- [ ] Saisie de caractères spéciaux / très longs dans les formulaires
- [ ] Upload d'un fichier non autorisé (exécutable, taille énorme)
- [ ] Upload d'un fichier au mauvais format dans la photothèque (PNG/HEIC → pas d'EXIF, sans erreur)
- [ ] Accès à une URL sans être connecté → redirection login
- [ ] Accès à une ressource d'un autre utilisateur (modifier l'ID dans l'URL)
- [ ] Soumission de formulaire sans jeton CSRF
- [ ] Navigation dans le désordre (étapes sautées)
- [ ] Double-clic / double-soumission (pas de doublon)
- [ ] Déconnexion puis bouton « retour » du navigateur (pas d'accès résiduel)
- [X] Messages d'erreur ne révélant pas d'info technique sensible

---

### Suivi des problèmes rencontrés

| # | Module | Description du problème | Sévérité |
|---|--------|-------------------------|----------|
| 1 | Isolation multi-tenant | Session partagée entre sous-domaines (`SESSION_DOMAIN` wildcard) + collision d'ID entre bases tenant → accès à un autre tenant sans ré-authentification. Corrigé par `GuardTenantSession` (2026-07-09). | 🔴 Critique — résolu |
| 2 | Favicon | Ancien favicon générique, ne correspondait plus au logo actuel (« ballon » bleu). Régénéré à partir de `public/img/logo.png`, mark seul recadré sans le texte « Plateforme Digitale ». Résolu 2026-07-09. | 🟡 Mineur — résolu |
| 3 | Sauvegardes locales | Fichiers `.gpg` créés en `644` au lieu de `600` (`copy()` sans `chmod` explicite dans `BackupService::sendLocal`). Corrigé 2026-07-09, validé en production sur VPS. | 🟠 Modéré — résolu |
|   |        |                         |          |


0 - ~~Icone à modifier~~ FAIT — 2026-07-09 (voir tableau de suivi #2)
