-- ============================================================
-- Pladigit — Initialisation des bases de données
-- À exécuter UNE SEULE FOIS en root sur le serveur MySQL
-- ============================================================

-- Base partagée super-administration
CREATE DATABASE IF NOT EXISTS `pladigit_platform`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Base template (copiée pour chaque nouveau tenant)
CREATE DATABASE IF NOT EXISTS `pladigit_tenant_template`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Utilisateur MySQL dédié
CREATE USER IF NOT EXISTS 'pladigit'@'localhost'
  IDENTIFIED BY 'mot_de_passe_fort';  -- À CHANGER !

GRANT ALL PRIVILEGES ON `pladigit_platform`.*       TO 'pladigit'@'localhost';
GRANT ALL PRIVILEGES ON `pladigit_tenant_%`.*       TO 'pladigit'@'localhost';
FLUSH PRIVILEGES;

-- Vérification
SHOW DATABASES LIKE 'pladigit%';
