# ADR-037 — Gouvernance des données personnelles et conformité RGPD

## Statut
Proposé — 2026-04-29

## Contexte

Les collectivités territoriales sont soumises au RGPD (Règlement Général
sur la Protection des Données) en tant que responsables de traitement.
Elles gèrent des données personnelles sensibles : coordonnées d'élus,
de personnalités, de contacts externes, d'agents.

Pladigit doit adopter une posture claire :
**protéger par défaut, permettre sur décision explicite et tracée.**

Le logiciel n'est pas le DPO (Délégué à la Protection des Données) de la
collectivité — mais il lui fournit tous les outils pour exercer ce rôle.

---

## Décision

### 1. Principe fondateur

```
Par défaut : protégé
Sur décision explicite de l'admin : débloqué
Pladigit trace — la collectivité assume
```

Toute action risquée au regard du RGPD est :
- Bloquée par défaut
- Débloquable par l'admin tenant via un engagement explicite
- Horodatée et enregistrée dans le journal d'audit avec l'identité de l'opérateur

---

### 2. Annuaire des personnalités

#### 2.1 Modèle de données générique

Un modèle universel Personnes + Rôles/Titres (N) couvre tous les cas :

```sql
personnes
    id
    nom, prenom, photo
    coordonnees_pro    -- email mairie, téléphone officiel
    coordonnees_priv   -- email perso, mobile (accès restreint)
    base_legale        -- enum: consentement / interet_legitime /
                       --       mission_service_public / obligation_legale
    opposition         -- boolean — bloquant définitivement si true
    visibilite         -- public / interne / confidentiel / archive
    date_revision      -- alerte automatique à dépassement
    timestamps, softDeletes

roles_titres
    id
    personne_id        -- FK personnes
    categorie          -- enum: elu / journaliste / scientifique /
                       --       protocole / economique / societe_civile / autre
    fonction           -- texte libre (Maire, Rédacteur en chef, Chercheur…)
    organisation       -- texte libre ou FK table organisations
    specialite         -- optionnel
    rang_protocolaire  -- entier, pour l'ordre lors des cérémonies
    date_debut
    date_fin           -- NULL si mandat en cours
    statut             -- actif / termine / suspendu
    timestamps
```

#### 2.2 Catégories de personnalités

| Catégorie       | Exemples de fonctions                                        |
|-----------------|--------------------------------------------------------------|
| Élus            | Maire, Conseiller, Député, Sénateur, Président de Région…   |
| Médias          | Journaliste, Correspondant local, Photographe de presse…    |
| Académique      | Chercheur, Enseignant-chercheur, Expert, Lauréat…           |
| Économique      | Chef d'entreprise, Président CCI, Représentant syndical…    |
| Protocole       | Préfet, Sous-préfet, Magistrat, Officier, Consul…           |
| Société civile  | Président d'association, Personnalité culturelle, Médecin…  |

#### 2.3 Choix organisationnel laissé à la mairie

La collectivité décide librement de sa structure :
- **Option 1** — une seule table "Personnalités" (tout le monde dedans,
  géré par les droits hiérarchiques)
- **Option 2** — tables séparées (ex: "Élus" + "Contacts externes")

Le Super Admin crée la structure ; le DataGrid s'y adapte.

#### 2.4 Anciens élus et durée de conservation

- Les mandats terminés sont conservés pour mémoire historique (Code du patrimoine)
- Les coordonnées privées (email perso, mobile) sont anonymisées à la fin du mandat
- Une date de révision automatique déclenche une alerte à l'admin tenant
- L'admin peut choisir : archiver (anonymiser) ou supprimer définitivement
- L'archivage conserve "Maire de X de 2014 à 2020" sans données personnelles

---

### 3. Import de données (Excel → MySQL)

L'import est le moment le plus risqué — des années de données accumulées
sans base légale claire entrent dans le système. Un assistant obligatoire
en 5 étapes encadre chaque import.

#### Étape 1 — Déclaration de la source

```
D'où proviennent ces données ?
○ Collecte directe auprès des personnes concernées
○ Source publique (annuaire officiel, site institutionnel)
○ Fichier interne existant (migration depuis ancien logiciel)
○ Transfert depuis un partenaire institutionnel
○ Autre — précisez
```

#### Étape 2 — Déclaration de la base légale

```
Sur quelle base légale traitez-vous ces données ?
○ Consentement recueilli et documenté
○ Intérêt légitime (relation institutionnelle établie)
○ Mission de service public
○ Obligation légale
○ Je ne sais pas → accès bloqué, contacter le DPO
```

Si "Je ne sais pas" est sélectionné, l'import est bloqué
et un message invite à contacter le DPO de la collectivité.

#### Étape 3 — Analyse automatique du fichier

Pladigit détecte et signale avant import :
- Colonnes sensibles probables (email, téléphone, date de naissance, adresse)
- Doublons avec des fiches existantes
- Données manifestement périmées (dates > seuil de rétention configuré)
- Champs non reconnus à mapper manuellement
- Champs sans base légale identifiable

#### Étape 4 — Engagement avant validation

```
⚠ Récapitulatif avant import
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
247 personnes à importer
├ 3 colonnes sensibles détectées
├ 12 doublons potentiels
└ 45 fiches datant de plus de 5 ans

En validant, vous déclarez :
☐ Disposer d'une base légale pour ce traitement
☐ Avoir informé ou être légalement dispensé d'informer les personnes
☐ Assumer la responsabilité de cet import en tant que
  responsable de traitement

Cette déclaration est horodatée et enregistrée dans le registre
des traitements de votre collectivité.

[ Annuler ]          [ J'assume et je valide l'import ]
```

#### Étape 5 — Génération automatique dans le registre

L'import génère une entrée dans le registre des traitements :
- Date et heure de l'import
- Identité de l'opérateur
- Source déclarée
- Base légale déclarée
- Nombre de fiches importées
- Colonnes sensibles présentes
- Référence au fichier source (nom, hash)

---

### 4. Communications (vœux, courriers, emails)

#### 4.1 Champs sur chaque fiche personne

- `base_legale` — base légale du traitement (voir §2.1)
- `opposition` — boolean, bloquant définitivement si `true`
- `date_opposition` — horodatage de la demande d'opposition

#### 4.2 Avertissement avant export d'une liste

Lors de tout export de liste incluant des données personnelles :

```
⚠ Action sensible détectée
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3 destinataires sans base légale renseignée
2 personnes en opposition enregistrée

Les personnes en opposition sont exclues automatiquement.
Les personnes sans base légale nécessitent votre décision.

En continuant, vous assumez la responsabilité de ce traitement
au titre de l'article 6 du RGPD.

Cette action sera enregistrée dans le journal d'audit
avec votre identité et l'horodatage.

[ Annuler ]     [ Exclure les cas litigieux ]     [ J'assume et je continue ]
```

#### 4.3 Traçabilité de chaque envoi

Chaque envoi groupé génère une entrée dans le journal d'audit :
- Identité de l'opérateur
- Horodatage
- Finalité déclarée (vœux / convocation / information…)
- Nombre de destinataires
- Nombre d'exclusions (oppositions + sans base légale)
- Décision de l'opérateur si cas litigieux

---

### 5. Registre des traitements

Le registre des traitements (obligation RGPD art. 30) est partiellement
auto-alimenté par Pladigit à partir des événements traçables :

| Événement                    | Entrée automatique |
|------------------------------|--------------------|
| Import de données            | ✅                  |
| Export de liste personnelles | ✅                  |
| Envoi groupé de communications | ✅                |
| Archivage / anonymisation    | ✅                  |
| Suppression de fiche         | ✅                  |
| Déblocage d'action risquée   | ✅                  |

Le registre est exportable en PDF et Excel pour présentation
à la CNIL ou au DPO.

---

### 6. Droits des personnes concernées

| Droit RGPD          | Mécanisme Pladigit                                           |
|---------------------|--------------------------------------------------------------|
| Droit d'accès       | Export de la fiche complète en PDF                           |
| Droit de rectification | Modification via DataGrid (tracée dans l'historique)      |
| Droit à l'effacement | Suppression ou anonymisation avec confirmation horodatée    |
| Droit d'opposition  | Champ `opposition = true`, bloquant définitivement           |
| Droit à la portabilité | Export JSON / CSV de la fiche                             |

---

## Conséquences

### Positives
- Pladigit protège la collectivité ET l'agent opérateur
- Registre des traitements partiellement auto-généré = gain de temps réel
- Posture commerciale différenciante : conformité RGPD sans bloquer le travail
- Traçabilité complète en cas de contrôle CNIL

### Points de vigilance
- L'assistant d'import alourdit le processus — acceptable car occasionnel
- La base légale doit être renseignée manuellement par l'admin tenant
- Pladigit ne remplace pas un DPO — le mentionner clairement dans la documentation
- Les politiques de rétention sont configurables mais non imposées —
  la collectivité reste responsable de les respecter

### Ce que Pladigit ne fait pas
- Ne valide pas la légalité d'un traitement (rôle du DPO)
- Ne bloque pas définitivement une action si l'admin choisit de débloquer
- Ne gère pas les demandes d'exercice de droits reçues par courrier
  (hors périmètre — traitement manuel par la collectivité)

---

## Références
- Règlement (UE) 2016/679 (RGPD) — articles 6, 13, 17, 21, 30
- Code du patrimoine — archivage des documents publics
- ADR-036 : DataGrid, DataPilote et droits hiérarchiques
- CNIL — Guide pratique pour les collectivités territoriales
