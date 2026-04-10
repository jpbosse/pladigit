# Contribuer à Pladigit

Merci de l'intérêt que vous portez à Pladigit. Les contributions sont les bienvenues — qu'il s'agisse de code, de documentation, de retours d'usage ou de signalements de bugs.

---

## Avant de commencer

Pladigit est un projet open source porté par **un développeur unique**, retraité, à titre personnel. Il n'existe pas d'équipe de maintenance, pas de SLA, pas d'entreprise derrière. C'est un bien commun numérique, pas un produit commercial.

Cela implique :
- Les réponses aux issues et PR peuvent prendre du temps
- Les contributions documentées et testées sont beaucoup plus faciles à intégrer
- La bienveillance est de mise dans tous les échanges

---

## Signaler un bug

1. Vérifier que le bug n'est pas déjà signalé dans les [issues GitHub](https://github.com/jpbosse/pladigit/issues)
2. Ouvrir une nouvelle issue avec :
   - La version de Pladigit concernée
   - Les étapes pour reproduire
   - Le comportement attendu vs observé
   - Les logs pertinents (`storage/logs/laravel.log`)
   - L'environnement (OS, PHP, MySQL, Redis)

> Pour les vulnérabilités de sécurité : voir [SECURITY.md](SECURITY.md) — **ne pas ouvrir d'issue publique**.

---

## Proposer une fonctionnalité

Ouvrir une issue avec le label `enhancement` en décrivant :
- Le besoin concret (quel utilisateur, quel contexte)
- La solution envisagée
- Les alternatives considérées

Les fonctionnalités liées aux collectivités françaises et à la souveraineté numérique sont prioritaires.

---

## Contribuer du code

### Prérequis

- PHP 8.4, Composer 2, Node.js 20, MySQL 8, Redis 7
- Lire [INSTALL.md](INSTALL.md) pour l'environnement de développement
- Lire [CLAUDE.md](CLAUDE.md) pour les commandes et l'architecture

### Workflow

```bash
# 1. Fork + clone
git clone https://github.com/VOTRE_COMPTE/pladigit.git
cd pladigit

# 2. Créer une branche
git checkout -b fix/description-courte
# ou
git checkout -b feature/description-courte

# 3. Développer + tester
php artisan test --exclude-group ldap,integration
./vendor/bin/pint
./vendor/bin/phpstan analyse --memory-limit=512M

# 4. Commit
git commit -m "fix: description courte du correctif"

# 5. Push + Pull Request vers la branche develop
git push origin fix/description-courte
```

### Conventions

**Branches :**
- `fix/` — correctifs
- `feature/` — nouvelles fonctionnalités
- `docs/` — documentation uniquement
- `refactor/` — refactoring sans changement de comportement

**Commits :** format [Conventional Commits](https://www.conventionalcommits.org/fr/)
- `fix:` correctif
- `feat:` nouvelle fonctionnalité
- `docs:` documentation
- `test:` ajout/modification de tests
- `refactor:` refactoring
- `chore:` maintenance (deps, config CI)

**Code :**
- PSR-12 obligatoire (Pint vérifie automatiquement)
- PHPStan niveau 5 — 0 erreur toléré
- Tout nouveau code doit avoir des tests PHPUnit correspondants
- Pas de mocks — les tests utilisent de vraies bases MySQL de test

**Pull Requests :**
- Cibler la branche `develop`, jamais `main`
- Décrire les changements, pourquoi ils sont nécessaires
- La CI doit être verte avant review
- Une PR = un sujet

---

## Contribuer à la documentation

La documentation est dans `docs/` au format Markdown. Les corrections, mises à jour et traductions sont bienvenues via PR.

Structure :
```
docs/
├── README.md          ← index
├── adr/               ← décisions architecturales (ADR)
├── annexes/           ← documentation technique par module
├── guides/            ← guides utilisateurs
└── divers/            ← installation, maintenance, checklist
```

---

## Soutenir le projet

L'infrastructure de démonstration (VPS OVH, domaine `pladigit.fr`) est financée personnellement.  
Si ce projet vous est utile, vous pouvez contribuer financièrement via [GitHub Sponsors](https://github.com/sponsors/jpbosse).

---

## Code de conduite

Ce projet adhère au principe simple : **respect et bienveillance**. Les échanges agressifs, irrespectueux ou hors-sujet ne seront pas tolérés.
