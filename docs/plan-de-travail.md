# Plan de travail — Pladigit

> Ordre d'exécution recommandé, toutes tâches confondues.
> Mis à jour : 23 mai 2026.

---

## Légende

| Symbole | Signification |
|---------|---------------|
| 🔴 | Bloquant / sécurité / infrastructure — à faire avant tout déploiement réel |
| 🟠 | Socle fonctionnel — nécessaire pour une démo convaincante |
| 🟡 | Confort et qualité — améliore significativement l'expérience |
| 🟢 | Extensions — fonctionnalités avancées après stabilisation du socle |
| 🔵 | DataPilote — module analytique, après DataGrid stable |
| ⚪ | Documentation / maintenance / opérationnel |

---

## ~~Bloc 0 — Fondations architecturales - TERMINE~~

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| ~~0.1~~ | ~~Migration additive — colonnes `relation_*` + `computed_*` sur `datagrid_columns`~~ | 🔴 | ADR-040 | |
| ~~0.2~~ | ~~Migration — table `datagrid_views`~~ | 🔴 | ADR-040 | |
| ~~0.3~~ | ~~Migration — table `datagrid_folders` + `folder_id` sur `datagrid_tables`~~ | 🔴 | ADR-039 | |
| ~~0.4~~ | ~~Migration — table `datagrid_user_preferences`~~ | 🔴 | ADR-039 | |
| ~~0.5~~ | ~~Enum `DatagridColumnType::RELATION` + `NOM_PERSONNE` + `CHEMIN_FICHIER`~~ | 🔴 | ADR-039/040 | |
| ~~0.6~~ | ~~Service `DatagridNormalizationService` (squelette + détection colonnes répétées/inutiles)~~ | 🔴 | ADR-040 | |

---

## Bloc 1 — Sécurité et infrastructure

### Étape 1-A — Documents et ADR — TERMINÉE

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| ~~1.A.1~~ | ~~Amender ADR-041 §1.1~~ | ⚪ | ADR-041 §1.1 | **FAIT — 2026-05-17** |
| ~~1.A.2~~ | ~~Amender ADR-041 §1.2~~ | ⚪ | ADR-041 §1.2 | **FAIT — 2026-05-17** |
| ~~1.A.3~~ | ~~Créer `docs/deploy/secrets.md`~~ | ⚪ | ADR-041 §1.2 | **FAIT — 2026-05-17** |

### Étape 1-B — Modifications applicatives (code Laravel) — TERMINÉE

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| ~~1.B.1~~ | ~~`CheckSuperAdmin.php` — alerte email + log structuré si tentative depuis IP non autorisée~~ | 🟠 | ADR-027 | **FAIT** |
| ~~1.B.2~~ | ~~Session Super Admin — régénération ID de session après login + timeout inactivité 30 min~~ | 🔴 | — | **FAIT** |
| ~~1.B.3~~ | ~~Wizard `install/index.php` — étape "Sécurité" : génération passphrase GPG~~ | 🔴 | ADR-041 §2.1 | **FAIT** |
| ~~1.B.4~~ | ~~UI Super Admin — afficher résultat vérification SHA-256~~ | 🟠 | ADR-041 §2.2 | **FAIT** |
| ~~1.B.5~~ | ~~Tableau de bord sécurité Super Admin~~ | 🟠 | ADR-041 §11 | **FAIT** |
| ~~1.B.6~~ | ~~Page "Tester la sauvegarde" Super Admin~~ | 🟠 | ADR-041 §8 | **FAIT** |
| ~~1.B.7~~ | ~~Commande artisan `pladigit:purge-audit-logs` + scheduler~~ | 🟡 | ADR-037 | **FAIT** |
| ~~1.B.8~~ | ~~Suppression complète d'un tenant~~ | 🟠 | ADR-037 | **FAIT** |
| ~~1.B.9~~ | ~~Commande artisan `pladigit:delete-tenant --slug=xxx`~~ | 🟠 | ADR-037 | **FAIT** |
| ~~1.B.10~~ | ~~Rate limiting sur déclenchement manuel de sauvegarde~~ | 🟡 | — | **FAIT** |
| ~~1.B.11~~ | ~~Log des exports DataGrid (qui, quoi, quand)~~ | 🟡 | ADR-037 | **FAIT** |
| ~~1.B.12~~ | ~~Purge automatique `audit_logs` — durée max absolue configurable~~ | 🟡 | ADR-037 | **FAIT** |

### Étape 1-C — Script d'installation (`install.sh`) — TERMINÉE

| # | Tâche | Priorité | Remarque |
|---|-------|----------|----------|
| ~~1.C.1~~ | ~~`logrotate.d/nginx` rotation 90 jours~~ | 🟠 | **FAIT** |
| ~~1.C.2~~ | ~~MySQL slow_query_log + log_error dans `mysqld.cnf`~~ | 🟠 | **FAIT** |
| ~~1.C.3~~ | ~~Note explicite sur TDE~~ | ⚪ | **FAIT** |
| ~~1.C.4~~ | ~~Gauge dialog — progression correcte sans superposition de fenêtres~~ | 🟠 | **FAIT — 2026-05-23** |
| ~~1.C.5~~ | ~~Messages de patience sur les étapes longues (apt, PHP, MySQL, Composer, npm, SSL)~~ | 🟠 | **FAIT — 2026-05-23** |
| ~~1.C.6~~ | ~~URL de succès affiche le domaine (pas l'IP)~~ | 🟠 | **FAIT — 2026-05-23** |
| ~~1.C.7~~ | ~~`build_env()` aligné sur `.env.example` — TENANT_DB, SESSION_ENCRYPT, BCRYPT_ROUNDS, REDIS_CLIENT, APP_LOCALE~~ | 🔴 | **FAIT — 2026-05-23** |
| ~~1.C.8~~ | ~~Domaine pré-rempli depuis `config.json` dans le wizard~~ | 🟠 | **FAIT — 2026-05-23** |
| ~~1.C.9~~ | ~~Page welcome — commande d'installation simplifiée pour les non-techniciens~~ | 🟠 | **FAIT — 2026-05-23** |
| 1.C.10 | **BUG** — Workers Supervisor démarrent avant que le wizard écrive le `.env` complet → `config:cache` sans mot de passe → workers FATAL. Fix : désactiver `autostart` dans Supervisor jusqu'à la fin du runner, puis activer via `supervisorctl start` depuis le runner. | 🔴 | **À corriger** |

### Étape 1-D — Tests

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 1.D.1 | Test PHPUnit Feature — isolation cross-tenant | 🔴 | ADR-002 | `tests/Feature/Tenant/TenantIsolationTest.php` |
| 1.D.2 | Test PHPUnit Feature — suppression tenant complète | 🟠 | ADR-037 | Compléter `OrganizationTest.php` |
| 1.D.3 | Test PHPUnit Feature — validation slug organisation | 🟠 | ADR-002 | Vérifier `OrganizationController::store()` |

### Étape 1-E — Tâches déléguées / procédures manuelles

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 1.1 | MySQL InnoDB TDE — chiffrement au repos | 🔴 | ADR-041 §1.1 | Non prérequis au déploiement. Risque documenté dans ADR-041. Contribution communauté bienvenue. |
| 1.4 | Chiffrement GPG du `.env` — archivage sécurisé hors serveur | 🔴 | ADR-041 §1.2 | Automatisé par le runner. Copie à télécharger hors serveur. |
| 1.6 | Test de restauration complète sur VPS de test | 🔴 | ADR-041 §5 | Valider RPO/RTO réels |
| 1.7 | Test de restauration partielle (un tenant) sur VPS de test | 🔴 | ADR-041 §6 | |
| 1.8 | Test de restauration d'un fichier GED | 🟠 | ADR-041 §7 | |
| 1.10 | Checklist sécurité mensuelle | ⚪ | ADR-041 §11 | Remplacée en grande partie par 1.B.5 |

---

## Bloc 1-P — Pentest (audit de sécurité)

*À faire avant tout déploiement chez une collectivité réelle. Jean-Pierre attaque Pladigit comme un hacker — l'application doit tout bloquer.*

| # | Tâche | Priorité | Remarque |
|---|-------|----------|----------|
| P.1 | **OWASP Top 10** — parcours méthodique des 10 catégories appliquées à Pladigit | 🔴 | Référence : owasp.org/Top10 |
| P.2 | **Injection SQL** — tester tous les champs de saisie, filtres DataGrid, import CSV | 🔴 | Outil : sqlmap |
| P.3 | **XSS (Cross-Site Scripting)** — tester les champs texte, noms de colonnes, valeurs importées | 🔴 | Payload : `<script>alert(1)</script>` dans les données |
| P.4 | **CSRF** — vérifier que tous les formulaires sont protégés par token Laravel | 🔴 | Laravel protège par défaut — vérifier les routes API |
| P.5 | **Brute force** — tester le login super admin et le login tenant sans rate limiting | 🔴 | Outil : Hydra ou Burp Suite Intruder |
| P.6 | **Accès inter-tenants** — tenter d'accéder aux données du tenant B depuis une session tenant A | 🔴 | Test d'isolation critique — ADR-002 |
| P.7 | **Upload malveillant** — uploader un fichier CSV avec payload, un PHP déguisé en CSV, un fichier ZIP bomb | 🔴 | Vérifier la validation MIME et l'extension |
| P.8 | **Path traversal** — tenter `../../etc/passwd` dans les paramètres de fichiers GED | 🔴 | |
| P.9 | **Élévation de privilèges** — tenter d'accéder à `/super-admin` depuis un compte tenant | 🔴 | |
| P.10 | **Headers HTTP sécurité** — vérifier CSP, HSTS, X-Frame-Options, X-Content-Type-Options | 🟠 | Outil : securityheaders.com |
| P.11 | **Exposition d'informations** — vérifier que les erreurs 500 n'exposent pas la stack trace en production | 🟠 | `APP_DEBUG=false` + pages d'erreur personnalisées |
| P.12 | **Sessions** — vérifier la régénération d'ID après login, le timeout d'inactivité, la destruction à la déconnexion | 🟠 | |
| P.13 | **Rapport de pentest** — documenter les vulnérabilités trouvées, les corrections appliquées, les risques résiduels | ⚪ | Fichier `docs/security/pentest-report.md` |

*Outils recommandés : OWASP ZAP (gratuit), Burp Suite Community, sqlmap, Nikto, Firefox DevTools.*

---

## ~~Bloc 2 — DataGrid Socle - TERMINE~~

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| ~~2.1~~ | ~~Compteur contextuel "X résultats sur Y total"~~ | 🟠 | ADR-039 §2.4 | **FAIT** |
| ~~2.2~~ | ~~Recherche globale multicolonne~~ | 🟠 | ADR-039 §2.1 | **FAIT** |
| ~~2.3~~ | ~~Gestion des dates Excel~~ | 🟠 | ADR-039 §2.4 | **FAIT** |
| ~~2.4~~ | ~~Ajout manuel d'une ligne~~ | 🟠 | ADR-039 §2.4 | **FAIT** |
| ~~2.5~~ | ~~Organisation des grilles en dossiers~~ | 🟠 | ADR-039 §2.4 | **FAIT** |
| ~~2.6~~ | ~~Type de colonne `CHEMIN_FICHIER`~~ | 🟡 | ADR-039 §2.4 | **FAIT** |
| ~~2.7~~ | ~~Persistance préférences utilisateur~~ | 🟡 | ADR-039 §2.4 | **FAIT** |
| ~~2.8~~ | ~~Export Excel avec avertissement RGPD~~ | 🟠 | ADR-039 §2.3 | **FAIT** |
| ~~2.9~~ | ~~Export PDF + impression d'une fiche~~ | 🟡 | ADR-039 §2.3 | **FAIT** |
| ~~2.10~~ | ~~Popup onglets (Données / Complémentaires / Historique)~~ | 🟠 | ADR-039 §2.5 | **FAIT** |
| ~~2.11~~ | ~~Onglet Historique UI~~ | 🟠 | ADR-039 §2.5 | **FAIT** |
| ~~2.12~~ | ~~Droits UI admin tenant~~ | 🟠 | ADR-039 §2.2 | **FAIT** |
| ~~2.13~~ | ~~Cache Redis des droits résolus~~ | 🟠 | ADR-039 §2.2 | **FAIT** |
| ~~2.14~~ | ~~Droits au niveau colonne~~ | 🟡 | ADR-039 §2.2 | **FAIT** |
| ~~2.15~~ | ~~Création/modification de structure de grille par l'admin tenant~~ | 🟡 | ADR-039 §2.4 | **FAIT** |
| ~~2.16~~ | ~~Tri par défaut configurable par grille~~ | 🟡 | ADR-039 | **FAIT** |

---

## ~~Bloc 3 — DataGrid Qualité des données - TERMINE~~

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| ~~3.1~~ | ~~Type de colonne `NOM_PERSONNE`~~ | 🟠 | ADR-039 §2.1 | **FAIT** |
| ~~3.2~~ | ~~Recherche floue (Levenshtein ≤ 2 + SOUNDS LIKE)~~ | 🟠 | ADR-039 §2.1 | **FAIT** |
| ~~3.3~~ | ~~Détection de doublons à l'import~~ | ✅ | ADR-039 §2.1 | **FAIT** |
| 3.6 | **ADR-043** — Fichiers tableurs GED vs DataGrid : règle d'unicité de source de vérité | 🟢 | ADR-043 | Rédigé — 2026-05-15 |

---

## Bloc 4 — DataGrid Extensions — Relations entre tables

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 4.1 | Interface Super Admin — configuration relation N-1 | 🟢 | ADR-040 §6.1 | |
| 4.2 | Rendu N-1 dans la grille (dropdown → libellé) | 🟢 | ADR-040 §2.2 | |
| 4.3 | Interface Super Admin — configuration relation 1-N | 🟢 | ADR-040 §6.1 | |
| 4.4 | Vue Master/Détail lazy dans la grille | 🟢 | ADR-040 §4.3 | |
| 4.5 | Interface Super Admin — configuration relation N-N | 🟢 | ADR-040 §6.1 | |
| 4.6 | Rendu N-N dans la popup (cases à cocher) | 🟢 | ADR-040 §2.2 | |
| 4.7 | Interface Super Admin — création de vues métier | 🟢 | ADR-040 §6.2 | |
| 4.8 | Colonnes calculées (`computed_sql`) | 🟢 | ADR-040 §5.1 | |
| 4.9 | Colonnes agrégées (`GROUP_CONCAT`) | 🟢 | ADR-040 §5.2 | |
| 4.10 | Popup onglets multi-tables | 🟢 | ADR-040 §4.4 | |
| 4.11 | Sidebar — affichage des vues métier | 🟢 | ADR-040 §4.1 | |
| 4.12 | Avertissement suppression avec lignes liées | 🟢 | ADR-040 §8 | |

---

## Bloc 5 — DataGrid Extensions — Assistant de normalisation

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 5.1 | Wizard Phase 1 — diagnostic automatique du fichier | 🟢 | ADR-040 §3.1 | |
| 5.2 | Wizard Phase 2 — drag & drop groupage des colonnes | 🟢 | ADR-040 §3.2 | |
| 5.3 | Wizard Phase 3 — récapitulatif et validation explicite | 🟢 | ADR-040 §3.3 | |
| 5.4 | Exécution migration — pivot colonnes répétées → lignes | 🟢 | ADR-040 §3.4 | |
| 5.5 | Rapport post-migration avec détection doublons | 🟢 | ADR-040 §3.4 | |

---

## Bloc 6 — DataGrid Extensions — Fonctionnalités avancées

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 6.1 | Champs conditionnels simples | 🟢 | ADR-039 §3.2 | |
| 6.2 | Pièces jointes — intégration GED | 🟢 | ADR-039 §3.3 | |
| 6.3 | Workflow statuts | 🟢 | ADR-039 §3.4 | |
| 6.4 | Validation par responsable | 🟢 | ADR-039 §3.4 | |
| 6.5 | Commentaires internes sur une fiche | 🟢 | ADR-039 §3.5 | |
| 6.6 | Assignation d'une fiche à un agent | 🟢 | ADR-039 §3.5 | |
| 6.7 | Widgets résumés au-dessus de la grille | 🟢 | ADR-039 §3.6 | |
| 6.8 | Virtualisation grands volumes | 🟢 | ADR-039 §3.7 | |
| 6.9 | Tri multi-colonnes UI | 🟢 | ADR-039 §3.7 | |
| 6.10 | En-têtes de colonnes groupés | 🟢 | ADR-039 §3.7 | |
| 6.11 | Verrouillage optimiste | 🟢 | ADR-039 §3.7 | |
| 6.12 | Champs calculés en lecture seule | 🟢 | ADR-039 §3.7 | |
| 6.13 | Duplication d'une fiche | 🟢 | ADR-039 §2.5 | |
| 6.14 | QR code par fiche | 🟢 | ADR-039 §3.7 | |
| 6.15 | Recherche cross-tables | 🟢 | ADR-040 §7 | |

---

## Bloc 7 — Source de vérité documentaire

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 7.1 | Interface classification des documents existants | 🟠 | ADR-038 | |
| 7.2 | Création d'un acte officiel | 🟠 | ADR-038 | |
| 7.3 | Sélection du modèle à la création | 🟠 | ADR-038 | |
| 7.4 | Recherche facettée Type + Date + Service | 🟡 | ADR-038 | |
| 7.5 | Interface admin tenant — configurer modèles et patrons de nommage | 🟡 | ADR-038 | |
| 7.6 | Reset compteur séquentiel (Super Admin) | 🟡 | ADR-038 | |
| 7.7 | Registre des traitements auto-alimenté (RGPD) | 🟡 | ADR-037 | |
| 7.8 | Assistant import RGPD 5 étapes | 🟡 | ADR-037 | |

---

## Bloc 8 — DataPilote

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 8.1 | Bouton "Analyser" → URL `/datagrid/{id}/pilote` | 🔵 | ADR-039 §4.1 | |
| 8.2 | Tableau croisé dynamique | 🔵 | ADR-039 §4.2 | |
| 8.3 | Agrégations | 🔵 | ADR-039 §4.2 | |
| 8.4 | Graphiques Apache ECharts | 🔵 | ADR-039 §4.2 | |
| 8.5 | Export DataPilote | 🔵 | ADR-039 §4.2 | |
| 8.6 | Sauvegarde des configurations DataPilote | 🔵 | ADR-039 §4.3 | |

---

## Bloc 9 — Documentation et maintenance

| # | Tâche | Priorité | Remarque |
|---|-------|----------|----------|
| 9.1 | Mettre à jour l'index des ADR | ⚪ | ADR-039 à 041 manquants |
| 9.2 | Mettre à jour le CHANGELOG | ⚪ | |
| 9.3 | SEO — mots-clés GitHub topics, description repo, README | ⚪ | |
| 9.4 | SEO — méta-tags Open Graph sur `pladigit.fr` | ⚪ | |
| 9.5 | Post "Show HN" (Hacker News) | ⚪ | Après Bloc 2 stable |
| 9.6 | Fiche projet ADULLACT enrichie | ⚪ | Après Bloc 2 stable |
| 9.7 | Outreach collectivités — relance Noirmoutier + nouveaux contacts CDG | ⚪ | Après Bloc 2 stable |
| 9.8 | README — polish final avant communication | ⚪ | |
| 9.9 | CDC et index ADR — session de revue documentation | ⚪ | |
| 9.10 | Checklist sécurité mensuelle (ADR-041 §11) | ⚪ | |
| 9.11 | Test de restauration mensuel (ADR-041 §8) | ⚪ | |
| 9.12 | `SUPER_ADMIN_ALLOWED_IPS` — documentation pour les administrateurs | ⚪ | ADR-027 |
| 9.13 | Guide datagrid utilisateurs — traçabilité historique visible par tous | ⚪ | |
| 9.14 | SEO-Visibility — maximiser la visibilité sur GitHub, ADULLACT, Hacker News | ⚪ | |
| 9.15 | Guide d'installation Ubuntu adapté aux 3 profils (commune, maison des communes, communauté de communes) | ⚪ | Hébergé sur pladigit.fr ou GitHub Pages, mentionné au démarrage de install.sh |
| 9.16 | Documentation HSTS — procédure de test par navigateur (Firefox privé, Brave, Chrome, Edge, Safari) | ⚪ | À inclure dans la doc d'installation |

---

## Récapitulatif — ordre global recommandé

```
Bloc 0   — Fondations architecturales          ✅ TERMINÉ
Bloc 1   — Sécurité et infrastructure          🔄 En cours (1.C.10 + 1.D + 1.E)
Bloc 1-P — Pentest                             🔴 À planifier avant premier tenant réel
Bloc 2   — DataGrid Socle                      ✅ TERMINÉ
Bloc 3   — DataGrid Qualité des données        ✅ TERMINÉ (sauf 3.6)
Bloc 7   — Source de vérité documentaire       (en parallèle de Bloc 4-5)
Bloc 4   — DataGrid Extensions Relations       (après socle stable)
Bloc 5   — Assistant de normalisation          (après relations)
Bloc 6   — DataGrid Extensions avancées        (au fil de l'eau)
Bloc 8   — DataPilote                          (après DataGrid complet)
Bloc 9   — Documentation et communication      (en parallèle, tout au long)
```

**Jalons clés :**

| Jalon | Condition | Objectif |
|-------|-----------|----------|
| 🎯 **Démo DSI** | Blocs 0 + 1 + 2 terminés | Montrer à une collectivité |
| 🎯 **Show HN / ADULLACT** | Blocs 0 + 1 + 2 + 3 terminés | Communication publique |
| 🎯 **Premier tenant réel** | Blocs 0 + 1 + 2 + 3 + 7 + Pentest terminés | Déploiement en conditions réelles |
| 🎯 **DataGrid complet** | Blocs 0 à 6 terminés | Version 2.0 |
| 🎯 **Suite complète** | Blocs 0 à 8 terminés | Version 3.0 |
