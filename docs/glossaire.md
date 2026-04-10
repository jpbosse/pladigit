# Glossaire — Pladigit

Ce glossaire définit les termes techniques et métier utilisés dans la documentation de Pladigit. Les termes sont classés par catégorie.

---

## Architecture & technique

**ADR (Architecture Decision Record)**
Document qui enregistre une décision architecturale importante : le contexte, la décision prise, les alternatives considérées et les conséquences. Pladigit dispose de 22 ADR dans `docs/adr/`. Permet à tout contributeur de comprendre *pourquoi* un choix a été fait, pas seulement *quoi*.

**Alpine.js**
Framework JavaScript léger utilisé pour les interactions frontend (modales, drag & drop, upload progressif). Complémentaire à Livewire — Alpine.js gère les interactions purement côté client, Livewire gère les composants réactifs côté serveur.

**Artisan**
Interface en ligne de commande de Laravel. Permet d'exécuter des commandes de maintenance, migrations, tests, synchronisations NAS, etc. Exemple : `php artisan ged:sync`.

**Base platform**
Base MySQL centrale (`pladigit_platform`) contenant uniquement la table `organizations` avec les informations de connexion à chaque base tenant. Ne contient jamais de données utilisateurs.

**Base tenant**
Base MySQL dédiée à une organisation (`pladigit_{slug}`). Contient toutes les données de l'organisation : utilisateurs, documents, photos, projets, logs d'audit. Isolée des autres tenants.

**CI/CD (Continuous Integration / Continuous Deployment)**
Pipeline automatisé déclenché à chaque push sur GitHub. Exécute 4 vérifications : PHPUnit (tests), Pint (style), PHPStan (typage), Composer audit (sécurité). Aucun merge sur `main` si un check échoue.

**Circuit breaker LDAP**
Mécanisme de protection qui coupe temporairement les tentatives de connexion LDAP après un certain nombre d'échecs consécutifs. Évite qu'une panne LDAP ne bloque tous les utilisateurs — le fallback sur l'authentification locale prend le relais.

**Driver NAS**
Implémentation concrète d'un protocole de stockage. Pladigit propose trois drivers : `local` (disque local), `sftp` (NAS Linux/FreeBSD), `smb` (NAS Windows/Samba). Le driver actif est configuré par tenant dans `TenantSettings`.

**Eloquent**
ORM (Object-Relational Mapping) de Laravel. Permet d'interagir avec la base de données via des objets PHP plutôt que des requêtes SQL brutes. Les modèles Pladigit (`GedDocument`, `MediaItem`, `Project`...) sont des classes Eloquent.

**GedStorageInterface**
Interface PHP définissant le contrat commun pour tous les drivers de stockage GED (`put`, `get`, `delete`, `exists`, `listDirectory`...). Le code métier n'interagit jamais directement avec un driver — il injecte `GedStorageInterface`. Voir ADR-020.

**Job / Queue**
Tâche asynchrone exécutée en arrière-plan par un worker Redis. Utilisé pour les traitements longs : upload de photos, import ZIP, synchronisation NAS, export. Évite de bloquer la requête HTTP de l'utilisateur.

**Laravel**
Framework PHP MVC utilisé comme socle de Pladigit. Version 11.x. Fournit le routing, l'ORM Eloquent, les migrations, la gestion des jobs, le scheduler, et l'injection de dépendances.

**Livewire**
Framework Laravel permettant de construire des interfaces réactives sans JavaScript. Les composants Livewire s'exécutent côté serveur et communiquent via AJAX avec le navigateur. Version 4.2 dans Pladigit.

**Middleware**
Code exécuté avant qu'une requête HTTP atteigne le contrôleur. Pladigit utilise notamment `ResolveTenant` (résolution du tenant depuis le sous-domaine), `RequireModule` (vérification qu'un module est activé), `CheckRole` (vérification du rôle utilisateur).

**Migration**
Script PHP versionné qui décrit une modification de schéma de base de données. Pladigit sépare les migrations en deux répertoires : `database/migrations/platform/` (base centrale) et `database/migrations/tenant/` (bases organisations).

**Multi-tenant**
Architecture où une seule application sert plusieurs organisations indépendantes, chacune avec ses propres données isolées. Pladigit utilise une base MySQL dédiée par organisation (approche "database-per-tenant").

**NasManager**
Service Laravel qui instancie le bon driver de stockage NAS (local, SFTP, SMB) en fonction de la configuration du tenant. Fonctionne pour la photothèque (`NasManager::mediaDriver()`) et pour la GED (`NasManager::gedDriver()`).

**PHPStan**
Outil d'analyse statique PHP. Pladigit exige le niveau 5 (0 erreur tolérée). Détecte les erreurs de typage, les variables indéfinies, les appels de méthodes incorrects — sans exécuter le code.

**Pint**
Outil de formatage PHP de Laravel. Applique automatiquement le standard PSR-12. Exécuté sur chaque commit via CI/CD.

**Redis**
Base de données en mémoire utilisée pour le cache, les sessions et la file de tâches (queue). Plus rapide que MySQL pour ces usages. Requis en production.

**ResolveTenant**
Middleware qui s'exécute en premier sur chaque requête. Extrait le slug de l'organisation depuis le sous-domaine (ex: `mairie-soullans.pladigit.fr` → slug `mairie-soullans`), charge l'organisation depuis la base platform, puis appelle `TenantManager::connectTo()`.

**Scheduler**
Planificateur de tâches Laravel. Déclenche des commandes à intervalles réguliers (synchronisation NAS toutes les heures, génération des tâches récurrentes quotidiennement, purge des données expirées). Configuré dans `routes/console.php`. Nécessite un cron `* * * * * php artisan schedule:run`.

**Soft delete**
Suppression logique — l'enregistrement n'est pas physiquement supprimé de la base mais marqué avec une date `deleted_at`. Permet de restaurer des données supprimées par erreur. Utilisé sur les projets, tâches, photos et documents GED.

**Supervisor**
Gestionnaire de processus Linux qui maintient les queue workers Laravel actifs en permanence. Redémarre automatiquement les workers en cas de crash.

**TenantManager**
Singleton Laravel qui maintient la référence à l'organisation courante et reconfigure dynamiquement la connexion MySQL `tenant` vers la bonne base. Accessible via `TenantManager::current()` ou `TenantManager::currentOrFail()`.

**WOPI (Web Application Open Platform Interface)**
Protocole standardisé permettant à un éditeur bureautique (ici Collabora Online) d'accéder aux fichiers stockés sur un autre serveur (ici Pladigit). Pladigit implémente les endpoints WOPI : `CheckFileInfo`, `GetFile`, `PutFile`, `Lock`, `Unlock`, `RefreshLock`, `GetLock`.

---

## Modules & fonctionnalités

**Audit trail**
Journal horodaté de toutes les actions effectuées sur la plateforme : connexions, modifications de documents, changements de droits, suppressions. Stocké dans la table `audit_logs` de chaque base tenant. Exportable en CSV ou JSON. Rétention configurable par organisation (3 à 36 mois).

**Branding**
Personnalisation visuelle de l'interface par organisation : logo, couleurs primaires. Chaque organisation voit Pladigit aux couleurs de sa structure. Configuré par l'Admin Organisation dans Admin > Paramètres > Apparence.

**Collabora Online (CODE)**
Éditeur bureautique open source auto-hébergé. Alternative souveraine à Microsoft Office Online ou Google Docs. Édite les formats ODF natifs (ODT, ODS, ODP) et les formats Microsoft Office. Communique avec Pladigit via le protocole WOPI. CODE = Collabora Online Development Edition (version communautaire gratuite).

**GED (Gestion Électronique de Documents)**
Module de gestion documentaire de Pladigit. Permet d'organiser, stocker, versionner, rechercher et éditer des documents dans une arborescence de dossiers avec droits fins. Alternative souveraine à SharePoint.

**Module gating**
Mécanisme qui conditionne l'accès à une fonctionnalité à l'activation du module correspondant pour l'organisation. Implémenté via le middleware `RequireModule` et la colonne JSON `enabled_modules` sur la table `organizations`. Voir ADR-016.

**NAS (Network Attached Storage)**
Serveur de stockage en réseau. Les collectivités utilisent souvent un NAS pour stocker leurs fichiers (photos, documents). Pladigit se connecte au NAS via SFTP ou SMB et synchronise automatiquement son contenu.

**Photothèque**
Module de gestion de médiathèque de Pladigit. Permet d'organiser des photos et vidéos en albums hiérarchiques avec droits par album, synchronisation NAS, déduplication, tags, partage par lien et export. Alternative souveraine à OneDrive Photos.

**Versioning**
Mécanisme qui conserve automatiquement toutes les versions antérieures d'un document GED. Chaque modification (upload manuel ou sauvegarde Collabora) crée une version archivée dans `ged_document_versions`. Permet de restaurer n'importe quelle version antérieure.

**Watermark**
Filigrane appliqué à la volée sur les photos lors de l'affichage dans Pladigit. Le fichier original sur le NAS n'est jamais modifié. Configurable par l'Admin Organisation (texte ou logo, position, opacité).

---

## Droits & sécurité

**2FA TOTP (Two-Factor Authentication — Time-based One-Time Password)**
Double authentification par code temporaire à 6 chiffres généré par une application mobile (Google Authenticator, Aegis). Le code change toutes les 30 secondes. Activable par utilisateur. Les codes de secours sont chiffrés AES-256.

**AES-256**
Algorithme de chiffrement symétrique utilisé dans Pladigit pour protéger les secrets sensibles stockés en base : mot de passe LDAP, clé TOTP, identifiants SMTP. Standard de facto pour le chiffrement des données au repos.

**bcrypt**
Algorithme de hachage des mots de passe. Utilisé avec un coût de 12 en production (suffisamment lent pour résister aux attaques par force brute). Implémenté nativement par Laravel.

**GedPermissionLevel**
Enum PHP définissant les niveaux de droit sur un dossier GED : `None` (0), `View` (1), `Download` (2), `Upload` (3), `Admin` (4). La comparaison est numérique — `atLeast(Download)` vérifie `level >= 2`.

**Héritage des droits**
Mécanisme par lequel un sous-dossier ou sous-album hérite automatiquement des droits de son parent si aucune permission explicite n'est définie. Évite de reconfigurer les droits à chaque niveau de l'arborescence.

**LDAP / LDAPS**
Protocole d'annuaire permettant l'authentification via Active Directory ou OpenLDAP. Pladigit exige LDAPS (LDAP over SSL, port 636) — la connexion non chiffrée (port 389) est refusée. Voir ADR-005.

**PKI (Public Key Infrastructure)**
Infrastructure de gestion de clés cryptographiques permettant la signature électronique. Pladigit prévoit une PKI interne auto-hébergée (usage interne, sans valeur légale externe) et une intégration avec des prestataires RGS \*\* pour les actes officiels.

**ProjectRole**
Enum PHP définissant le rôle d'un utilisateur dans un projet spécifique : Manager, Member, Viewer. S'accumule avec le `UserRole` global — les deux couches sont évaluées simultanément. Voir ADR-010.

**RGPD (Règlement Général sur la Protection des Données)**
Règlement européen encadrant le traitement des données personnelles. Pladigit intègre nativement : rétention configurable des logs d'audit, droits d'accès granulaires, hébergement en France, pas de transfert hors UE.

**RGS \*\* (Référentiel Général de Sécurité — niveau 2 étoiles)**
Standard français pour la signature électronique qualifiée dans les administrations publiques. Requis pour les actes officiels ayant valeur juridique (délibérations, arrêtés, marchés publics). Les certificats sont délivrés par des prestataires qualifiés (Yousign, Docaposte, Universign).

**Super Admin**
Administrateur de la plateforme Pladigit dans sa globalité. Ses identifiants sont stockés dans le fichier `.env` uniquement (jamais en base de données). Il provisionne les organisations, configure SMTP et LDAP, surveille l'état global. Il n'a pas accès aux données des organisations clientes.

**Token WOPI**
Jeton d'accès temporaire généré par Pladigit pour permettre à Collabora Online d'accéder à un document. Format : `{org_slug}:{raw_token}`. Durée configurable (4h par défaut). Stocké dans `ged_wopi_tokens`.

**UserRole**
Enum PHP définissant le rôle global d'un utilisateur dans son organisation : Admin, President, DGS, ResponsableDirection, ResponsableService, Agent. Détermine les droits par défaut sur tous les modules.

---

## Secteur public

**Collectivité locale**
Désigne les communes (mairies), communautés de communes, communautés d'agglomération, départements et régions. Public cible principal de Pladigit pour les organisations de moins de 20 000 habitants.

**DGS (Directeur Général des Services)**
Principal collaborateur du Maire ou du Président d'une collectivité. Dirige l'administration et coordonne les services. Dans Pladigit, le rôle DGS donne une visibilité transverse sur tous les projets et documents.

**Délibération**
Acte officiel voté par le Conseil Municipal ou le Conseil Communautaire. Doit être transmise en préfecture. Nécessite une signature électronique qualifiée (RGS \*\*) pour avoir valeur légale dans les échanges dématérialisés.

**DINUM (Direction Interministérielle du Numérique)**
Direction de l'État français en charge de la transformation numérique des administrations. Porte La Suite Numérique et les référentiels de souveraineté. Pladigit s'aligne sur ses choix (Collabora, Jitsi souverain).

**La Suite Numérique**
Offre de l'État français (portée par la DINUM) proposant des outils numériques souverains aux agents publics. Pladigit s'inscrit dans la même philosophie et partage plusieurs choix techniques (Collabora Online, Jitsi Meet).

**NAS (dans le contexte collectivités)**
Serveur de stockage réseau physiquement installé dans les locaux de la collectivité. Souvent un Synology ou QNAP. Les agents y accèdent via Windows Explorer (SMB/CIFS). Pladigit se synchronise avec le NAS existant sans obliger la collectivité à migrer ses fichiers.

**Souveraineté numérique**
Capacité d'une organisation à contrôler ses données, ses outils et son infrastructure sans dépendance à des acteurs étrangers ou privés. Pour Pladigit : hébergement en France, code auditable (AGPL-3.0), formats ouverts (ODF), aucun cloud propriétaire.

---

*Pladigit — contact@pladigit.fr — github.com/jpbosse/pladigit — AGPL-3.0*
