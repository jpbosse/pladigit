# Plan de travail — Pladigit

> Ordre d'exécution recommandé, toutes tâches confondues.
> Mis à jour : 17 mai 2026.

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
*À poser maintenant, avant tout développement fonctionnel.
Ces migrations sont additives — elles n'impactent pas l'existant.*

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| ~~0.1~~ | ~~Migration additive — colonnes `relation_*` + `computed_*` sur `datagrid_columns`~~ | 🔴 | ADR-040 | Socle relations — doit précéder tout développement Extensions |
| ~~0.2~~ | ~~Migration — table `datagrid_views`~~ | 🔴 | ADR-040 | Socle vues métier transparentes |
| ~~0.3~~ | ~~Migration — table `datagrid_folders` + `folder_id` sur `datagrid_tables`~~ | 🔴 | ADR-039 | Organisation sidebar en dossiers |
| ~~0.4~~ | ~~Migration — table `datagrid_user_preferences`~~ | 🔴 | ADR-039 | Persistance colonnes visibles, per_page |
| ~~0.5~~ | ~~Enum `DatagridColumnType::RELATION` + `NOM_PERSONNE` + `CHEMIN_FICHIER`~~ | 🔴 | ADR-039/040 | Nouveaux types — ne génèrent aucun comportement seuls |
| ~~0.6~~ | ~~Service `DatagridNormalizationService` (squelette + détection colonnes répétées/inutiles)~~ | 🔴 | ADR-040 | Réutilisé par l'assistant IA futur |


---

## Bloc 1 — Sécurité et infrastructure

*À implémenter avant tout déploiement chez une collectivité réelle.*

### Étape 1-A — Documents et ADR

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| ~~1.A.1~~ | ~~Amender ADR-041 §1.1~~ — TDE délégué prestataire/communauté OS, appel à contribution, risque résiduel documenté et assumé | ⚪ | ADR-041 §1.1 | **FAIT — 2026-05-17** |
| ~~1.A.2~~ | ~~Amender ADR-041 §1.2~~ — GPG `.env` automatisé par le wizard (même passphrase que les sauvegardes, même runner) | ⚪ | ADR-041 §1.2 | **FAIT — 2026-05-17** |
| ~~1.A.3~~ | ~~Créer `docs/deploy/secrets.md`~~ — procédures de gestion des secrets, déchiffrement d'urgence, à deux emplacements obligatoires | ⚪ | ADR-041 §1.2 | **FAIT — 2026-05-17** |

### Étape 1-B — Modifications applicatives (code Laravel)

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 1.B.1 | `CheckSuperAdmin.php` — alerte email + log structuré si tentative depuis IP non autorisée | 🟠 | ADR-027 | ~20 lignes ; Mail::to() + Log::warning() |
| 1.B.2 | Session Super Admin — régénération ID de session après login + timeout inactivité 30 min | 🔴 | — | Vérifier `AuthController` ; session()->regenerate() + middleware |
| 1.B.3 | Wizard `install/index.php` — ajouter étape "Sécurité" : génération passphrase GPG, encadré bloquant avec confirmation en 3 points, activation GPG par défaut, écriture en base + chiffrement `.env` par le runner | 🔴 | ADR-041 §2.1 | Remplace "UI Super Admin activer GPG" — le GPG est activé à l'install, pas en option après |
| 1.B.4 | UI Super Admin — afficher résultat vérification SHA-256 à la demande (code `BackupService` déjà prêt) | 🟠 | ADR-041 §2.2 | Bouton "Vérifier l'intégrité" sur la liste des sauvegardes |
| 1.B.5 | Tableau de bord sécurité Super Admin — état GPG, dernière sauvegarde, workers, dernier test de restauration, clés SSH autorisées, version PHP/Laravel | 🟠 | ADR-041 §11 | Intégrer `CheckTdeCommand` existant ; remplace la checklist manuelle |
| 1.B.6 | Page "Tester la sauvegarde" Super Admin — déchiffrer, vérifier SHA-256, lister le contenu de l'archive sans restaurer ; écrire dans le journal de tests | 🟠 | ADR-041 §8 | Couvre 1.6 partiel, 1.7 partiel, 1.9 |
| 1.B.7 | Commande artisan `pladigit:purge-audit-logs` + scheduler — durée configurable dans PlatformSettings (RGPD) | 🟡 | ADR-037 | Vérifier overlap avec `PurgeExpiredDataCommand` avant de créer |
| 1.B.8 | Suppression complète d'un tenant — compléter `destroy()` : ajouter suppression fichiers GED + sauvegardes locales (la base MySQL et le soft-delete existent déjà) | 🟠 | ADR-037 | Trou identifié : `storage/app/private/ged/organisations/{slug}/` non supprimé |
| 1.B.9 | Commande artisan `pladigit:delete-tenant --slug=xxx` — suppression complète (base + GED + sauvegardes) avec confirmation explicite | 🟠 | ADR-037 | Complète 1.B.8 pour usage CLI prestataire |
| 1.B.10 | Rate limiting sur déclenchement manuel de sauvegarde — éviter saturation disque | 🟡 | — | 1 sauvegarde manuelle / 10 min par org |
| 1.B.11 | Log des exports DataGrid (qui, quoi, quand) — RGPD, registre des traitements | 🟡 | ADR-037 | Chaque export Excel/PDF loggé dans `datagrid_audit_logs` |
| 1.B.12 | Purge automatique `audit_logs` — durée max absolue configurable (ex : 5 ans) indépendante de la rétention courante | 🟡 | ADR-037 | Complète 1.B.7 |

### Étape 1-C — Script d'installation (`install.sh`)

| # | Tâche | Priorité | Remarque |
|---|-------|----------|----------|
| 1.C.1 | Ajouter dans `install.sh` : `logrotate.d/nginx` rotation 90 jours (en-têtes HTTP sécurité déjà présents lignes 637-642) | 🟠 | `logrotate.d/nginx` — daily, rotate 90, compress |
| 1.C.2 | Ajouter dans `install.sh` : MySQL slow_query_log + log_error dans `mysqld.cnf` | 🟠 | `slow_query_log=1`, `long_query_time=2`, `log_error` |
| 1.C.3 | Ajouter dans `install.sh` : note explicite sur TDE (non automatisé — voir ADR-041 §1.1 et contribution communauté) | ⚪ | Message informatif en fin d'install |

### Étape 1-D — Tests

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 1.D.1 | Test PHPUnit Feature — isolation cross-tenant : un user tenant A ne peut pas accéder aux routes ni aux données du tenant B via DataGrid | 🔴 | ADR-002 | Nouveau fichier `tests/Feature/Tenant/TenantIsolationTest.php` |
| 1.D.2 | Test PHPUnit Feature — suppression tenant complète : vérifier que base, GED et sauvegardes sont bien supprimés | 🟠 | ADR-037 | Compléter `OrganizationTest.php` |
| 1.D.3 | Test PHPUnit Feature — validation slug organisation : caractères dangereux rejetés pour le nom de base MySQL | 🟠 | ADR-002 | Vérifier la validation existante dans `OrganizationController::store()` |

### Étape 1-E — Tâches déléguées / procédures manuelles (hors app)

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 1.1 | MySQL InnoDB TDE — chiffrement au repos | 🔴 | ADR-041 §1.1 | **Non prérequis au déploiement.** Risque résiduel (vol physique disque) documenté et assumé dans ADR-041. À faire par un administrateur système qualifié si disponible. Contribution communauté open source bienvenue (`CONTRIBUTING.md` — label `help wanted / security`). |
| 1.4 | Chiffrement GPG du `.env` — archivage sécurisé hors serveur | 🔴 | ADR-041 §1.2 | **Automatisé par le runner d'installation** (même passphrase que les sauvegardes). Copie à télécharger hors serveur — rappelé sur la page de succès du wizard. |
| 1.6 | Test de restauration complète sur VPS de test | 🔴 | ADR-041 §5 | Valider RPO/RTO réels — ne peut pas être automatisé dans l'app |
| 1.7 | Test de restauration partielle (un tenant) sur VPS de test | 🔴 | ADR-041 §6 | Idem — procédure documentée dans ADR-041 |
| 1.8 | Test de restauration d'un fichier GED | 🟠 | ADR-041 §7 | Idem |
| 1.10 | Checklist sécurité mensuelle — planifier dans l'agenda | ⚪ | ADR-041 §11 | Remplacée en grande partie par le tableau de bord 1.B.5 |

---

~~## Bloc 2 — DataGrid Socle (fonctionnalités utilisateur) - ~~
*Ce bloc seul suffit pour une démo convaincante auprès d'une DSI.*

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| ~~2.1~~ | ~~Compteur contextuel "X résultats sur Y total" - TERMINE~~| 🟠 | ADR-039 §2.4 | 1h de travail |
| ~~2.2~~ | ~~Recherche globale multicolonne (1 champ → toutes colonnes texte visibles) - TERMINE~~ | 🟠 | ADR-039 §2.1 | Impact immédiat pour l'utilisateur |
| ~~2.3~~ | ~~Gestion des dates Excel (numéros sériels → dates ISO) à l'import - TERMINE~~ | 🟠 | ADR-039 §2.4 | Fréquent dans les fichiers collectivités |
| ~~2.4~~ | ~~Ajout manuel d'une ligne (bouton + popup vide) - TERMINE~~| 🟠 | ADR-039 §2.4 | Valeurs par défaut par colonne |
| ~~2.5~~ | ~~Organisation des grilles en dossiers — sidebar collapse/expand - TERMINE~~ | 🟠 | ADR-039 §2.4 | Utilise migration 0.3 |
| ~~2.6~~ | ~~Type de colonne `CHEMIN_FICHIER` — icône PDF/image/fichier cliquable - TERMINE~~| 🟡 | ADR-039 §2.4 | Cas arrêtés sur NAS |
| ~~2.7~~ | ~~Persistance préférences utilisateur (colonnes visibles, per_page) - TERMINE~~ | 🟡 | ADR-039 §2.4 | Utilise migration 0.4 |
| ~~2.8~~ | ~~Export Excel (PhpSpreadsheet) avec avertissement RGPD - TERMINE~~| 🟠 | ADR-039 §2.3 | — |
| ~~2.9~~ | ~~Export PDF + impression d'une fiche - TERMINE~~ | 🟡 | ADR-039 §2.3 | dompdf |
| ~~2.10~~ | ~~Popup onglets (Données / Complémentaires / Historique) - TERMINE~~ | 🟠 | ADR-039 §2.5 | — |
| ~~2.11~~ | ~~Onglet Historique UI — qui a changé quoi dans la fiche - TERMINE~~| 🟠 | ADR-039 §2.5 | Données dans `datagrid_audit_logs` — UI manque |
| ~~2.12~~ | ~~Droits UI admin tenant — par département et utilisateur - TERMINE~~| 🟠 | ADR-039 §2.2 | — |
| ~~2.13~~ | ~~Cache Redis des droits résolus (`datagrid_perm:{tenant}:{user}:{table}`) - TERMINE~~ | 🟠 | ADR-039 §2.2 | Invalidation sur modif hiérarchie |
| ~~2.14~~ | ~~Droits au niveau colonne (masquer colonne selon service) - TERMINE~~ | 🟡 | ADR-039 §2.2 | Ex : colonne Salaire → RH uniquement |
| ~~2.15~~ | ~~Création/modification de structure de grille par l'admin tenant - TERMINE~~| 🟡 | ADR-039 §2.4 | Renommer, réordonner — DDL réservé Super Admin |
| ~~2.16~~ | ~~Tri par défaut configurable par grille - TERMINE~~ | 🟡 | ADR-039 | — |
| ~~2.17~~ | ~~Colonne numéro de ligne (optionnelle)~~ — *supprimé : colonne ordinaire TEXT ou NUMBER créée par le Super Admin selon le format de la collectivité* | ~~🟡~~ | ADR-039 | — |

---

~~## Bloc 3 — DataGrid Qualité des données - TERMINE~~
*Recherche floue et gestion des doublons — différenciant fort.*

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| ~~3.1~~ | ~~Type de colonne `NOM_PERSONNE` dans l'enum et l'UI -TERMINE~~ | 🟠 | ADR-039 §2.1 | Sous-type de TEXT |
| ~~3.2~~ | ~~Recherche floue (Levenshtein ≤ 2 + SOUNDS LIKE) sur colonnes `NOM_PERSONNE` - TERMINE~~ | 🟠 | ADR-039 §2.1 | Activé sur `fuzzy_search = true` uniquement |
| ~~3.3~~ | ~~Détection de doublons à l'import — avertissement avec contexte prénom/ville, seuil adaptatif Levenshtein, filtre abréviations — TERMINÉ~~ | ✅ | ADR-039 §2.1 | Approche : avertir et laisser l'utilisateur corriger son fichier source |
| ~~3.4~~ | ~~Interface de fusion de doublons~~ — *supprimé : remplacé par l'approche avertissement de 3.3* | ~~🟡~~ | ADR-039 §2.1 | — |
| ~~3.5~~ | ~~Import depuis un fichier GED existant~~ — *supprimé : voir ADR-043* | ~~🟡~~ | ADR-039 | Un fichier tableur modifié dans la GED ne serait pas reflété dans la DataGrid |
| 3.6 | **ADR-043** — Fichiers tableurs GED vs DataGrid : règle d'unicité de source de vérité. Upload d'un `.xlsx`/`.ods`/`.csv` dans la GED → proposition d'import DataGrid. Exception archivage lecture seule avec bandeau d'avertissement permanent. | 🟢 | ADR-043 | Rédigé — 2026-05-15 |

---

## Bloc 4 — DataGrid Extensions — Relations entre tables
*Fondations posées en Bloc 0 — UI à construire ici.*

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 4.1 | Interface Super Admin — configuration relation N-1 | 🟢 | ADR-040 §6.1 | Le plus simple |
| 4.2 | Rendu N-1 dans la grille (dropdown → libellé) | 🟢 | ADR-040 §2.2 | — |
| 4.3 | Interface Super Admin — configuration relation 1-N | 🟢 | ADR-040 §6.1 | — |
| 4.4 | Vue Master/Détail lazy dans la grille | 🟢 | ADR-040 §4.3 | API JSON distincte de Livewire |
| 4.5 | Interface Super Admin — configuration relation N-N | 🟢 | ADR-040 §6.1 | Table de liaison créée manuellement |
| 4.6 | Rendu N-N dans la popup (cases à cocher) | 🟢 | ADR-040 §2.2 | — |
| 4.7 | Interface Super Admin — création de vues métier | 🟢 | ADR-040 §6.2 | Nom métier, table principale, tables liées |
| 4.8 | Colonnes calculées (`computed_sql`) — rendu + validation syntaxique | 🟢 | ADR-040 §5.1 | Connexion MySQL lecture seule dédiée |
| 4.9 | Colonnes agrégées (`GROUP_CONCAT`) — rendu | 🟢 | ADR-040 §5.2 | — |
| 4.10 | Popup onglets multi-tables (Identité / Mandats / Commissions / Historique) | 🟢 | ADR-040 §4.4 | — |
| 4.11 | Sidebar — affichage des vues métier à la place des tables brutes | 🟢 | ADR-040 §4.1 | — |
| 4.12 | Avertissement suppression avec lignes liées | 🟢 | ADR-040 §8 | — |

---

## Bloc 5 — DataGrid Extensions — Assistant de normalisation
*Outil clé pour la migration depuis les fichiers Excel complexes.*

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 5.1 | Wizard Phase 1 — diagnostic automatique du fichier (colonnes répétées/inutiles) | 🟢 | ADR-040 §3.1 | Utilise `DatagridNormalizationService` (0.6) |
| 5.2 | Wizard Phase 2 — drag & drop groupage des colonnes par entité | 🟢 | ADR-040 §3.2 | Interface guidée, langage naturel |
| 5.3 | Wizard Phase 3 — récapitulatif et validation explicite | 🟢 | ADR-040 §3.3 | Aucun DDL sans validation Super Admin |
| 5.4 | Exécution migration — pivot colonnes répétées → lignes (job asynchrone) | 🟢 | ADR-040 §3.4 | Indicateur de progression |
| 5.5 | Rapport post-migration avec détection doublons | 🟢 | ADR-040 §3.4 | — |

---

## Bloc 6 — DataGrid Extensions — Fonctionnalités avancées

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 6.1 | Champs conditionnels simples (afficher colonne B si colonne A = valeur) | 🟢 | ADR-039 §3.2 | Dans la popup uniquement |
| 6.2 | Pièces jointes — intégration GED (onglet Documents dans la popup) | 🟢 | ADR-039 §3.3 | Table `datagrid_row_documents` |
| 6.3 | Workflow statuts — transitions configurables + couleur de ligne | 🟢 | ADR-039 §3.4 | — |
| 6.4 | Validation par responsable (transition `requires_approval`) | 🟢 | ADR-039 §3.4 | Dépend notifications |
| 6.5 | Commentaires internes sur une fiche | 🟢 | ADR-039 §3.5 | — |
| 6.6 | Assignation d'une fiche à un agent | 🟢 | ADR-039 §3.5 | Dépend module Chat |
| 6.7 | Widgets résumés au-dessus de la grille (compteur, somme, répartition, alertes) | 🟢 | ADR-039 §3.6 | 4 widgets max par grille |
| 6.8 | Virtualisation grands volumes — server-side Tabulator au-delà de 2 000 lignes | 🟢 | ADR-039 §3.7 | API JSON distincte |
| 6.9 | Tri multi-colonnes UI (Shift+clic sur en-tête) | 🟢 | ADR-039 §3.7 | — |
| 6.10 | En-têtes de colonnes groupés | 🟢 | ADR-039 §3.7 | Ex : "Coordonnées" regroupe adresse/CP/ville |
| 6.11 | Verrouillage optimiste — avertissement si deux agents sur la même fiche | 🟢 | ADR-039 §3.7 | — |
| 6.12 | Champs calculés en lecture seule (âge, durée de mandat…) | 🟢 | ADR-039 §3.7 | Calcul côté serveur |
| 6.13 | Duplication d'une fiche | 🟢 | ADR-039 §2.5 | — |
| 6.14 | QR code par fiche (URL directe) | 🟢 | ADR-039 §3.7 | Accès mobile terrain |
| 6.15 | Recherche cross-tables (toutes grilles du tenant simultanément) | 🟢 | ADR-040 §7 | Endpoint + UI tableau de bord |

---

## Bloc 7 — Source de vérité documentaire
*Socle architectural posé (ADR-038, migrations créées) — UI à construire.*

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 7.1 | Interface classification des documents existants (non classifiés) | 🟠 | ADR-038 | — |
| 7.2 | Création d'un acte officiel — type, référence auto DEL-2026-042, métadonnées | 🟠 | ADR-038 | — |
| 7.3 | Sélection du modèle (template Collabora) à la création | 🟠 | ADR-038 | — |
| 7.4 | Recherche facettée Type + Date + Service | 🟡 | ADR-038 | — |
| 7.5 | Interface admin tenant — configurer modèles et patrons de nommage | 🟡 | ADR-038 | — |
| 7.6 | Reset compteur séquentiel (Super Admin) | 🟡 | ADR-038 | — |
| 7.7 | Registre des traitements auto-alimenté (RGPD) | 🟡 | ADR-037 | — |
| 7.8 | Assistant import RGPD 5 étapes (déclaration source, base légale, engagement) | 🟡 | ADR-037 | — |

---

## Bloc 8 — DataPilote
*Après stabilisation complète du DataGrid (Socle + Extensions).*

| # | Tâche | Priorité | ADR | Remarque |
|---|-------|----------|-----|----------|
| 8.1 | Bouton "Analyser" dans la barre d'outils DataGrid → URL `/datagrid/{id}/pilote` | 🔵 | ADR-039 §4.1 | — |
| 8.2 | Tableau croisé dynamique — axes ligne/colonne par drag & drop | 🔵 | ADR-039 §4.2 | Calculs serveur Laravel |
| 8.3 | Agrégations — somme, moyenne, compte, min, max | 🔵 | ADR-039 §4.2 | — |
| 8.4 | Graphiques Apache ECharts — barres, courbes, secteurs, combiné | 🔵 | ADR-039 §4.2 | MIT — rendu client |
| 8.5 | Export DataPilote — Excel (PhpSpreadsheet) + PNG (ECharts) + PDF | 🔵 | ADR-039 §4.2 | — |
| 8.6 | Sauvegarde des configurations DataPilote par utilisateur | 🔵 | ADR-039 §4.3 | Table `datagrid_pilote_configs` |

---

## Bloc 9 — Documentation et maintenance
*En parallèle du développement, au fil de l'eau.*

| # | Tâche | Priorité | Remarque |
|---|-------|----------|----------|
| 9.1 | Mettre à jour l'index des ADR (`docs/adr/index.md`) — ADR-039 à 041 manquants | ⚪ | — |
| 9.2 | Mettre à jour le CHANGELOG | ⚪ | — |
| 9.3 | SEO — mots-clés GitHub topics, description repo, README | ⚪ | — |
| 9.4 | SEO — méta-tags Open Graph sur `pladigit.fr` | ⚪ | — |
| 9.5 | Post "Show HN" (Hacker News) — préparation et publication | ⚪ | Après Bloc 2 stable |
| 9.6 | Fiche projet ADULLACT enrichie | ⚪ | Après Bloc 2 stable |
| 9.7 | Outreach collectivités — relance Noirmoutier + nouveaux contacts CDG | ⚪ | Après Bloc 2 stable |
| 9.8 | README — polish final avant communication | ⚪ | — |
| 9.9 | CDC et index ADR — session de revue documentation | ⚪ | — |
| 9.10 | Checklist sécurité mensuelle (ADR-041 §11) — à planifier dans l'agenda | ⚪ | — |
| 9.11 | Test de restauration mensuel (ADR-041 §8) — à planifier dans l'agenda | ⚪ | — |
| 9.12 | `SUPER_ADMIN_ALLOWED_IPS` variable d'environnement (évolution `CheckSuperAdmin.php`) | ⚪ | ADR-027 |
| 9.13 | Guide datagrid pour les utilisateurs - ne pas oublier que l'historique apparaît pour tout le monde --> tracabilité | ⚪ | ADR-027 |
| 9.14 | SEO-Visibility : maximiser les mots-clés pour que Pladigit soit mieux référencé et visible sur le net : Google, GitHub, ADULLACT, Hacker News ("Show HN"), etc. | ⚪ | ADR-027 |


---

## Récapitulatif — ordre global recommandé

```
Bloc 0  — Fondations architecturales          (migrations + enums + service)
Bloc 1  — Sécurité et infrastructure          (ADR + app + install.sh + tests)
Bloc 2  — DataGrid Socle                      (fonctionnalités utilisateur)
Bloc 3  — DataGrid Qualité des données        (fuzzy + doublons)
Bloc 7  — Source de vérité documentaire       (en parallèle de Bloc 2-3)
Bloc 4  — DataGrid Extensions Relations       (après socle stable)
Bloc 5  — Assistant de normalisation          (après relations)
Bloc 6  — DataGrid Extensions avancées        (au fil de l'eau)
Bloc 8  — DataPilote                          (après DataGrid complet)
Bloc 9  — Documentation et communication      (en parallèle, tout au long)
```

**Jalons clés :**

| Jalon | Condition | Objectif |
|-------|-----------|----------|
| 🎯 **Démo DSI** | Blocs 0 + 1 + 2 terminés | Montrer à une collectivité |
| 🎯 **Show HN / ADULLACT** | Blocs 0 + 1 + 2 + 3 terminés | Communication publique |
| 🎯 **Premier tenant réel** | Blocs 0 + 1 + 2 + 3 + 7 terminés | Déploiement en conditions réelles |
| 🎯 **DataGrid complet** | Blocs 0 à 6 terminés | Version 2.0 |
| 🎯 **Suite complète** | Blocs 0 à 8 terminés | Version 3.0 |
