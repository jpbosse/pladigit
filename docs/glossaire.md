# Glossaire — Pladigit

Ce glossaire définit les termes techniques et métier utilisés dans la documentation de Pladigit. Les termes sont classés par catégorie.

---

## Termes métier — collectivités territoriales

**Agent**
Tout employé d'une collectivité territoriale qui n'exerce pas de fonction d'encadrement. Dans Pladigit, le rôle Agent est le niveau de droits le plus restreint : accès aux modules et dossiers auxquels son service a accès.

**CalDAV**
Protocole standard d'échange de données d'agenda, basé sur WebDAV. Permet la synchronisation d'un agenda Pladigit avec des clients de messagerie comme Thunderbird ou des smartphones. Prévu dans la roadmap niveau 2 pour remplacer l'agenda Outlook.

**CDG (Centre de Gestion)**
Structure publique qui assiste les collectivités territoriales de son département sur les questions de gestion des ressources humaines, de formation, de conseil juridique et de mutualisation informatique. Les CDG sont des partenaires naturels pour le déploiement mutualisé de Pladigit (une installation pour plusieurs communes).

**DGS (Directeur Général des Services)**
Agent de catégorie A (attaché ou attaché principal) détaché sur un emploi fonctionnel, nommé dans les communes de plus de 2 000 habitants. Le DGS est responsable de la coordination des services de la collectivité et de la mise en œuvre des décisions du conseil municipal. Dans Pladigit, le rôle DGS donne accès à l'ensemble des modules et tableaux de bord de synthèse.

**Direction**
Premier niveau de l'organigramme d'une collectivité dans Pladigit. Une direction regroupe plusieurs services (exemple : Direction des Services Techniques, Direction des Affaires Culturelles). Les droits d'accès aux documents et projets s'appliquent au niveau de la direction ou du service.

**MAPA (Marché à Procédure Adaptée)**
Marché public dont le montant est inférieur aux seuils européens (actuellement 40 000 € HT pour les fournitures et services courants). Les MAPA représentent la grande majorité des achats des petites communes. Le module Gestion de projet de Pladigit est conçu pour accompagner le suivi de ces marchés.

**ODF (OpenDocument Format)**
Format de fichier bureautique standardisé (ISO/IEC 26300), utilisé nativement par LibreOffice et Collabora Online. ODT pour les documents texte, ODS pour les tableurs, ODP pour les présentations. Garantit la lisibilité à long terme des archives municipales, indépendamment de l'éditeur logiciel.

**RGPD (Règlement Général sur la Protection des Données)**
Règlement européen (2016/679) entré en vigueur en mai 2018, qui encadre le traitement des données personnelles. Les collectivités territoriales sont soumises au RGPD et doivent tenir un registre des traitements, désigner un délégué à la protection des données (DPD ou DPO), et garantir la sécurité des données de leurs agents et administrés.

**SGM (Secrétaire Général de Mairie)**
Appellation officielle depuis la loi du 30 décembre 2023 (anciennement secrétaire de mairie). Agent de catégorie B ou A selon la strate de la commune, nommé par arrêté du maire. Le SGM coordonne l'administration générale de la mairie. Dans les communes de moins de 2 000 habitants, il est souvent l'interlocuteur principal pour l'adoption de Pladigit.

**Syndicat informatique territorial**
Structure publique mutualisée qui fournit des services informatiques à un ensemble de collectivités adhérentes (exemple : SICTIAM en PACA, SIRES en Normandie). Comme les CDG, les syndicats informatiques sont des partenaires privilégiés pour le déploiement de Pladigit à l'échelle d'un territoire.

---

## Sécurité et conformité

**AIPD (Analyse d'Impact relative à la Protection des Données)**
Étude obligatoire sous RGPD pour les traitements susceptibles d'engendrer un risque élevé pour les personnes concernées. Pladigit facilite l'AIPD grâce à son audit trail complet, son hébergement maîtrisé et son code source auditable.

**AES-256**
Algorithme de chiffrement symétrique utilisé par Pladigit pour protéger les secrets TOTP et les codes de secours stockés en base de données. AES-256 est le standard recommandé par les organismes gouvernementaux pour les données sensibles.

**ANSSI (Agence Nationale de la Sécurité des Systèmes d'Information)**
Autorité nationale française en matière de cybersécurité. L'ANSSI publie des recommandations et des guides à destination des collectivités territoriales. En 2023, l'ANSSI a signalé une hausse de 47 % des incidents sur les collectivités, dont 73 % sur des structures de moins de 20 000 habitants.

**bcrypt**
Algorithme de hachage des mots de passe utilisé par Pladigit (coût 12). Contrairement au chiffrement réversible, le hachage est à sens unique : un mot de passe haché ne peut pas être retrouvé par calcul. Le paramètre de coût (12) rend le calcul intentionnellement lent, ce qui rend les attaques par force brute économiquement inenvisageables.

**Cloud Act**
Loi américaine (Clarifying Lawful Overseas Use of Data Act, 2018) autorisant les autorités américaines à accéder aux données hébergées par des entreprises américaines, même si ces données sont physiquement stockées hors des États-Unis. Microsoft, Google et Amazon sont soumis au Cloud Act. Pladigit hébergé en France ou en Europe n'est pas concerné.

**Fail2ban**
Outil Linux qui surveille les journaux système et bannit automatiquement les adresses réseau qui présentent des comportements suspects (tentatives de connexion répétées, attaques par force brute). Intégré dans les déploiements Pladigit en production.

**LDAPS**
Protocole LDAP (protocole d'annuaire) sécurisé par TLS. Pladigit n'autorise que les connexions LDAPS — la connexion LDAP non chiffrée est refusée (ADR-005). LDAP (port 389) transmet les identifiants en clair sur le réseau, ce qui est inacceptable en environnement de production.

**SecNumCloud**
Qualification délivrée par l'ANSSI pour les prestataires de services en nuage (cloud) qui respectent un référentiel de sécurité exigeant. Pladigit ne dispose pas encore de cette qualification (v0.8), mais son hébergement auto-géré en France ou en Europe offre une alternative conforme aux exigences des collectivités.

**TOTP (Time-based One-Time Password)**
Protocole de double authentification défini par la RFC 6238. Génère un code à 6 chiffres valable 30 secondes, calculé à partir d'un secret partagé et de l'heure courante. Compatible avec Google Authenticator, Aegis, Authy et Microsoft Authenticator. Pladigit utilise TOTP plutôt que SMS pour éviter les vulnérabilités liées aux opérateurs téléphoniques (ADR-017).

**UFW (Uncomplicated Firewall)**
Outil de gestion des règles de pare-feu sous Linux (Ubuntu). Permet de définir simplement quels ports réseau sont accessibles. Les déploiements Pladigit en production l'utilisent pour n'exposer que les ports nécessaires (80/443 pour le web, 22 pour SSH).

---

## Architecture & technique

**ADR (Architecture Decision Record)**
Fiche de décision architecturale : document qui enregistre un choix technique important, son contexte, les alternatives considérées et les conséquences. Pladigit dispose de 31 ADR dans `docs/adr/` (ADR-001 à ADR-031). Permet à tout contributeur ou prestataire de comprendre *pourquoi* un choix a été fait, pas seulement *quoi*.

**Alpine.js**
Framework JavaScript léger utilisé pour les interactions frontend (modales, drag & drop, upload progressif). Complémentaire à Livewire — Alpine.js gère les interactions purement côté client, Livewire gère les composants réactifs côté serveur.

**Apache ECharts**
Bibliothèque JavaScript de visualisation de données (licence Apache 2.0 / MIT), retenue pour le module DataPilote. Produit des graphiques interactifs (barres, courbes, secteurs, combinés) avec un rendu de qualité professionnelle. Choisie pour sa licence open source compatible AGPL-3.0, son absence de dépendances et la richesse de son API.

**DataGrid Relationnel**
Extension du module DataGrid permettant de définir des relations entre plusieurs tables d'un même tenant. Exemple : une table Élus reliée à une table Commissions via une colonne de type `RELATION`. Transforme le DataGrid en véritable applicatif métier no-code, comparable à Airtable ou NocoDB mais souverain. Développement prévu dans le bloc Extensions (ADR-039).

**Artisan**
Interface en ligne de commande de Laravel. Permet d'exécuter des commandes de maintenance, migrations, tests, synchronisations NAS, etc. Exemple : `php artisan ged:sync`.

**Base platform**
Base MySQL centrale (`pladigit_platform`) contenant uniquement la table `organizations` avec les informations de connexion à chaque base organisation. Ne contient jamais de données utilisateurs.

**Base tenant (base organisation)**
Base MySQL dédiée à une organisation (`pladigit_{slug}`). Contient toutes les données de l'organisation : utilisateurs, documents, photos, projets, journaux d'audit. Totalement isolée des autres organisations.

**CI/CD (Continuous Integration / Continuous Deployment — Intégration Continue / Déploiement Continu)**
Pipeline automatisé déclenché à chaque mise à jour du code sur GitHub. Exécute 4 vérifications : PHPUnit (tests), Pint (style de code), PHPStan (analyse statique), Composer audit (sécurité des dépendances). Aucun merge sur `main` si une vérification échoue.

**DataGrid**
Module Pladigit permettant aux agents d'une collectivité de consulter, saisir, filtrer et exporter des données structurées sous forme de grille, sans écrire de SQL ni de code. Équivalent souverain des tableurs Excel collaboratifs. La structure (colonnes, types) est définie par le Super Admin ; les données sont gérées par l'admin tenant et les agents.

**DataPilote**
Module analytique de Pladigit, accessible depuis le DataGrid via un bouton "Analyser". Permet de construire des tableaux croisés dynamiques et des graphiques à partir des données d'une grille, par simple configuration (drag & drop). Lecture seule — aucune modification de données depuis le DataPilote.

**DDL (Data Definition Language — Langage de Définition des Données)**
Sous-ensemble du langage SQL qui définit ou modifie la *structure* d'une base de données. Les instructions DDL sont : `CREATE TABLE` (créer une table), `ALTER TABLE` (ajouter ou modifier une colonne), `DROP TABLE` (supprimer une table). Dans Pladigit, le DDL est réservé exclusivement au Super Admin — aucun utilisateur ni admin tenant ne peut exécuter du DDL directement. Une erreur de DDL peut corrompre toute une base de données de façon irréversible.

**DML (Data Manipulation Language — Langage de Manipulation des Données)**
Sous-ensemble du SQL qui agit sur les *données* (et non la structure). Les instructions DML sont : `SELECT` (lire), `INSERT` (ajouter), `UPDATE` (modifier), `DELETE` (supprimer). Les agents Pladigit exécutent du DML à travers l'interface — sans jamais l'écrire directement.

**Distance de Levenshtein**
Mesure de la différence entre deux chaînes de caractères, exprimée en nombre minimal d'opérations (insertion, suppression, substitution) nécessaires pour passer de l'une à l'autre. Exemple : la distance entre "Dupont" et "Dupond" est 1 (une substitution). Utilisée dans Pladigit pour la recherche floue sur les noms de personnes — permet de retrouver "Dupont" même si saisi "Dupond" ou "Dupon".

**Fuzzy search (recherche floue)**
Technique de recherche qui accepte des variantes orthographiques du terme cherché. Contrairement à une recherche exacte, la recherche floue retrouve "Jean Dupont" si l'utilisateur tape "Dupond" ou "J. Dupont". Dans Pladigit DataGrid, activée sur les colonnes explicitement marquées `fuzzy_search = true` par le Super Admin.

**Numéro sériel Excel**
Représentation interne des dates dans Microsoft Excel : un nombre entier comptant les jours depuis le 1er janvier 1900 (avec un bug historique sur l'année 1900 conservé par compatibilité). Exemple : le nombre 46000 correspond au 26 mars 2025. Lors de l'import d'un fichier Excel dans DataGrid, Pladigit détecte et convertit automatiquement ces nombres en dates lisibles pour les colonnes déclarées de type `DATE`.

**Circuit breaker LDAP**
Mécanisme de protection qui coupe temporairement les tentatives de connexion à l'annuaire après un certain nombre d'échecs consécutifs. Évite qu'une panne de l'annuaire ne bloque tous les utilisateurs — le basculement sur l'authentification locale prend le relais.

**Driver NAS**
Implémentation concrète d'un protocole de stockage. Pladigit propose trois drivers : `local` (disque local du serveur), `sftp` (NAS Linux/FreeBSD), `smb` (NAS Windows/Samba). Le driver actif est configuré par organisation dans `TenantSettings`.

**Eloquent**
ORM (Object-Relational Mapping — correspondance objet-relationnel) de Laravel. Permet d'interagir avec la base de données via des objets PHP plutôt que des requêtes SQL brutes. Les modèles Pladigit (`GedDocument`, `MediaItem`, `Project`) sont des classes Eloquent.

**GedStorageInterface**
Interface PHP définissant le contrat commun pour tous les drivers de stockage GED (`put`, `get`, `delete`, `exists`, `listDirectory`). Le code métier n'interagit jamais directement avec un driver concret — il injecte `GedStorageInterface`. Permet de changer de backend de stockage sans toucher au code applicatif (ADR-020).

**Job / Queue (file de tâches)**
Tâche asynchrone exécutée en arrière-plan par un worker. Utilisé pour les traitements longs : upload de photos, import ZIP, synchronisation NAS, export. Évite de bloquer la requête HTTP de l'utilisateur pendant le traitement.

**Laravel**
Framework PHP MVC utilisé comme socle de Pladigit. Version 11.x. Fournit le routage, l'ORM Eloquent, les migrations de base de données, la gestion des files de tâches, le planificateur de tâches (scheduler) et l'injection de dépendances.

**Livewire**
Framework Laravel permettant de construire des interfaces réactives sans écrire de JavaScript. Les composants Livewire s'exécutent côté serveur et communiquent via AJAX avec le navigateur. Version 4.2 dans Pladigit.

**Middleware**
Code exécuté avant qu'une requête HTTP atteigne le contrôleur. Pladigit utilise notamment `ResolveTenant` (résolution de l'organisation depuis le sous-domaine), `RequireModule` (vérification qu'un module est activé), `CheckRole` (vérification du rôle utilisateur).

**Migration**
Script PHP versionné qui décrit une modification de schéma de base de données. Pladigit sépare les migrations en deux répertoires : `database/migrations/platform/` (base centrale) et `database/migrations/` (bases organisations). Les deux doivent être exécutées lors de l'installation.

**Multi-organisation (multi-tenant)**
Architecture où une seule application sert plusieurs organisations indépendantes, chacune avec ses propres données isolées. Pladigit utilise une base MySQL dédiée par organisation. Un syndicat informatique peut ainsi gérer 30 communes sur une seule installation Pladigit.

**NasManager**
Service Laravel qui instancie le bon driver de stockage selon la configuration de l'organisation (local, SFTP, SMB). Fonctionne pour la photothèque (`NasManager::mediaDriver()`) et pour la GED (`NasManager::gedDriver()`).

**ODF → voir** *termes métier*

**PHPStan**
Outil d'analyse statique PHP. Pladigit exige le niveau 5 (0 erreur tolérée). Détecte les erreurs de typage, les variables indéfinies, les appels de méthodes incorrects — sans exécuter le code.

**Pint**
Outil de formatage PHP de Laravel. Applique automatiquement le standard PSR-12 (convention de style de code PHP). Exécuté automatiquement sur chaque mise à jour via la CI/CD.

**Redis**
Base de données en mémoire utilisée pour le cache, les sessions et la file de tâches. Plus rapide que MySQL pour ces usages. Requis en production.

**Tabulator**
Bibliothèque JavaScript de grilles de données (licence MIT), retenue pour le module DataGrid (ADR-036). Fournit nativement le tri multi-colonnes, la virtualisation des lignes pour les grands volumes, le mode server-side, le Master/Détail et les en-têtes groupés. Choisie pour sa licence open source, l'absence de jQuery, et la richesse fonctionnelle de sa version Community (contrairement à AG Grid dont les fonctionnalités avancées sont réservées à la licence Enterprise payante).

**ResolveTenant**
Middleware qui s'exécute en premier sur chaque requête. Extrait l'identifiant de l'organisation depuis le sous-domaine (exemple : `mairie-soullans.pladigit.fr` → slug `mairie-soullans`), charge l'organisation depuis la base platform, puis établit la connexion à la base de données de l'organisation.

**Scheduler (planificateur de tâches)**
Composant Laravel qui déclenche des commandes à intervalles réguliers : synchronisation NAS toutes les heures, génération des tâches récurrentes quotidiennement, purge des données expirées. Nécessite un cron système : `* * * * * php artisan schedule:run`.

**SHA-256**
Algorithme de hachage cryptographique utilisé pour la déduplication des fichiers dans la photothèque. Chaque fichier est identifié par son empreinte SHA-256 — deux fichiers identiques ont la même empreinte et ne sont stockés qu'une seule fois sur le disque, même s'ils apparaissent dans des albums différents (ADR-013).

**Soft delete (suppression logique)**
Suppression qui ne retire pas physiquement l'enregistrement de la base de données, mais le marque avec une date de suppression. Permet de restaurer des éléments supprimés par erreur. Utilisé sur les projets, tâches, photos et documents GED.

**Supervisor**
Gestionnaire de processus Linux qui maintient les workers de files de tâches Laravel actifs en permanence. Redémarre automatiquement les workers en cas d'arrêt inattendu.

**Versioning (gestion des versions)**
Mécanisme qui conserve l'historique de toutes les versions d'un document. Dans la GED Pladigit, chaque modification d'un document crée une nouvelle version horodatée et attribuée à l'utilisateur, sans supprimer les versions précédentes. Permet de restaurer n'importe quelle version antérieure.

**WOPI (Web Application Open Platform Interface)**
Protocole standard (Microsoft) permettant à un éditeur bureautique (ici Collabora Online) d'ouvrir, modifier et enregistrer des fichiers hébergés sur un autre serveur (ici la GED Pladigit). Pladigit implémente les endpoints WOPI complets : CheckFileInfo, GetFile, PutFile, Lock, Unlock, RefreshLock, GetLock (ADR-021 à ADR-024).
