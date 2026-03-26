
PLADIGIT
Synthèse Technique Complète — Phase 2
Sessions de développement — Octobre 2025 → Mars 2026


# 1. Contexte du projet

Pladigit est une plateforme SaaS de digitalisation interne, alternative souveraine open source (AGPL-3.0) aux outils Microsoft. Elle est développée par Jean-Pierre Bossé pour Les Bézots (Soullans, Vendée) sur un planning de 48 mois (octobre 2025 → septembre 2029).

## 1.1 — Stack technique

## 1.2 — Environnement de production
- VPS Ubuntu 24 LTS — Nginx, PHP-FPM 8.4, MySQL 8, Redis, Soketi
- GitHub Actions CI/CD — 4 jobs : PHPUnit, Pint, PHPStan, Composer audit
- Dépôt : github.com/jpbosse/pladigit — branches develop / main
- Serveur de développement : /var/www/pladigit — user deploy

# 2. Session v1.1 — Socle Phase 1 (Oct–Déc 2025)

Synthèse de la session ayant produit synthese.md v1.1. État initial : Phase 1 livrée, 221 tests / 492 assertions. CI vert.

## 2.1 — Livrables Phase 1
- Infrastructure multi-tenant opérationnelle : TenantManager, bases MySQL dédiées par organisation.
- Authentification locale complète : bcrypt coût 12, sessions, verrouillage compte après N tentatives.
- 2FA TOTP : Google Authenticator / Aegis, codes de secours chiffrés AES-256, rate limiting.
- Middleware ForcePwdChange : changement de mot de passe obligatoire au premier login.
- Super Admin : interface /super-admin, création organisations, isolation totale.
- CI/CD GitHub Actions : PHPUnit + Pint + PHPStan — 3 checks verts sur chaque commit.
- Suite de tests Phase 1 : 47 tests / 77+ assertions sur toutes les couches critiques.

## 2.2 — Architecture multi-tenant
Décision clé ADR-002 : chaque organisation dispose d'une base MySQL dédiée (pladigit_{slug}). Isolation absolue des données, conformité RGPD par construction, migrations indépendantes. Le TenantManager détecte l'organisation via le sous-domaine et configure dynamiquement la connexion Eloquent.

## 2.3 — Suite de tests Phase 1

# 3. Session v1.2 — Début Phase 2 (Jan–Fév 2026)

Synthèse de la session ayant produit synthese v1.2. Travaux : page profil, directions/services, UserRole enum, invitation email, dashboard Blade.

## 3.1 — Fonctionnalités implémentées
- Page profil utilisateur : informations personnelles, changement de mot de passe, gestion 2FA, codes de secours.
- UserRole enum (App\Enums\UserRole) : source unique de vérité — level(), atLeast(), label() en français.
- Structure organisationnelle : tables departments (type: direction|service, parent_id) et user_department (pivot is_manager).
- Visibilité des utilisateurs selon le rôle : Admin/DGS voit tout, Resp. Direction voit sa direction, etc.
- Invitation utilisateur par email : token 64 chars, durée 72h, remplacement du mot de passe en clair.
- Dashboard Blade : widgets par rôle (statistiques, raccourcis), décision de réserver Livewire à Phase 3+.
- Personnalisation visuelle : logo, couleurs primaires et nom de l'organisation par l'Admin Organisation.
- Configuration LDAP et SMTP depuis l'interface d'administration (routes §6.1 activées).

## 3.2 — Décisions techniques
Livewire réservé à Phase 3+ (GED/photothèque) — Blade avec contrôleurs directs suffit pour les phases 1 et 2 et évite la complexité du build pipeline JS. Dashboard configurable à concevoir avant Phase 3.

## 3.3 — État CI/CD en fin de session

# 4. Session du 13 mars 2026 — Fin Phase 2 + CDC v2.0

Cette session a finalisé la Phase 2 (§6.2, §6.3, §6.6) et produit la refonte complète du CDC. Point de départ : 221 tests.

## 4.1 — §6.2 : Tests LDAP Docker (LdapAuthTest)
### Contexte
Les tests LdapAuthTest n'existaient pas dans l'archive — ils ont été écrits depuis zéro en s'appuyant sur le docker-compose.test.yml et init.ldif déjà en place. OpenLDAP tourne sur le port 3389 (test) avec 3 utilisateurs (alice admin, bob user, charlie dgs).

### Bugs corrigés dans LdapAuthService.php

### Modification de signature
syncUser(array $ldapEntry, ?string $preResolvedRole = null, ?Connection $conn = null, ?string $baseDn = null) — le rôle pré-résolu est passé en paramètre. L'appel dans syncAllUsers() passe null pour conserver le comportement existant.

### Résultat
LdapAuthTest : 5/5 verts ✓ — Tests groupe "ldap", lancés manuellement avec OpenLDAP Docker.

## 4.2 — §6.6 : Option --tenant sur SyncLdapUsers
### Comportement ajouté
La commande artisan pladigit:sync-ldap accepte désormais --tenant=slug pour cibler un seul tenant. Sans l'option, tous les tenants actifs sont synchronisés (comportement inchangé).

Logique ajoutée dans handle() :
- $slug = $this->option('tenant') — si fourni, filtre sur organizations.slug.
- Tenant inexistant ou inactif → message d'erreur + return Command::FAILURE.
- Sans option → tous les tenants actifs traités en boucle.

### Bugs dans SyncLdapUsersTest corrigés

### 5 tests couverts
- Sans --tenant → tous les tenants actifs synchronisés (exactly(2))
- Avec --tenant=slug → uniquement ce tenant (once())
- --tenant=slug-inexistant → message erreur + exit code 1
- Tenant suspended ignoré sans option
- Tenant suspended ciblé directement → exit code 1

SyncLdapUsersTest : 5/5 verts ✓ — 15 assertions.

## 4.3 — §6.3 : Tests branding (BrandingTest)
### Contexte
SettingsTest.php existait déjà (accès, couleurs valides/invalides, upload/formats). BrandingTest.php complète avec isolation tenant, suppression de fichiers et reset des valeurs par défaut.

### Bug principal : org non persistée en base
TestCase::setUp() crée l'org avec new Organization([...]) sans save() → $org->update([...]) ne persiste rien → le contrôleur voit logo_path = null → ne supprime pas le fichier → assertMissing() échoue.

Solution — helper persistCurrentOrg() :
- forceCreate() avec le même slug/db_name que l'org test.
- TenantManager::connectTo() reconnecte sur le modèle persisté.
- Les 3 tests concernant les fichiers l'appellent en premier.

### 6 tests couverts
- remove_logo → supprime le fichier Storage + vide logo_path
- remove_login_bg → idem pour le fond de connexion
- Upload nouveau logo → ancien fichier supprimé, nouveau path sauvegardé
- Couleur par défaut conservée si aucun champ soumis
- Isolation tenant → branding tenant A n'affecte pas tenant B
- Non-admin → 403

BrandingTest : 6/6 verts ✓ — 20 assertions.

## 4.4 — Corrections CI/CD GitHub Actions

## 4.5 — État final Phase 2


# 5. Bilan complet Phase 2

## 5.1 — Toutes les tâches

## 5.2 — Évolution des tests

## 5.3 — Fichiers modifiés / créés en Phase 2

# 6. Refonte documentaire — CDC v2.0

Le CDC v1.4 était un document monolithique fusionné (toutes versions + toutes annexes). Il a été restructuré en un document principal + 7 annexes séparées, chacune évoluant indépendamment.

## 6.1 — Documents produits

Tous les fichiers ont passé la validation XML/schéma (python scripts/office/validate.py — All validations PASSED).
Annexes restantes (E, F, G, H, I, J, L, N, O, P, R, S) à produire au fil des phases 3–13.

# 7. Prochaines étapes — Phase 3

## 7.1 — Phase 3 : Photothèque NAS (Avr–Jun 2026)
- Connexion au NAS de l'organisation (SMB/CIFS ou SFTP) — sans remplacer le NAS existant.
- Galerie avec albums, visionneuse plein écran, tri par date/tag/auteur.
- Watermark automatique configurable (texte ou logo) — fichier NAS jamais modifié.
- Droits d'accès à 3 niveaux par album : visualisation, téléchargement, administration.
- Upload drag & drop multi-fichiers, extraction EXIF, détection doublons SHA-256.
- Types supportés : JPEG, PNG, WEBP, GIF, MP4, MOV, PDF.

## 7.2 — Points techniques à décider avant Phase 3
- Dashboard configurable : architecture des widgets (layout par rôle, ordre personnalisable ?).
- Footer (mentions légales, version, contact) dans layouts/app.blade.php et layouts/admin.blade.php — non encore implémenté.
- Migration Livewire 3 pour la galerie photothèque — valider le build pipeline vite.config.js.

## 7.3 — Bonnes pratiques consolidées