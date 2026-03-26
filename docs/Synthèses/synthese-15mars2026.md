PLADIGIT
Plateforme de Digitalisation Interne
Alternative souveraine open source AGPL-3.0

# 1. Résumé exécutif
Cette session a couvert le développement de 8 fonctionnalités majeures de la Phase 3 de Pladigit, ainsi que plusieurs corrections de robustesse et d'ergonomie. Le pipeline CI/CD reste vert avec 311 tests passants.


# 2. Modules activables par organisation
Socle architectural SaaS permettant d'activer ou désactiver les modules Pladigit par organisation depuis l'interface Super Admin.
## 2.1 — ModuleKey enum (App\Enums\ModuleKey)
Source unique de vérité pour les 8 modules de la plateforme :
- media — Photothèque (Phase 3) — disponible
- ged — Gestion documentaire (Phase 5)
- collabora — Édition collaborative (Phase 6)
- erp — ERP DataGrid (Phase 7)
- projects — Gestion de projet (Phase 8)
- chat — Chat temps réel (Phase 9)
- news — Fil d'actualités (Phase 10)
- surveys — Sondages (Phase 11)
Méthodes : label(), phase(), isAvailable(), available(), values(), options()
## 2.2 — Organization::hasModule()
Colonne JSON enabled_modules sur la table organizations (pladigit_platform). Cast automatique en array. Méthodes : hasModule(), enableModule(), disableModule(), activeModules().
## 2.3 — Middleware RequireModule
Appliqué sur toutes les routes /media/* via middleware('module:media'). Retourne HTTP 403 si le module est désactivé pour le tenant courant. Les routes super-admin sont exemptées.
## 2.4 — Interface Super Admin
Checkboxes dans la fiche organisation. Seuls les modules disponibles (isAvailable()) sont affichés. L'état activé/désactivé est mémorisé en JSON. Style inline — indépendant du build Tailwind.
## 2.5 — Navigation conditionnelle
Sidebar et command palette : le lien Photothèque n'apparaît que si hasModule(MEDIA) est vrai. Les modules futurs (GED, Agenda, Chat, ERP) ont été supprimés de la sidebar et du dashboard.

# 3. Watermark sur téléchargements
Apposition d'un watermark à la volée lors du téléchargement uniquement. Le fichier original sur le NAS n'est jamais modifié.
## 3.1 — WatermarkService
- Rendu via GD natif PHP 8.4 — aucune dépendance externe
- Police TrueType DejaVuSans-Bold — support UTF-8 natif (accents français)
- Fallback GD interne si FreeType absent — translittération des accents
- Fond rectangle semi-transparent (imagefilledrectangle) derrière le texte
- Type texte : imagettftext + bbox pour calcul précis des dimensions
- Type logo : logo de l'organisation redimensionné + imagecopymerge
- MIME supportés : image/jpeg, image/png, image/webp
- Vidéos et PDF non watermarkés — retour du fichier original
## 3.2 — Configuration par tenant (TenantSettings)
Nouvelles colonnes : wm_enabled, wm_type (text|logo), wm_text, wm_position (bottom-right|bottom-left|center|bottom-center), wm_opacity (10–100), wm_size (small|medium|large).
Interface : Admin → Paramètres → Photothèque — section watermark avec toggle, choix type, champ texte, sélecteurs position/taille, curseur opacité.
## 3.3 — Migration
2026_04_01_000003_add_watermark_to_tenant_settings — nommé après media_cols pour respecter l'ordre de migration.

# 4. Super Admin — Refonte page organisation
La page show.blade.php a été entièrement réécrite avec une navigation par onglets JS.
## 4.1 — Structure
- En-tête : nom, statut (badge coloré), plan, boutons Modifier / Suspendre / Activer
- 4 métriques : utilisateurs, stockage, base MySQL, modules actifs
- 4 onglets : Administrateur / Modules / SMTP / LDAP
- Onglet actif mémorisé dans l'URL (?tab=...) — survit au rechargement après sauvegarde
## 4.2 — Style
100% style inline — indépendant du build Tailwind CSS. Fonctionne immédiatement sans recompilation.

# 5. Arbre hiérarchique utilisateur
Correction du formulaire de création et d'édition utilisateur : l'arbre directions/services était limité à 2 niveaux.
## 5.1 — Correction UserController
Remplacement de directions()->get() + services()->get() par Department::whereNull('parent_id')->with('allChildren')->get() — chargement récursif N niveaux.
## 5.2 — Partial _dept_checkboxes.blade.php
Nouveau partial récursif : indentation 18px par niveau, icônes 🏢/📁/📂, support du label de département, attribut data-name pour la recherche.
## 5.3 — Champ de recherche
Input de filtrage instantané (JS oninput) sur nom et label. filterDepts() masque les items non correspondants via display:none.

# 6. Corrections de robustesse
## 6.1 — TenantProvisioningService — Rollback atomique
Problème : si les migrations tenant échouaient après la création de la base MySQL, le tenant existait dans un état incohérent (base créée mais vide, org en pending).
Solution :
- Variable $dbCreated pour tracer la création de la base
- Vérification du code de retour d'Artisan::call() — exception si exit code ≠ 0
- Bloc catch : DROP DATABASE IF EXISTS si la base a été créée
- ProvisioningException dédiée (App\Services\ProvisioningException)
- OrganizationController::store() : supprime l'org et redirige avec erreur si provisioning échoue
Tests : TenantProvisioningTest — 5 tests Feature (2 marqués @group integration pour les tests nécessitant CREATE DATABASE).
## 6.2 — MediaService::upload() — Transaction BDD + Compensation NAS
Problème : writeFile() NAS réussissait puis MediaItem::create() pouvait échouer → fichier orphelin permanent sur le NAS.
Solution :
- MediaItem::create() encapsulé dans DB::connection('tenant')->transaction()
- Bloc catch : suppression du fichier NAS ET de la miniature si la transaction BDD échoue
- Log::warning si la suppression NAS échoue à son tour
- RuntimeException levée avec contexte complet

# 7. Footer statique et pages légales
## 7.1 — Footer
- Position static (plus de position: fixed) — visible uniquement en bas de page après scroll
- 3 blocs : infos système (gauche vertical) / liens légaux (droite vertical) / flèche ↑
- margin-left: var(--pd-footer-h) synchronisé avec pd-main pour éviter le chevauchement sidebar
- Version v1.5 · Phase 3, copyright, email, GitHub, AGPL-3.0
## 7.2 — Pages légales
- Route /mentions-legales → LegalController::mentions() → legal/mentions.blade.php
- Route /confidentialite → LegalController::confidentialite() → legal/confidentialite.blade.php
- Mentions légales : éditeur, responsable, hébergement, licence AGPL-3.0 expliquée, propriété intellectuelle
- Politique de confidentialité : données collectées, base légale, durée conservation, droits RGPD, sécurité, cookies, sous-traitants
- Pages autonomes (pas de layout Pladigit) — accessibles sans authentification

# 8. État des tests — fin de session
## 8.1 — Évolution


## 8.2 — Groupes exclus du CI
- --exclude-group ldap : tests d'intégration OpenLDAP Docker (non disponible sur GitHub Actions)
- --exclude-group integration : tests nécessitant CREATE DATABASE (droits non accordés sur le runner)
Ces tests peuvent être lancés manuellement : php artisan test --group ldap (avec OpenLDAP Docker) ou php artisan test --group integration (avec droits MySQL complets).
## 8.3 — Pipeline CI/CD
- PHPUnit : 311 passed — exclude-group ldap,integration
- Laravel Pint : 0 style issue
- PHPStan niveau 5 : 0 erreur (continue-on-error pour smbclient)
- Composer audit : 0 vulnérabilité

# 9. Fichiers produits cette session


# 10. Prochaines étapes
## 🔴 Priorité haute — bloquant avant premier client
- Queue Laravel — jobs ProcessMediaUpload, ProcessZipImport (timeout HTTP en prod)
- Import ZIP — extraction + ingestion via queue
## 🟡 Phase 3 — photothèque
- Image de couverture sur les albums
- Partage par lien temporaire
- Tri par date EXIF (prise de vue réelle)
- Détection de doublons inter-albums
- Export ZIP d'un album
- Recherche dans la photothèque
- serve() images > 10 Mo → stream() automatique
## 🟡 Infrastructure
- Index BDD sur department_id + tenant
- Purge sessions expirées — Artisan planifié
- Cache thumbnails Apache2 — Cache-Control renforcé
## 🔵 Phase 12 — avant prod
- Rotation audit_logs > 12 mois
- Enforcement quota strict
- CSP + headers sécurité complets (Apache2)
- Rate limiting sous-domaines (mod_evasive)
- Rotation clés AES TOTP/LDAP
- Audit cross-tenant TenantManager
- Suppression albums démo parasites
## Note technique — ADR-007 : PHPStan + smbclient
L'extension PHP smbclient (pecl) n'est pas disponible sur le runner GitHub Actions. Le stub stubs/smbclient.php définit les symboles pour l'analyse locale. Le CI utilise continue-on-error: true sur PHPStan jusqu'à résolution.
Règle générale : toute extension PHP non disponible sur le CI → stub dans stubs/ + ignoreErrors avec reportUnmatched: false dans phpstan.neon.