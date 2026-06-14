# ADR-038 — Source de vérité documentaire (Niveau 2)

## Statut
Accepté — 2026-04-30

## Contexte

La GED Pladigit (Niveau 1) permet de stocker et organiser des fichiers dans
une arborescence de dossiers. Cependant, un `GedDocument` n'a aucune
connaissance de sa nature juridique ou administrative : un PDF nommé
`seance-06-2026.pdf` est indistinguable d'un arrêté de nomination ou
d'un compte-rendu de réunion technique.

Les collectivités territoriales produisent des documents officiels soumis
à des obligations légales :

- **Délibérations** — adoptées en séance du conseil, transmises au contrôle
  de légalité (préfecture), publiées pour devenir exécutoires (CGCT art.
  L.2131-1)
- **Arrêtés** — actes individuels ou réglementaires du Maire, publiés ou
  notifiés selon leur nature
- **Procès-verbaux** — signés, archivés, communicables sur demande (CADA)

Ces documents nécessitent :
1. Une **classification explicite** par type
2. Une **référence normalisée** pour la traçabilité (ex: DEL-2026-042)
3. Un **nommage automatique** cohérent pour éviter les anomalies (espaces,
   caractères spéciaux, dates absentes)
4. Des **métadonnées obligatoires** (date officielle, service émetteur, objet)
5. Des **modèles de documents** (gabarits) pour garantir la cohérence visuelle

---

## Décision

### 1. Enrichissement sémantique de `ged_documents`

Ajout de colonnes documentaires à la table existante (migration additive,
rétrocompatible — les documents existants conservent `document_type = null`
et sont affichés comme "Non classifié") :

| Colonne          | Type             | Rôle                                     |
|------------------|------------------|------------------------------------------|
| `document_type`  | varchar(50) NULL | Type officiel (enum GedDocumentType)     |
| `reference`      | varchar(30) NULL | Référence normalisée (DEL-2026-042)      |
| `document_date`  | date NULL        | Date officielle de l'acte                |
| `object`         | varchar(255) NULL| Intitulé court du document               |
| `department_id`  | FK NULL          | Service / direction émetteur             |
| `template_id`    | FK NULL          | Modèle utilisé lors de la création       |
| `tags`           | JSON NULL        | Mots-clés pour la recherche facettée     |

### 2. Types documentaires officiels (GedDocumentType)

13 types couvrant les documents courants des collectivités < 20 000 hab. :

**Actes réglementaires** (soumis à numérotation officielle) :
- `deliberation` — Délibération du conseil (préfixe : DEL)
- `arrete` — Arrêté municipal (préfixe : ARR)
- `decision` — Décision du Maire (préfixe : DEC)

**Comptes rendus** :
- `compte_rendu` — Compte-rendu de réunion (préfixe : CR)
- `proces_verbal` — Procès-verbal du conseil (préfixe : PV)

**Correspondance** :
- `courrier` — Courrier officiel (préfixe : COUR)
- `note_service` — Note de service (préfixe : NS)
- `rapport` — Rapport au conseil (préfixe : RAP)

**Marchés & contrats** :
- `marche` — Marché public (préfixe : MP)
- `convention` — Convention (préfixe : CONV)
- `contrat` — Contrat (préfixe : CTR)

**Budgétaire** :
- `budget` — Document budgétaire (préfixe : BUD)

**Divers** :
- `autre` — Document non classifié (préfixe : DOC)

### 3. Nommage automatique (DocumentNamingService)

**Format de référence** : `{PREFIX}-{AAAA}-{NNN}`

- `{PREFIX}` : préfixe du type (DEL, ARR, CR…)
- `{AAAA}` : année sur 4 chiffres
- `{NNN}` : compteur séquentiel sur 3 chiffres, réinitialisé par année et
  par type

Exemples :
```
DEL-2026-001    première délibération de 2026
DEL-2026-042    quarante-deuxième délibération de 2026
ARR-2026-015    quinzième arrêté de 2026
CR-2026-007     septième compte-rendu de 2026
```

**Compteur atomique** : géré par la table `ged_document_sequences`
(type + année → last_sequence). L'incrémentation utilise `SELECT FOR UPDATE`
en transaction pour éviter les doublons en cas d'accès concurrent.

**Patron de nommage** (name_pattern dans GedDocumentTemplate) :
Variables disponibles : `{PREFIX}`, `{YEAR}`, `{SEQ}`, `{DEPT}`, `{SLUG}`

Exemples de patrons :
```
{PREFIX}-{YEAR}-{SEQ}           → DEL-2026-042
{PREFIX}-{YEAR}-{SEQ}-{DEPT}    → ARR-2026-015-rh
{PREFIX}-{YEAR}-{SEQ}-{SLUG}    → CR-2026-007-conseil-municipal-juin
```

### 4. Modèles de documents (GedDocumentTemplate)

Un modèle définit, pour un type donné :
- Le patron de nommage
- Le dossier GED cible par défaut (surchargeâble par l'agent)
- Le service émetteur par défaut
- La liste des champs obligatoires à la création
- Le fichier gabarit Collabora (.odt/.docx) optionnel

**11 modèles par défaut** sont fournis via `GedDocumentTemplateSeeder`
(idempotent — n'écrase pas les personnalisations de l'admin tenant).

### 5. Rétrocompatibilité

- Les documents existants sans type conservent `document_type = NULL`
- Ils sont affichés comme "Non classifié" dans l'interface
- Leur comportement (upload, versioning, permissions) est inchangé
- La migration est strictement additive (ALTER TABLE, pas de DROP)

---

## Conséquences

### Positives
- Classification documentaire alignée sur les obligations légales CGCT
- Référence unique et traçable pour chaque acte officiel
- Nommage cohérent : plus d'anomalies dans les noms de fichiers
- Métadonnées exploitables pour la recherche facettée (Type + Date + Service)
- Fondation pour les workflows de validation (Niveau 2 — ADR à venir)
- Gabarits Collabora disponibles pour les modèles courants

### Points de vigilance
- Les références sont par tenant — pas de numérotation inter-collectivités
- Le compteur séquentiel est réinitialisé par année ET par type ; si une
  référence est annulée, le numéro n'est pas réutilisé (trous tolérés)
- Le Super Admin peut resetter un compteur en cas d'erreur (à implémenter)
- Le gabarit Collabora (template_file_path) est géré manuellement par l'admin
  tenant — pas d'interface de création dans cette version

### Alternatives écartées
- **Numérotation continue sans reset annuel** : contraire aux pratiques des
  collectivités (registres annuels)
- **Enum MySQL strict** sur document_type : rigide, rend les migrations
  d'ajout de type dangereuses — varchar(50) + enum PHP + validation Laravel
  offre la même garantie avec plus de souplesse
- **Table séparée ged_official_documents** : fragmentation inutile,
  complexifie les jointures ; l'enrichissement de ged_documents est préférable

---

## Fichiers créés / modifiés

### Créés
- `app/Enums/GedDocumentType.php`
- `app/Models/Tenant/GedDocumentTemplate.php`
- `app/Models/Tenant/GedDocumentSequence.php`
- `app/Services/Ged/DocumentNamingService.php`
- `database/migrations/tenant/2026_04_30_000001_add_document_type_to_ged_documents.php`
- `database/migrations/tenant/2026_04_30_000002_create_ged_document_templates_table.php`
- `database/migrations/tenant/2026_04_30_000003_add_template_fk_to_ged_documents.php`
- `database/seeders/GedDocumentTemplateSeeder.php`
- `docs/adr/ADR-038.md` (ce fichier)

### Modifiés
- `app/Models/Tenant/GedDocument.php` — ajout fillable, casts, relations et helpers
  documentaires (documentTypeEnum, isOfficialAct, documentTypeLabel, badgeColor)

---

## Références
- CGCT art. L.2122-22 — délégations du Maire
- CGCT art. L.2131-1 — publicité et caractère exécutoire des actes
- Loi CADA — communicabilité des documents administratifs
- ADR-020 : interface de stockage GED
- ADR-022 : intégration Collabora dans la GED
- ADR-036 : DataGrid et modèle de droits hiérarchiques
- ADR-043 : GED vs DataGrid — unicité de source de vérité pour les fichiers tableurs
