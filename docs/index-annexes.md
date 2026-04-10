# Annexes — Index et rôles

Ce document liste toutes les annexes techniques de Pladigit, leur contenu et leur audience cible.

---

## Vue d'ensemble

| Annexe | Titre | Audience | Statut |
|--------|-------|----------|--------|
| [A](annexes/annexe-a-personas.md) | Personas et parcours utilisateurs | Concepteurs, contributeurs UX | ✅ À jour |
| [B](annexes/annexe-b-multitenant.md) | Architecture multi-tenant | Développeurs, déployeurs | ✅ À jour |
| [C](annexes/annexe-c-matrice-droits.md) | Matrice des droits et rôles | Admins, développeurs | ✅ À jour |
| [D](annexes/annexe-d-org-structure.md) | Structure organisationnelle | Admins, développeurs | ✅ À jour |
| [E](annexes/annexe-e-module-phototheque.md) | Module Photothèque | Développeurs | ✅ À jour |
| [F](annexes/annexe-f-politique-nas.md) | Politique de synchronisation NAS | Déployeurs, admins | ✅ À jour |
| [G](annexes/annexe-g-module-ged-collabora.md) | Module GED + Collabora Online | Développeurs, déployeurs | ✅ À jour |
| [K](annexes/annexe-k-cicd.md) | CI/CD et tests automatisés | Développeurs, contributeurs | ✅ À jour |
| [M](annexes/annexe-m-pra.md) | Plan de Reprise d'Activité (PRA) | Opérateurs, admins | ✅ À jour |
| [O](annexes/annexe-o-politique-quotas.md) | Politique de quotas de stockage | Admins, super-admins | ✅ À jour |
| [Q](annexes/annexe-q-succession.md) | Plan de succession et continuité | Repreneur potentiel | ✅ À jour |
| [T](annexes/annexe-t-gestion-projet.md) | Module Gestion de projet | Développeurs | ✅ À jour |

---

## Détail par annexe

### Annexe A — Personas et parcours utilisateurs
Présente les quatre profils types d'utilisateurs de Pladigit : Marie (secrétaire de mairie), Jean-Pierre (maire), Julie (responsable technique / admin organisation), Amadou (responsable de service technique). Chaque persona illustre un parcours type concret. Utile pour guider les choix de conception d'interface et comprendre les besoins réels du terrain.

### Annexe B — Architecture multi-tenant
Documente le modèle multi-tenant custom de Pladigit : base MySQL dédiée par organisation, fonctionnement du `TenantManager`, résolution du tenant depuis le sous-domaine, garanties d'isolation, provisionnement automatique. Référence indispensable pour tout développeur ou déployeur.

### Annexe C — Matrice des droits et rôles
Tableau complet des permissions par rôle (Admin, Président, DGS, Resp. Direction, Resp. Service, Agent) sur toutes les actions de la plateforme. Couvre la GED, la photothèque, les projets, l'administration. Référence pour configurer les droits d'une nouvelle organisation.

### Annexe D — Structure organisationnelle
Documente la hiérarchie Directions > Services > Agents, le modèle de données associé, les règles métier (un service a obligatoirement une direction parente), et l'interface d'administration `/admin/departments`.

### Annexe E — Module Photothèque
Architecture technique complète de la photothèque : modèle de données, service `MediaService`, gestion des uploads asynchrones, déduplication SHA-256, extraction EXIF, watermark, streaming HTTP range, droits par album, synchronisation NAS. Référence pour les développeurs travaillant sur le module MEDIA.

### Annexe F — Politique de synchronisation NAS
Décrit la politique de synchronisation entre le NAS physique et Pladigit pour la photothèque : drivers disponibles (local, SFTP, SMB), comportement de la commande `nas:sync`, gestion des conflits, fichiers ignorés, planification. Utile pour les administrateurs configurant la connexion NAS.

### Annexe G — Module GED + Collabora Online
Documentation technique complète des modules GED et Collabora : arborescence de dossiers, système de permissions héritées (`GedPermissionLevel`), `GedStorageInterface` et ses trois drivers, synchronisation NAS → GED, versioning, intégration GED↔Projets, protocole WOPI complet (CheckFileInfo, GetFile, PutFile, locks), token multi-tenant, gouvernance admin, recherche FULLTEXT. Référence principale pour les développeurs travaillant sur ces modules.

### Annexe K — CI/CD et tests automatisés
Pipeline GitHub Actions, règles qualité (PHPStan niveau 5, Pint PSR-12, 0 vulnérabilité), architecture des tests (2 vraies bases MySQL, pas de mocks), configuration `phpunit.xml`, gestion des tests LDAP exclus du CI. Référence pour les contributeurs souhaitant faire tourner les tests ou comprendre le pipeline.

### Annexe M — Plan de Reprise d'Activité (PRA)
Objectifs et indicateurs (RTO/RPO), architecture de sauvegarde, procédures de reprise pour 3 scénarios (panne serveur, corruption base, compromission sécurité), surveillance et health check, test PRA semestriel. Référence pour les opérateurs en charge de la production.

### Annexe O — Politique de quotas de stockage
Définition du quota par organisation, ce qui est compté, enforcement à l'upload et à la synchronisation NAS, paliers d'alerte (80/90/95%), affichage dans l'interface, actions disponibles en cas de dépassement. Référence pour les super-admins gérant l'espace disque.

### Annexe Q — Plan de succession et continuité projet
Plan garantissant qu'un tiers peut reprendre le projet en cas d'indisponibilité du développeur principal. Localisation de tous les actifs (code, .env, clés, certificats), guide de reprise en 4 étapes, options stratégiques (fork communautaire, repreneur individuel, archivage). Document essentiel pour un projet porté par un développeur unique.

### Annexe T — Module Gestion de projet
Architecture technique complète du module projets : ADR-008 à ADR-011, migrations MySQL, 15 contrôleurs, modèle de données (projects, milestones, tasks, dépendances, budget, risques), trois couches de droits cumulatives, cinq vues de planification (Liste, Kanban, Gantt, Agenda, Charge), export PDF/iCal, modèles de projet, historique d'activité. Référence pour les développeurs travaillant sur le module PROJECTS.

---

## Lettres disponibles pour de futures annexes

Les lettres suivantes sont disponibles pour les prochaines annexes : **H, I, J, L, N, P, R, S, U, V, W, X, Y, Z**

Annexes prévues :
- **Annexe H** — Module Chat (temps réel, WebSocket, Soketi) — *à créer lors du développement*
- **Annexe I** — Module Agenda global (CalDAV, iCal) — *à créer lors du développement*
- **Annexe J** — DataGrid + DataPilot — *à créer lors du développement*
- **Annexe L** — Signature électronique + PKI — *à créer lors du développement*

---

*Pladigit — contact@pladigit.fr — github.com/jpbosse/pladigit — AGPL-3.0*
