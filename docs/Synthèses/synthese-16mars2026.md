PLADIGIT
Plateforme de Digitalisation Interne
Alternative souveraine open source AGPL-3.0

# 1. Résumé exécutif
Cette session a permis de corriger 5 bugs critiques, de livrer 3 nouvelles fonctionnalités majeures et de mettre en place l'infrastructure de queue Redis avec supervision systemd. Le pipeline CI/CD reste vert.


# 2. Corrections appliquées
## 2.1 — Colonnes nas_photo_* manquantes
Symptôme : SQLSTATE[42S22] Column not found nas_photo_driver lors de la sauvegarde des paramètres NAS d'une nouvelle organisation.
Cause : La migration 2026_04_01_000001_add_nas_columns_to_tenant_settings créait les colonnes nas_driver, nas_local_path... mais le code NasManager.php lisait nas_photo_driver, nas_photo_local_path...
Correction
- Migration 2026_04_01_000001 corrigée — colonnes renommées nas_photo_* à la source
- Commande pladigit:fix-nas-columns créée et exécutée pour renommer les colonnes sur les tenants existants
- Migration appliquée sur tous les tenants actifs via php artisan migrate:tenants --force
- Commande supprimée après usage


## 2.2 — Doublons d'albums lors de la synchronisation NAS
Symptôme : Plusieurs albums avec le même nom apparaissaient en interface après plusieurs synchronisations NAS.
Cause : La méthode findOrCreateAlbumForPath() cherchait les albums existants uniquement par nas_path, sans filtrer par parent_id. Des dossiers portant le même nom dans des arborescences différentes (ou après syncs multiples) généraient des doublons.
Correction
- Fichier : app/Services/MediaService.php
- Ajout du filtre ->where('parent_id', $parentId) dans la requête de recherche
- Nettoyage en base des doublons existants via DELETE SQL ciblé (sauvegarde préalable effectuée)
- Résultat : 59 albums → 48 albums après dédoublonnage

## 2.3 — CSS cassé sur la page photothèque
Symptôme : Les photos s'affichaient en bas de page au lieu d'être à droite de la sidebar.
Cause : Un caractère parasite x avant le premier commentaire CSS (x/* Layout général */) cassait le parsing du bloc #ph-wrap.
- Fichier : resources/views/media/albums/show.blade.php ligne 5
- Suppression du x parasite — layout restauré immédiatement

## 2.4 — Permissions storage/ www-data vs deploy
Symptôme récurrent : Permission denied sur storage/logs/laravel.log, storage/app/nas_simulation/ et storage/app/private/tmp/.
Cause : PHP-FPM tourne sous www-data, le worker queue sous deploy. Les fichiers créés par l'un n'étaient pas accessibles par l'autre.
Correction définitive
- sudo usermod -aG deploy www-data — www-data ajouté au groupe deploy
- sudo chown -R deploy:deploy storage/ + chmod -R 775 storage/
- sudo systemctl restart php8.4-fpm

# 3. Nouvelles fonctionnalités
## 3.1 — Popup de connexion sur pladigit.fr
Remplacement du dropdown slug-only par un popup modal complet permettant la connexion en une seule étape depuis la page d'accueil publique.
Comportement
- 1ère connexion : saisie manuelle de l'organisation (texte)
- Connexions suivantes : organisation pré-remplie depuis cookie pladigit_org (durée 1 an)
- Vérification AJAX de l'existence du slug via /check-org-ajax/{slug}
- Si org invalide : message d'erreur dans le popup
- Si org valide : cookie écrit + formulaire POST soumis vers http://{slug}.pladigit.fr/login
- Après connexion réussie : redirection vers le dashboard de l'organisation
- Après déconnexion : redirection vers pladigit.fr (LoginController::logout corrigé)
Fichiers modifiés
- resources/views/welcome.blade.php — popup Alpine.js + JS cookie
- app/Http/Controllers/Auth/LoginController.php — logout() → redirect pladigit.fr
- bootstrap/app.php — exemption CSRF pour route /login (cross-domaine)

## 3.2 — Queue Laravel avec supervision systemd
Mise en place de la queue Redis pour rendre les uploads non-bloquants. Le Job ProcessMediaUpload traite en arrière-plan la génération de miniature, l'extraction EXIF et le calcul des dimensions.
Architecture
- Upload synchrone : validation + détection doublon SHA-256 + écriture NAS + création MediaItem (statut pending)
- Upload asynchrone (queue) : miniature + EXIF + dimensions → statut done
- En cas d'échec du Job : statut failed + log d'erreur
Infrastructure
- QUEUE_CONNECTION=redis déjà configuré en .env
- Migration ajout colonne processing_status (pending|done|failed) sur media_items
- Service systemd pladigit-queue.service — User=deploy, Restart=always, RestartSec=5
- Démarrage automatique au boot + redémarrage en cas de crash
Fichiers créés/modifiés
- app/Jobs/ProcessMediaUpload.php — nouveau Job
- app/Models/Tenant/MediaItem.php — processing_status ajouté au fillable
- app/Services/MediaService.php — upload() allégé, dispatch du Job
- database/migrations/tenant/2026_03_16_102817_add_processing_status_to_media_items.php
- /etc/systemd/system/pladigit-queue.service

## 3.3 — Import ZIP en arrière-plan
Nouvelle fonctionnalité permettant d'importer un fichier ZIP de photos directement dans un album. L'extraction et l'ingestion se font via la queue pour éviter tout timeout HTTP.
Comportement
- Upload du ZIP via formulaire modal → stockage temporaire dans storage/app/private/tmp/zip_imports/
- Dispatch immédiat du Job ProcessZipImport — réponse HTTP instantanée
- Job : extraction ZIP + ingestion de chaque fichier via MediaService::upload()
- Extensions supportées : jpg, jpeg, png, webp, gif, mp4, mov, pdf
- Fichiers ignorés : dossiers, fichiers cachés, __MACOSX, extensions non supportées
- Doublons ignorés automatiquement (SHA-256)
- Nettoyage automatique du ZIP temporaire après traitement
- Quota : vérifié à chaque fichier via MediaService::upload()
Fichiers créés/modifiés
- app/Jobs/ProcessZipImport.php — nouveau Job (timeout 600s, tries 1)
- app/Http/Controllers/Media/MediaItemController.php — méthode importZip()
- routes/web.php — Route POST media/albums/{album}/import-zip
- resources/views/media/albums/show.blade.php — bouton + modal ZIP

# 4. État CI/CD — fin de session

# 5. Prochaines étapes
## 🔴 Priorité haute — bloquant avant premier client
- Album public → droits restreints automatiquement (#5)
- Espace stockage masqué dans module (#6)
## 🟡 Phase 3 — Photothèque
- Image de couverture sur les albums
- Partage par lien temporaire
- Tri par date EXIF (prise de vue réelle)
- Détection doublons inter-albums
- Export ZIP d'un album
- Recherche dans la photothèque
- serve() images > 10 Mo → stream() automatique
## 🟡 Infrastructure
- Index BDD sur department_id + tenant
- Purge sessions expirées — Artisan planifié
- Cache thumbnails — Cache-Control renforcé
## 🔵 Phase 12 — avant prod
- Rotation audit_logs > 12 mois
- Enforcement quota strict
- CSP + headers sécurité complets (Apache2)
- Rate limiting sous-domaines (mod_evasive)
- Rotation clés AES TOTP/LDAP
- Audit cross-tenant TenantManager

# 6. Bonnes pratiques consolidées

— Fin de la synthèse —