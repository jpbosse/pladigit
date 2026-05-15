# ADR-043 — GED vs DataGrid : unicité de source de vérité pour les fichiers tableurs

## Statut
Proposé — 2026-05-15

## Contexte

La GED de Pladigit accueillera des fichiers issus d'une synchronisation NAS
pouvant représenter 20 à 30 ans d'historique documentaire. Parmi ces fichiers,
un nombre significatif sont des tableurs (`.xlsx`, `.ods`, `.csv`) qui
pourraient être candidats à une importation en DataGrid.

Le problème central : un même jeu de données ne peut pas être maintenu
simultanément dans un fichier tableur éditable en GED **et** dans une DataGrid
active. Cela créerait deux sources de vérité divergentes sans mécanisme de
réconciliation fiable.

Le présent ADR établit les règles qui gouvernent la coexistence de ces deux
modules et le processus de décision laissé aux utilisateurs.

---

## Décision

### 1. Principe fondamental

**Un fichier tableur en GED et une DataGrid active ne peuvent pas coexister
sur le même jeu de données en mode éditable simultané.**

Dès qu'un fichier tableur est importé en DataGrid, le fichier GED source
passe en lecture seule. La DataGrid devient l'unique source de vérité
pour ces données.

---

### 2. Déclenchement de la décision : à l'ouverture dans la GED

La décision n'est **pas** déclenchée à l'upload ni à la synchronisation NAS.

**Raison :** une sync NAS initiale peut importer des milliers de fichiers
tableurs en masse. Forcer une décision immédiate bloquerait le processus
et serait inapplicable pour des fichiers jamais ouverts depuis des années.

À la **première ouverture** d'un fichier tableur dans la GED, une popup
est présentée à l'utilisateur avec trois choix :

| Choix | Action | Effet |
|-------|--------|-------|
| Importer comme DataGrid | Lance le wizard d'import | Fichier GED → lecture seule |
| Garder dans la GED | Stockage normal | Aucun lien créé |
| Me le rappeler plus tard | Décision différée | Fichier marqué "en attente" |

Les fichiers "en attente" ne sont pas des anomalies — c'est l'état normal
pour tout fichier jamais ouvert depuis la sync NAS.

---

### 3. Traçabilité des décisions

Toute décision est enregistrée de manière immuable :

- `user_id` de l'utilisateur ayant effectué le choix
- `timestamp` de la décision
- Choix effectué (`import`, `ged_only`, `deferred`)
- Référence au fichier GED (`ged_file_id`)
- Référence à la DataGrid créée le cas échéant (`datagrid_id`)

Un historique des décisions est consultable par l'admin tenant et le Super Admin.

---

### 4. Comportement du fichier GED après import en DataGrid

Le fichier GED source passe en **lecture seule permanente** dès le lancement
du wizard :

- Bandeau d'avertissement visible dans la GED :
  *"Ce fichier est la source d'une DataGrid — modification impossible.
  Accédez à la DataGrid pour modifier les données."*
- Icône cadenas dans la liste des fichiers GED
- Lien direct vers la DataGrid associée

Toute tentative de modification est bloquée au niveau de l'interface
et de l'API.

---

### 5. Sort du fichier GED après import — choix utilisateur

À la fin du wizard d'import, deux options sont proposées :

| Option | Effet |
|--------|-------|
| **Garder dans la GED** | Fichier conservé en lecture seule, bandeau permanent, lien affiché |
| **Supprimer de la GED** | Fichier supprimé, DataGrid totalement autonome, lien effacé |

Ce choix est également tracé.

---

### 6. Réversibilité — sens unique du lien automatique

Le lien automatique GED → DataGrid est **à sens unique**.
Il n'existe pas de mécanisme automatique de retour DataGrid → GED.

Cependant, trois chemins de retour manuel sont possibles si l'utilisateur
s'est trompé :

1. **Export DataGrid → ODS** puis réimport manuel dans la GED.
   Le wizard se redéclenche à la première ouverture → nouveau choix.
2. **Restauration du fichier original** depuis la sauvegarde
   (backup NAS ou Timeshift selon l'environnement).
3. **Restauration depuis la corbeille GED** si le fichier avait été supprimé
   en fin de wizard et que le délai de rétention n'est pas expiré.

Ce choix de réversibilité manuelle assumée est documenté ici pour éviter
toute demande future d'implémentation d'un retour automatique.

---

### 7. Suppression ultérieure du fichier GED lié par un admin

Si un admin tente de supprimer un fichier GED en lecture seule lié à une
DataGrid active :

- **Avertissement bloquant** : l'opération est refusée par défaut
- L'admin doit confirmer explicitement qu'il souhaite casser le lien
- La confirmation est tracée dans le journal d'audit
- La DataGrid reste intacte, le lien (`ged_file_id`) est mis à `null`

---

### 8. Interface d'intégrité

Une interface dédiée est accessible à la demande par l'admin tenant
et le Super Admin, sur le modèle de la photothèque et de la GED.

Elle liste les **anomalies réelles** uniquement — pas les fichiers
"jamais ouverts" qui sont en état normal :

| Catégorie | Description |
|-----------|-------------|
| Lien cassé | Fichier GED lié à une DataGrid supprimée |
| Anomalie lecture seule | Fichier GED lié mais non verrouillé (incohérence) |
| Référence orpheline | DataGrid avec `ged_file_id` pointant vers un fichier inexistant |

**Contrôle automatique nocturne (minuit)** via le scheduler Laravel :
détection des incohérences listées ci-dessus, alimentation du tableau
de bord d'intégrité, notification admin si anomalies détectées.

Le tableau de bord affiche également, à titre informatif (sans les forcer) :
- Fichiers tableurs en attente de décision
- Fichiers tableurs conservés en GED (décision prise)
- Fichiers tableurs liés à une DataGrid
- Fichiers tableurs supprimés après import (journal)

Filtres disponibles : type de fichier, date, utilisateur décideur, statut.

---

## Conséquences

### Positives
- Unicité de source de vérité garantie par le système, pas par la discipline des utilisateurs
- Traçabilité complète des décisions et de leur auteur
- Pas de blocage lors de la sync NAS initiale
- Réversibilité possible sans complexité technique supplémentaire

### Négatives / contraintes
- Le wizard d'import doit être accessible depuis la GED (pas seulement depuis le module DataGrid)
- Le contrôle d'intégrité nocturne est une dépendance au scheduler Laravel (déjà en place)
- L'interface d'intégrité est un écran supplémentaire à développer (bloc à planifier)

### Hors périmètre de cet ADR
- Synchronisation automatique bidirectionnelle GED ↔ DataGrid (explicitement exclue)
- Import depuis une URL distante ou une API externe
- Gestion des fichiers non-tableurs (PDF, images, etc.) — couverts par la GED standard

---

## Alternatives écartées

**Option A — Décision à l'upload :** écartée car incompatible avec la sync NAS
en masse. Des milliers de fichiers arriveraient sans pouvoir être traités
immédiatement.

**Option C — Lien fort avec sync automatique :** écartée pour complexité
disproportionnée dans un contexte solo et risques d'incohérence lors de
modifications concurrentes.

**Sens inverse automatique DataGrid → GED :** écarté. La réversibilité
manuelle (export + réimport) est suffisante et évite la complexité d'une
sync bidirectionnelle.

---

## Références
- ADR-038 : Source de vérité documentaire — classification juridique des actes officiels en GED
- ADR-039 : DataGrid DataPilote niveaux 2 et 3 — fonctionnalités avancées
- ADR-040 : Relations entre tables DataGrid
