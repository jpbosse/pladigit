# ARGUMENTAIRE — Pladigit
## Plateforme de Digitalisation Interne pour les collectivités françaises

> *Document de référence — Jean-Pierre Bossé — Mai 2026*
> *À destination des centres de gestion, syndicats informatiques, ADULLACT, collectivités*

---

## Qui suis-je ?

Je suis **Jean-Pierre Bossé**, retraité de la fonction publique territoriale, basé à **Soullans (Vendée, 85)**.

J'ai passé ma carrière dans les collectivités locales. Je connais de l'intérieur les contraintes budgétaires des petites communes, les décisions informatiques difficiles à prendre avec des équipes réduites, les délégués à la protection des données mutualisés qui demandent des audits, les élus qui veulent des rapports lisibles, les secrétaires généraux de mairie (SGM) et directeurs généraux des services (DGS) qui jonglent entre dix outils différents.

À la retraite, j'ai continué à coder — par passion, et parce que je voyais un vrai problème sans solution adaptée. Entre **octobre 2025 et avril 2026**, j'ai construit seul les **7 premières phases de Pladigit** : socle d'authentification, gestion de projet, photothèque connectée au serveur de fichiers, gestion électronique de documents, éditeur collaboratif Collabora Online, et assistant d'installation automatique.

**Ce que je ne suis pas :** un commercial, un éditeur de logiciel, une startup cherchant des investisseurs. Je suis un pair qui partage ce qu'il a construit, et qui cherche des organisations pilotes et des partenaires bénévoles pour faire vivre le projet.

---

## À propos de Pladigit

**Pladigit** (*Plateforme de Digitalisation Interne*) est une alternative souveraine et libre (code source ouvert) à Microsoft 365, conçue spécifiquement pour les **collectivités françaises de moins de 20 000 habitants**.

- **Licence :** AGPL-3.0 — Licence Publique Générale GNU Affero (code) / CC BY-SA 4.0 (documentation)
- **Dépôt de code :** [github.com/jpbosse/pladigit](https://github.com/jpbosse/pladigit)
- **Démonstration :** [pladigit.fr](https://pladigit.fr)
- **Contact :** contact@pladigit.fr

### Ce que Pladigit remplace

| Outil Microsoft | Ce que Pladigit propose à la place | État |
|-----------------|-------------------------------------|------|
| Planner | Gestion de projet (tableau Kanban, diagramme de Gantt, budget) | ✅ Disponible |
| OneDrive / Photos | Photothèque connectée au serveur de fichiers | ✅ Disponible |
| SharePoint | Gestion électronique de documents + éditeur Collabora | ✅ Disponible |
| Word / Excel / PowerPoint | Collabora Online (formats ouverts ODF natifs) | ✅ Disponible |
| Teams | Messagerie instantanée Pladigit | 🔜 Prévu |
| Outlook Calendrier | Agenda partagé + protocole CalDAV | 🔜 Prévu |

### Chiffres clés — mai 2026

| Indicateur | Résultat |
|-----------|---------|
| Phases livrées | 7 phases / octobre 2025 → avril 2026 |
| Tests automatisés | 759 tests unitaires / 1 645 vérifications — tous verts |
| Analyse statique du code | PHPStan niveau 5 — zéro erreur |
| Vulnérabilités connues | Audit des dépendances — zéro faille recensée |
| Décisions d'architecture documentées | 31 registres de décision publiés |
| Intégration continue | GitHub Actions — 4 vérifications obligatoires avant toute mise à jour |

---

## Contexte : pourquoi ce projet existe

### Le problème réel dans les petites collectivités

68 % des communes de moins de 5 000 habitants n'ont pas d'informaticien dédié *(source : Fédération Nationale des Collectivités Concédantes et Régies — FNCCR — 2023)*. Ces communes paient pourtant Microsoft 365, souvent sans en utiliser la moitié des fonctionnalités, et sans en maîtriser les conséquences sur la protection des données personnelles.

En parallèle :
- Le coût de Microsoft 365 a augmenté de **+40 % en 3 ans** sur le catalogue d'achats publics UGAP (Groupement de Commandes pour les Achats Publics) entre 2021 et 2024
- **73 % des incidents informatiques malveillants sur les collectivités** touchent des structures de moins de 20 000 habitants *(Agence Nationale de la Sécurité des Systèmes d'Information — ANSSI — 2023)*
- Les données de délibérations, d'agents, d'associations sont souvent dans des tableurs Excel non versionnés, sur des serveurs de fichiers non interfacés avec les outils métiers, ou dans des boîtes mail personnelles

### La fenêtre d'opportunité de 2026

Quatre signaux se cumulent en ce moment :
1. **Fin des contrats UGAP favorables** → hausse des licences Microsoft inévitable pour des milliers de collectivités
2. **Circulaire de la Direction Interministérielle du Numérique (DINUM)** sur la réduction des dépendances aux grandes plateformes américaines dans les administrations
3. **Montée de la cybermenace sur les mairies** — le sujet est désormais porté en Conseil municipal
4. **Aucun concurrent identique** — aucun outil libre français n'est multi-organisation natif + gestion électronique de documents + Collabora + gestion de projets + photothèque + installable en 30 minutes à moins de 1 500 €/an

---

## 1 — Argumentaire Sécurité

### Double authentification : application mobile plutôt que SMS

Le SMS peut être détourné par une technique dite de **transfert de numéro frauduleux** : un attaquant convainc l'opérateur téléphonique de transférer votre numéro sur une autre carte SIM, et reçoit ensuite tous vos codes de vérification envoyés par SMS. Des collectivités ont été compromises par ce vecteur.

Pladigit utilise le protocole **TOTP** (mot de passe à usage unique basé sur le temps) via des applications mobiles comme Google Authenticator, Aegis ou Authy. Le code de vérification est généré **directement sur le téléphone de l'agent**, sans passer par un réseau mobile, sans dépendance à un opérateur téléphonique, **sans aucun coût supplémentaire**. Il fonctionne même sans connexion internet.

Dans Pladigit :
- Le secret de l'application d'authentification est chiffré en base de données (algorithme AES-256 — chiffrement de niveau très élevé utilisé par les organisations gouvernementales)
- Un code de secours chiffré permet à l'agent de récupérer l'accès en cas de perte du téléphone, sans intervention externe
- L'administrateur peut rendre ce second facteur d'authentification obligatoire pour toute son organisation
- Ce choix est documenté et justifié dans la fiche de décision numéro 17 du projet

### Résistance aux attaques assistées par intelligence artificielle

Les attaques informatiques actuelles utilisant l'intelligence artificielle ciblent principalement deux vecteurs :

1. **Hameçonnage ciblé** (courriels frauduleux très convaincants générés par intelligence artificielle) → rendu moins dangereux par la double authentification : même si un agent se fait voler son mot de passe, l'attaquant ne peut pas se connecter sans le code de vérification qui change toutes les 30 secondes sur le téléphone de l'agent.

2. **Attaque par force brute sur les mots de passe** (essai de milliers de combinaisons à la seconde) → bloqué par trois mécanismes cumulés :
   - Verrouillage automatique du compte après plusieurs tentatives échouées
   - Blocage automatique au niveau du serveur par l'outil Fail2ban (bannissement de l'adresse réseau agressive)
   - Algorithme bcrypt pour le stockage des mots de passe — délai de calcul intentionnellement élevé, qui rend toute attaque par force brute économiquement inenvisageable

### Horizon de l'informatique quantique

Les ordinateurs quantiques, lorsqu'ils atteindront la maturité, seront capables de casser certains algorithmes de chiffrement classiques. Les grandes organisations commencent à s'y préparer (nouvelles normes publiées par l'Institut National américain des Standards et de la Technologie en 2024).

Pladigit utilise **bcrypt** pour les mots de passe — un algorithme à sens unique, structurellement plus résistant que les chiffrements asymétriques classiques susceptibles d'être remis en cause par l'informatique quantique.

**L'argument de la licence sur les portes dérobées :** La licence AGPL-3.0 oblige quiconque distribue une version modifiée de Pladigit à **publier le code source complet de ses modifications**. Il est donc structurellement impossible de distribuer à des collectivités une version contenant une porte dérobée cachée sans l'exposer publiquement. Aucun logiciel propriétaire ne peut offrir cette garantie.

---

## 2 — Pladigit face à Nextcloud

Nextcloud est un excellent outil généraliste. Il n'a pas été conçu pour les collectivités françaises.

### Ce que Pladigit fait mieux

| Critère | Nextcloud | Pladigit |
|---------|-----------|---------|
| Hébergement multi-organisations | Non — une installation par organisation | Oui — natif, base de données isolée par organisation |
| Structure hiérarchique | Groupes à configurer manuellement | Oui — Direction > Service > Agent intégrée |
| Gestion de projet | Extension externe limitée | Oui — Kanban, Gantt, budget, risques |
| Photothèque avancée | Partielle | Oui — données EXIF, filigrane, dédoublonnage |
| Installation sans technicien | Non | Oui — assistant 8 étapes, une seule commande |
| Conçu pour collectivités françaises | Non | Oui — rôles, hiérarchie, export PDF pour les élus |
| Listes collaboratives sans programmation | Non | Prévu — DataGrid et tableaux croisés dynamiques |

**Pour un centre de gestion ou un syndicat informatique :** gérer 30 communes sous Nextcloud, c'est maintenir 30 installations distinctes. Sous Pladigit, c'est une seule installation, avec 30 espaces isolés et indépendants.

### Ce que Pladigit ne fait pas encore — à dire clairement

- Pladigit est jeune (version 0.8, démarré en octobre 2025)
- Il est développé par une seule personne, sans équipe commerciale ni assistance disponible à toute heure
- La messagerie instantanée et l'agenda partagé ne sont pas encore disponibles (prévus pour 2027)
- Il n'existe pas encore de références d'usage en collectivité établies sur la durée

Dire ces points soi-même, avant qu'on vous les reproche, est toujours plus efficace que de les passer sous silence.

---

## 3 — Pladigit face à Microsoft 365

### Le coût, comparé honnêtement

Pour une commune de 10 agents, Microsoft 365 Business Standard sur le catalogue UGAP 2024 représente environ **1 500 €/an de licences** — hors formation, hors assistance, hors hausses tarifaires à venir (+40 % en 3 ans sur la période récente).

Pladigit en accès libre (plan Communautaire) : **0 € de licence** + un serveur virtuel français (OVH, Scaleway, Infomaniak) à partir de **15 €/mois** pour une installation sans l'éditeur Collabora, soit **environ 180 €/an tout compris**. L'économie est positive dès le premier mois.

### Où sont hébergées vos données ?

Vos délibérations, arrêtés, données d'agents — sous Microsoft 365, elles transitent sur des infrastructures soumises au **Cloud Act américain** (loi autorisant les autorités américaines à accéder aux données stockées par les entreprises américaines, même hors des États-Unis). La décision Schrems II de la Cour de Justice de l'Union Européenne (2020) a invalidé l'accord de transfert de données dit Privacy Shield. L'accord qui le remplace reste contesté juridiquement.

Avec Pladigit, les données restent **sous votre maîtrise** : soit sur votre propre serveur interne à la collectivité, soit sur un serveur virtuel hébergé en France (OVH, Scaleway, Infomaniak), soit au minimum chez un hébergeur européen soumis exclusivement au droit de l'Union Européenne. Le délégué à la protection des données peut pointer l'hébergeur sur une carte.

### Les formats ouverts de documents

Un document au format ODT (format ouvert de traitement de texte standardisé) ouvert dans 10 ans sera encore lisible par n'importe quel logiciel libre. Un document au format DOCX (format Microsoft Word) dépend de la bonne volonté de Microsoft pour rester lisible dans ses prochaines versions. Pour des archives municipales à conservation longue durée, c'est un argument de fond — et une exigence croissante des archives départementales.

### Aucune dépendance à un fournisseur unique

Si Microsoft décide demain de doubler ses tarifs ou de supprimer une fonctionnalité, vous n'avez aucun recours contractuel réel. Avec Pladigit sous licence AGPL-3.0 : le code est juridiquement libre, vous pouvez le faire maintenir par n'importe quelle société de services informatiques locale, ou le reprendre vous-même. Il n'existe pas de situation où vous perdez l'accès à vos propres données.

---

## 4 — Argumentaire pour les Centres de Gestion et syndicats informatiques

Les Centres de Gestion (CDG) et les syndicats informatiques territoriaux accompagnent les collectivités de leur périmètre sur des missions mutualisées — ressources humaines, juridique, conseil informatique. Pladigit est précisément pensé pour s'articuler avec ce modèle.

### L'angle de la mutualisation

Une seule installation Pladigit peut héberger plusieurs communes, chacune dans sa propre base de données isolée et indépendante. Pour un Centre de Gestion ou un syndicat informatique, devenir hébergeur mutualisé de Pladigit pour les communes sans capacité informatique interne — c'est exactement leur mission de service.

Un centre de gestion qui déploie Pladigit pour 20 communes de son périmètre n'installe et ne maintient qu'un seul serveur. Chaque commune accède à son propre espace via un sous-domaine dédié, sans jamais voir les données des autres.

*Exemple concret :* le Centre de Gestion de la Vendée (CDG 85) pourrait devenir le point d'entrée Pladigit pour les communes vendéennes de moins de 5 000 habitants, assurant l'installation initiale, la formation des secrétaires généraux de mairie (SGM — appellation issue de la loi du 30 décembre 2023), et le suivi technique de proximité. Ce modèle est transposable à n'importe quel CDG ou syndicat informatique territorial.

### L'angle de la proximité

Un Centre de Gestion parle déjà la langue des communes de son périmètre. Proposer Pladigit, c'est prolonger cette relation de confiance existante sur le volet numérique — sans intermédiaire commercial lointain.

### Le plan Partenaire pour les CDG

Le plan Partenaire (sur devis) couvre l'accompagnement à l'installation, la configuration des messageries, de l'annuaire des utilisateurs (Active Directory ou LDAP — protocole standard de gestion centralisée des comptes), de l'éditeur Collabora, le support direct sous 48 heures, et une formation initiale en visioconférence d'une heure. Il est conçu pour les communes sans informaticien dédié — le profil type du périmètre des CDG.

**Points pratiques :**
- Démonstration disponible sur pladigit.fr
- Installation en 30 minutes via une seule commande sur le serveur
- Financement possible via l'ANCT (Agence Nationale de la Cohésion des Territoires), programme Territoires Numériques — jusqu'à 50–80 % du coût pris en charge pour la commune
- Financement possible via les fonds européens FEDER (Fonds Européen de Développement Régional) pour la transformation numérique des petites collectivités

---

## 5 — Cas concret : une commune touristique côtière

*Ce cas est représentatif d'une commune littorale de 3 000 à 8 000 habitants permanents, dont la population triple en été.*

### Le contexte typique

Une petite commune touristique côtière fonctionne avec une équipe municipale réduite à l'année — souvent un secrétaire général de mairie (SGM), un ou deux agents techniques, et le maire qui suit les dossiers depuis son téléphone. L'été, l'activité explose : événements culturels, animations, coordination avec les associations locales, suivi de chantiers d'entretien du front de mer.

### Ce que Pladigit apporte concrètement

**La photothèque pour la communication saisonnière**
Les photos de fêtes, de marchés estivaux, de concerts en plein air — aujourd'hui éparpillées sur des clés USB, des téléphones personnels ou dans des boîtes mail — sont centralisées dans Pladigit avec synchronisation automatique depuis le serveur de fichiers existant. Albums organisés par événement, filigrane aux couleurs de la commune, partage par lien temporaire pour les associations ou la presse locale. Plus de course au dernier moment pour retrouver les photos d'un événement passé.

**La gestion de projet pour les travaux saisonniers**
La commune pilote plusieurs petits chantiers chaque année : réfection de la promenade, mise aux normes d'équipements, aménagements de plages. Ces projets sont souvent suivis sous Excel, sans visibilité pour les élus. Avec Pladigit, chaque projet dispose d'un diagramme de Gantt, d'un suivi budgétaire (prévision, engagement, paiement effectif, cofinancements), et d'un document de synthèse lisible pour le Conseil municipal.

**La gestion documentaire pour la mémoire de la commune**
Les délibérations, arrêtés, contrats avec les associations, permis de construire — tous versés dans la base documentaire avec versioning (chaque modification est tracée et réversible), droits d'accès par service, et recherche plein texte. Un nouveau secrétaire général de mairie (SGM) ou directeur général des services (DGS) trouve en quelques secondes le dossier qu'il cherche, sans appeler son prédécesseur.

**L'argument de l'hébergement maîtrisé**
La dépendance à un service cloud distant est un risque supplémentaire pour une commune dont la connectivité peut être dégradée par les conditions météorologiques ou des travaux sur le réseau. Héberger Pladigit sur un serveur interne ou un serveur virtuel français, avec des sauvegardes automatiques régulières, rend la commune moins vulnérable aux aléas d'internet. Ce qui est souvent perçu comme une contrainte technique devient ici un avantage opérationnel réel.

---

## 6 — Positionnement face à la Suite Numérique de l'État

Pladigit n'est **pas concurrent** de la Suite Numérique proposée par la Direction Interministérielle du Numérique (DINUM) — qui regroupe Tchap (messagerie), Resana (espace de travail collaboratif), Webconf (visioconférence), Webmail et Nextcloud institutionnel. Il est **complémentaire**.

La Suite DINUM adresse les usages transversaux nationaux : communiquer, se réunir à distance, partager des fichiers entre administrations. Pladigit adresse les besoins de **gestion documentaire locale, de photothèque communale, de gestion de projets de terrain** — des usages que la Suite DINUM ne couvre pas.

Pour un interlocuteur de la préfecture ou d'un service de l'État : *"Pladigit complète la Suite Numérique sur ce qu'elle ne fait pas à l'échelle d'une commune. Un agent peut utiliser Tchap pour ses échanges et Pladigit pour gérer les documents et les projets de sa collectivité."*

---

## 7 — Transparence technique pour les Délégués à la Protection des Données et l'ANSSI

### Pour les Délégués à la Protection des Données (parfois appelés DPO — Data Protection Officer)

La licence AGPL-3.0 va plus loin que la sécurité. Elle répond directement aux besoins des délégués à la protection des données mutualisés :

- **Journal d'audit complet** : chaque accès, modification ou suppression est enregistré, horodaté, attribué à un utilisateur identifié — export en format tableur (CSV) ou fichier structuré (JSON), conservation paramétrable à 12 mois par défaut et extensible jusqu'à 36 mois — conforme à l'obligation de registre des traitements du Règlement Général sur la Protection des Données (RGPD)
- **Versioning documentaire** : toute modification d'un document est traçable, avec possibilité de restaurer une version antérieure
- **Hébergement maîtrisé** : les données restent physiquement en France (serveur interne à la collectivité, ou serveur virtuel chez OVH, Scaleway, Infomaniak) — ou au minimum chez un hébergeur européen si un hébergeur français n'est pas disponible. Dans tous les cas, aucune ambiguïté sur la localisation des données ni sur le droit applicable
- **Code source public** : le délégué à la protection des données ou toute société mandatée peut auditer le code source lui-même, sans avoir à faire confiance à l'éditeur sur parole
- **Isolation entre organisations** : chaque collectivité dispose de sa propre base de données — il n'y a aucun mélange de données entre organisations distinctes

### Pour les exigences de l'ANSSI (Agence Nationale de la Sécurité des Systèmes d'Information)

- Pas encore de qualification SecNumCloud (certification de sécurité de l'ANSSI pour les hébergements en nuage) — c'est honnête à dire pour un projet en version 0.8
- En revanche : zéro faille connue dans les dépendances du code (audit automatique à chaque mise à jour), niveau d'analyse statique PHPStan 5 (aucune erreur de typage dans le code), 31 fiches de décision d'architecture publiées — chaque choix technique est traçable et défendable
- Fail2ban, pare-feu UFW (outil de gestion des règles réseau), connexion à l'annuaire obligatoirement chiffrée, double authentification TOTP — les recommandations de base de l'ANSSI pour les collectivités sont toutes implémentées
- *Argument central* : un logiciel propriétaire peut avoir autant de failles — vous n'en saurez simplement rien. Ici, tout est visible et auditable.

---

## 8 — Ce qui arrive bientôt : en finir avec les tableurs Excel éparpillés

Un problème très fréquent dans les collectivités : des dizaines de fichiers Excel disséminés sur les serveurs de fichiers, les bureaux des agents, les boîtes mail. Listes d'élus, registres d'associations, suivi des équipements, plannings d'agents, tableaux de bord — chacun dans son coin, sans lien entre eux, sans traçabilité, sans que personne ne sache quelle version est la bonne.

Deux modules sont prévus dans la prochaine phase de Pladigit pour répondre directement à ce problème :

**DataGrid — les listes collaboratives sans programmation**
Créer et gérer des listes structurées directement dans Pladigit, sans écrire une ligne de code, sans maîtriser un tableur avancé. Une liste d'associations de la commune, un registre des équipements communaux, un suivi des demandes de travaux — autant de données saisies une fois, accessibles par les bons agents selon leurs droits, modifiables en temps réel, sans risque de doublon ni de version périmée.

**DataPilot — les synthèses croisées à la demande**
Une fois les données dans DataGrid, DataPilot permet d'en extraire des synthèses : consommation téléphonique par service et par mois, carburant par véhicule, nombre d'heures par agent. Des tableaux croisés dynamiques — le même principe qu'Excel, mais sans avoir à reconstruire le tableau à chaque mise à jour des données sources, et avec des droits d'accès qui garantissent que chaque agent ne voit que ce qui le concerne.

Ces deux modules remplaceront progressivement les tableurs Excel éparpillés par une source de données unique, partagée, versionnée et cohérente.

---

## Tableau comparatif général

| Solution | Multi-organisation | Gestion de documents | Édition collaborative | Gestion de projets | Photothèque | Moins de 1 500 €/an | Logiciel libre français |
|----------|-------------|-----|----------------|---------|-------------|-------------|-------------------|
| Microsoft 365 | Non | Oui | Oui | Non | Non | Non | Non |
| Suite DINUM | Non | Partiel | Non | Non | Non | Oui | Oui |
| Nextcloud Hub | Non | Oui | Oui | Non | Partiel | Oui | Non |
| Jalios Digital Workplace | Partiel | Oui | Oui | Oui | Non | Non | Non |
| **✦ Pladigit** | **Oui** | **Oui** | **Oui** | **Oui** | **Oui** | **Oui** | **Oui** |

*Aucun outil libre français n'est multi-organisation natif + gestion de documents + édition collaborative + gestion de projets + photothèque + installable en 30 minutes à moins de 1 500 €/an. Cette absence de solution existe toujours en mai 2026. Pladigit l'occupe seul.*

---

## Ce que je propose concrètement

### Plan Communautaire — gratuit

Code source complet sous licence AGPL-3.0. Installation via un script en une seule commande. Assistant de configuration web en 8 étapes. Assistance communautaire via GitHub. Tous les modules accessibles sans restriction. Idéal pour les organisations disposant d'un syndicat informatique ou d'une compétence technique interne.

### Plan Partenaire — sur devis

Tout le plan Communautaire, plus : accompagnement à l'installation, configuration des messageries et de l'annuaire des utilisateurs, support direct sous 48 heures, formation initiale en visioconférence d'une heure, suivi des mises à jour. Pour les communes sans informaticien dédié.

*Règle fondamentale : les deux plans donnent accès à l'intégralité des fonctionnalités. Ce qui distingue les offres est le niveau d'accompagnement — jamais les fonctionnalités.*

---

## Comment me contacter

**Jean-Pierre Bossé**
Soullans (Vendée, 85)
contact@pladigit.fr
github.com/jpbosse/pladigit
pladigit.fr

---

*Pladigit — Reprendre le contrôle de votre numérique.*
*AGPL-3.0 (code) · CC BY-SA 4.0 (documentation) · Mai 2026*
