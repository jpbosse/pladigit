# Pladigit — Plateforme Collaborative Mutualisée

Plateforme SaaS multi-tenant pour les collectivités territoriales.

## Stack Technique

- **Backend :** Laravel 11 / PHP 8.2
- **Frontend :** Livewire 3 + Alpine.js + Blade
- **Base de données :** MySQL 8 (base dédiée par tenant)
- **Cache / Queues :** Redis 7
- **Auth 2FA :** Google2FA (TOTP) + LDAPS (Active Directory)

## Structure Multi-tenant

- `pladigit_platform` — base super-administration (organisations, audit global)
- `pladigit_{slug}` — base dédiée par organisation (users, paramètres, documents…)
- `pladigit_tenant_template` — base template pour la création de nouveaux tenants

## Installation rapide

```bash
composer create-project laravel/laravel pladigit "^11.0"
# Copier les fichiers de ce dépôt dans le projet Laravel
cp .env.example .env
php artisan key:generate
# Créer les bases MySQL (voir database/sql/create_databases.sql)
php artisan migrate --database=mysql --path=database/migrations/platform
```

## Documentation

- [Guide d'Implémentation Phase 1 & 2](docs/)
- [ADR — Architecture Decision Records](docs/adr/)
- [Checklist de validation](docs/checklist.md)

## Phases du Projet

| Phase | Période | Description |
|-------|---------|-------------|
| 1 | Oct–Déc 2025 | Socle technique & qualité |
| 2 | Jan–Mar 2026 | Auth avancée (LDAP + 2FA) |
| 3+ | 2026–2029 | Modules fonctionnels |

---
*Développé par Les Bézots — Projet Pladigit*
