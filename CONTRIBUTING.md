# Contribuer à Pladigit

Merci de l'intérêt que vous portez à Pladigit. Les contributions sont les bienvenues — qu'il s'agisse de code, de documentation, de retours d'usage terrain ou de signalements de bugs.

---

## Avant de commencer

Pladigit est un projet libre porté par **un développeur unique**, retraité de la fonction publique territoriale, à titre personnel. Il n'existe pas d'équipe de maintenance, pas d'engagement de délai de réponse garanti, pas d'entreprise derrière. C'est un bien commun numérique, pas un produit commercial.

Cela implique :
- Les réponses aux issues et demandes de contribution (pull requests) peuvent prendre du temps
- Les contributions documentées et testées sont beaucoup plus faciles à intégrer
- La bienveillance est de mise dans tous les échanges

---

## Signaler un bug

1. Vérifier que le bug n'est pas déjà signalé dans les [issues GitHub](https://github.com/jpbosse/pladigit/issues)
2. Ouvrir une nouvelle issue avec :
   - La version de Pladigit concernée
   - Les étapes pour reproduire le problème
   - Le comportement attendu vs le comportement observé
   - Les journaux pertinents (`storage/logs/laravel.log`)
   - L'environnement (système d'exploitation, PHP, MySQL, Redis)

> Pour les vulnérabilités de sécurité : voir [SECURITY.md](SECURITY.md) — **ne pas ouvrir d'issue publique**.

---

## Proposer une fonctionnalité

Ouvrir une issue avec le label `enhancement` en décrivant :
- Le besoin concret (quel profil d'utilisateur, quel contexte collectivité)
- La solution envisagée
- Les alternatives considérées

Les fonctionnalités liées aux collectivités françaises, à la souveraineté numérique et à la conformité RGPD sont prioritaires.

---

## Contribuer du code

### Prérequis

- PHP 8.4+, Composer 2, Node.js 20, MySQL 8, Redis 7
- Lire [INSTALL.md](INSTALL.md) pour mettre en place l'environnement de développement local

### Mettre en place l'environnement local

```bash
git clone https://github.com/jpbosse/pladigit.git
cd pladigit

composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate

php artisan migrate --path=database/migrations/platform
php artisan migrate --path=database/migrations

php artisan queue:work
```

### Workflow de contribution

```bash
# 1. Fork + clone
git clone https://github.com/VOTRE_COMPTE/pladigit.git
cd pladigit

# 2. Créer une branche
git checkout -b fix/description-courte
# ou
git checkout -b feature/description-courte

# 3. Développer + vérifier
php -d memory_limit=512M vendor/bin/phpunit
./vendor/bin/pint
./vendor/bin/phpstan analyse --memory-limit=512M

# 4. Commiter
git commit -m "fix: description courte du correctif"

# 5. Pousser + ouvrir une PR vers la branche develop
git push origin fix/description-courte
```

### Conventions de nommage

**Branches :**
- `fix/` — correctifs de bugs
- `feature/` — nouvelles fonctionnalités
- `docs/` — documentation uniquement
- `refactor/` — refactoring sans changement de comportement

**Messages de commit :** format [Conventional Commits](https://www.conventionalcommits.org/fr/)
- `fix:` correctif
- `feat:` nouvelle fonctionnalité
- `docs:` documentation
- `test:` ajout ou modification de tests
- `refactor:` refactoring
- `chore:` maintenance (dépendances, configuration CI)

**Code :**
- PSR-12 obligatoire — Pint vérifie et corrige automatiquement
- PHPStan niveau 5 — 0 erreur tolérée (montée progressive vers le niveau 8 en cours, par paliers)
- Tout nouveau code doit avoir des tests PHPUnit correspondants
- Pas de mocks — les tests utilisent de vraies bases MySQL de test

**Pull Requests :**
- Cibler la branche `develop`, jamais directement `main`
- Décrire les changements et pourquoi ils sont nécessaires
- Le CI doit être vert avant review
- Une PR = un sujet

---

## Contribuer à la documentation

La documentation est dans `docs/` et à la racine du projet, au format Markdown.

### Structure des documents racine

| Fichier | Contenu |
|---------|---------|
| `README.md` | Présentation générale, fonctionnalités, installation rapide |
| `INSTALL.md` | Guide d'installation technique complet |
| `CONTRIBUTING.md` | Ce fichier — comment contribuer |
| `SECURITY.md` | Politique de sécurité et signalement de vulnérabilités |
| `CHANGELOG.md` | Historique des versions |
| `ROADMAP.md` | Feuille de route détaillée |
| `ARGUMENTAIRE.md` | Argumentaires pour les collectivités et partenaires |
| `OBJECTIONS.md` | Questions fréquentes et réponses |
| `CODE_OF_CONDUCT.md` | Code de conduite |

### Structure du répertoire `docs/`

```
docs/
├── README.md                        ← index de la documentation
├── glossaire.md                     ← glossaire des termes techniques et métier
├── plan-de-travail.md               ← suivi du plan de développement
├── checklist-tests-pladigit.md      ← tests fonctionnels
│
├── 01-product/
│   ├── CDC_Pladigit_v2.4.md         ← cahier des charges complet
│   ├── vision-2030.md               ← vision et principes
│   ├── charte-choix-technologiques.md
│   ├── module-system.md
│   └── ROADMAP.md
│
├── 02-architecture/
│   ├── installation.md              ← cahier des charges installeur
│   ├── deploy/                      ← guides de déploiement
│   └── annexes/                     ← documentation technique par module
│
├── 03-guides/                       ← guides utilisateurs par profil
│
├── 04-adr/                          ← décisions architecturales ADR-001 à ADR-043
│
└── 05-exploitation/                 ← checklist production, maintenance
```

---

## Types de contributions appréciées

Au-delà du code :
- **Retours d'usage terrain** : vous avez installé Pladigit dans une collectivité ? Votre retour est précieux
- **Documentation** : améliorer un guide utilisateur, corriger une imprécision
- **Tests** : signaler un comportement inattendu avec les étapes pour reproduire
- **Relecture** : corriger les fautes, clarifier les formulations

---

## Soutenir le projet

L'infrastructure de démonstration (VPS OVH, domaine `pladigit.fr`) est financée personnellement.
Si ce projet vous est utile, vous pouvez contribuer via [GitHub Sponsors](https://github.com/sponsors/jpbosse).

---

## Code de conduite

Voir [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).
