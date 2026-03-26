🛠
PLADIGIT
Guide de Maintenance
Phases 1 & 2 — Infrastructure & Auth

# 1. Base de Données
## 1.1 Architecture
Pladigit utilise deux types de bases MySQL :
- pladigit_platform — base centrale (organisations, super-admin)
- pladigit_[slug] — une base par tenant (users, departments, audit_logs...)
## 1.2 Migrations
Migrer la base platform
php artisan migrate \
--path=database/migrations/platform \
--database=mysql
Migrer la base tenant
php artisan migrate \
--path=database/migrations/tenant \
--database=tenant
⚠ Ne JAMAIS utiliser migrate:fresh sans préciser --database et --path.
⚠ Un migrate:fresh sans paramètres écrase pladigit_platform.
✓ Utilisez les alias bash définis en section 6 pour éviter les erreurs.
## 1.3 Restaurer la table organizations
Si la table organizations disparaît (suite à un migrate:fresh accidentel) :
php artisan migrate \
--path=database/migrations/platform \
--database=mysql

-- Puis réinsérer manuellement les organisations :
USE pladigit_platform;
INSERT INTO organizations (name, slug, db_name, status, plan, max_users,
primary_color, timezone, locale, created_at, updated_at) VALUES
('Organisation Demo', 'demo', 'pladigit_demo', 'active',
'communautaire', 9999, '#1E3A5F', 'Europe/Paris', 'fr_FR', NOW(), NOW()),
('CC de l\'île de Noirmoutier', 'ccnoirmoutier', 'pladigit_ccnoirmoutier',
'active', 'communautaire', 9999, '#1E3A5F', 'Europe/Paris', 'fr_FR', NOW(), NOW());

# 2. Gestion des Utilisateurs
## 2.1 Réinitialiser un mot de passe
Si un utilisateur ne peut plus se connecter :
# Étape 1 — Générer un hash bcrypt
php artisan tinker --no-interaction << 'EOF'
echo \Illuminate\Support\Facades\Hash::make('NouveauMdP!123');
EOF

# Étape 2 — Appliquer dans MySQL
mysql -u pladigit -p pladigit_[slug]
UPDATE users SET
password_hash = 'HASH_GÉNÉRÉ',
login_attempts = 0,
force_pwd_change = 1
WHERE email = 'utilisateur@exemple.fr';
⚠ Ne jamais stocker de mot de passe en clair dans la base ou les logs.
## 2.2 Débloquer un compte
Si login_attempts >= seuil ou locked_until est défini :
UPDATE users SET
login_attempts = 0,
locked_until = NULL
WHERE email = 'utilisateur@exemple.fr';
## 2.3 Vérifier l'état d'un utilisateur
SELECT id, name, email, status, role,
login_attempts, locked_until, force_pwd_change,
last_login_at
FROM users
WHERE email LIKE '%nom%';

# 3. Cache et Vues
## 3.1 Vider tous les caches
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
✓ À effectuer après chaque déploiement ou modification de fichiers .env.
## 3.2 Supprimer une vue compilée spécifique
Si une vue Blade est bloquée sur une ancienne version compilée :
# Identifier le fichier compilé
php artisan view:clear
# Si le problème persiste, supprimer manuellement
rm storage/framework/views/*.php

# 4. Permissions Fichiers
## 4.1 Corriger les permissions storage
En cas d'erreur "Permission denied" sur storage/ ou bootstrap/cache/ :
sudo chown -R deploy:www-data storage/ bootstrap/cache/
sudo chmod -R 775 storage/ bootstrap/cache/
## 4.2 Vérifier les permissions
ls -la storage/
ls -la bootstrap/cache/

# 5. Tests
## 5.1 Lancer la suite complète
php artisan test 2>&1

# Avec arrêt au premier échec
php artisan test --stop-on-failure 2>&1
## 5.2 Lancer un fichier spécifique
php artisan test tests/Feature/Admin/UserTest.php 2>&1
php artisan test tests/Feature/Admin/DepartmentTest.php 2>&1
php artisan test tests/Feature/SuperAdmin/OrganizationTest.php 2>&1
php artisan test tests/Feature/ProfileTest.php 2>&1
php artisan test tests/Feature/DashboardTest.php 2>&1
## 5.3 Lancer un test précis
php artisan test \
--filter="test_admin_peut_créer_un_utilisateur" 2>&1
## 5.4 Architecture des bases de test

# 6. Pipeline CI/CD
## 6.1 Étapes du pipeline
## 6.2 Corriger le style avant commit
# Vérifier sans corriger
./vendor/bin/pint --test

# Corriger automatiquement
./vendor/bin/pint

# Corriger un fichier spécifique
./vendor/bin/pint app/Http/Controllers/Admin/UserController.php
## 6.3 PHPStan
./vendor/bin/phpstan analyse --memory-limit=256M
✓ Les relations Eloquent doivent avoir des annotations @return avec génériques (ex: @return HasMany<Department, $this>).

# 7. Logs et Diagnostic
## 7.1 Consulter les logs Laravel
# Dernières 50 lignes
tail -50 storage/logs/laravel.log

# En temps réel
tail -f storage/logs/laravel.log

# Filtrer les erreurs
grep "ERROR\|Exception" storage/logs/laravel.log | tail -20
## 7.2 Diagnostic rapide 500
# Identifier l'erreur exacte d'un test
php artisan test --filter="nom_du_test" 2>&1 | grep -A3 "Error\|Exception"

# Tester une vue manuellement
php artisan tinker
// view('admin.departments.index')->render();

# 8. Aliases Bash Recommandés
Ajoutez ces alias dans ~/.bashrc pour éviter les erreurs de migration :
echo "alias migrate-platform='php artisan migrate --database=mysql --path=database/migrations/platform --force'" >> ~/.bashrc
echo "alias migrate-tenant='php artisan migrate --database=tenant --path=database/migrations/tenant --force'" >> ~/.bashrc
echo "alias migrate-fresh-tenant='php artisan migrate:fresh --database=tenant --path=database/migrations/tenant --force'" >> ~/.bashrc
source ~/.bashrc
⚠ Ne jamais utiliser migrate:fresh sans alias — risque d'écraser pladigit_platform.

# 9. Checklist Déploiement
Avant chaque mise en production :
- ☐  git pull origin main
- ☐  composer install --no-dev --optimize-autoloader
- ☐  php artisan migrate --database=mysql --path=database/migrations/platform --force
- ☐  php artisan view:clear && php artisan cache:clear && php artisan config:clear
- ☐  sudo chown -R deploy:www-data storage/ bootstrap/cache/
- ☐  Vérifier les logs : tail -20 storage/logs/laravel.log
- ☐  Tester la connexion sur une organisation