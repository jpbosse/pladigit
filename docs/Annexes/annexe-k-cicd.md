# Annexe K — CI/CD et tests automatisés

## K.1 — Pipeline GitHub Actions (Mars 2026)

## K.2 — Environnements Git
- develop : branche de développement — CI sur chaque push.
- main : branche de production — merge uniquement depuis develop après CI vert.
- staging : déploiement automatique depuis main pour validation avant production.

## K.3 — Règles qualité
- Aucun merge sur main si un check CI échoue.
- PHPStan niveau 5 : aucune erreur de typage tolérée.
- Pint : formatage automatique via pre-commit hook recommandé.
- Couverture de tests : toutes les couches critiques (auth, rôles, politique MDP, tenant, LDAP, branding).
- Tests LDAP (groupe ldap) exclus du CI — dépendance OpenLDAP Docker, lancés manuellement.

## K.4 — Suite de tests complète (Phase 2 — Mars 2026)

## K.5 — Configuration tests (phpunit.xml)
- Connexion MySQL tenant dédiée pour les tests (pladigit_testing_tenant).
- BCRYPT_ROUNDS=4 en environnement test (rapidité sans impact sécurité).
- APP_ENV=testing — pas de cache, pas de queue asynchrone.
- Tests LDAP exclus par défaut : #[Group("ldap")] → --exclude-group ldap sur CI.