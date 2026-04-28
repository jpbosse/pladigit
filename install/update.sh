#!/bin/bash
# =============================================================================
# Pladigit — Mise à jour de l'instance depuis le Super Admin
# Exécuté en root via sudoers (appelé par www-data)
# Usage : /var/www/pladigit/install/update.sh <log_file> <root_dir>
# =============================================================================

LOG_FILE="${1:-/var/www/pladigit/storage/logs/updates/update.log}"
ROOT_DIR="${2:-/var/www/pladigit}"
PHP="${PHP:-php8.4}"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

fail() {
    local MSG="$1"
    log "✗ ERREUR : $MSG"
    cd "$ROOT_DIR" && $PHP artisan pladigit:update-status error "$MSG" >> "$LOG_FILE" 2>&1 || true
    cd "$ROOT_DIR" && $PHP artisan up >> "$LOG_FILE" 2>&1 || true
    exit 1
}

# Créer le répertoire de logs si besoin
mkdir -p "$(dirname "$LOG_FILE")"

log "══════════════════════════════════════════════"
log "Début de la mise à jour Pladigit"
log "Répertoire : $ROOT_DIR"
log "══════════════════════════════════════════════"

cd "$ROOT_DIR" || fail "Répertoire introuvable : $ROOT_DIR"

# ── 1. Mode maintenance ───────────────────────────────────────────────────────
log "Activation du mode maintenance..."
$PHP artisan down >> "$LOG_FILE" 2>&1 \
    || fail "Impossible d'activer le mode maintenance"
log "✓ Mode maintenance activé"

# ── 2. git pull ───────────────────────────────────────────────────────────────
log "Récupération des sources (git pull origin main)..."
git config --global --add safe.directory "$ROOT_DIR" >> "$LOG_FILE" 2>&1 || true
git pull origin main >> "$LOG_FILE" 2>&1 \
    || fail "git pull origin main a échoué — vérifiez la connectivité réseau ou les conflits"
log "✓ Sources mises à jour"

# ── 3. Composer ───────────────────────────────────────────────────────────────
log "Installation des dépendances PHP (composer install --no-dev)..."
composer install --no-dev --optimize-autoloader --no-interaction >> "$LOG_FILE" 2>&1 \
    || fail "composer install a échoué"
log "✓ Dépendances PHP installées"

# ── 4. npm + build ────────────────────────────────────────────────────────────
log "Installation des dépendances JS (npm ci)..."
npm ci >> "$LOG_FILE" 2>&1 \
    || fail "npm ci a échoué"
log "✓ Dépendances JS installées"

log "Build des assets (npm run build)..."
npm run build >> "$LOG_FILE" 2>&1 \
    || fail "npm run build a échoué"
log "✓ Assets compilés"

# ── 5. Migrations plateforme ──────────────────────────────────────────────────
log "Migration de la base plateforme..."
$PHP artisan migrate --force >> "$LOG_FILE" 2>&1 \
    || fail "La migration de la base plateforme a échoué"
log "✓ Base plateforme migrée"

log "Migration du schéma platform (database/migrations/platform)..."
$PHP artisan migrate --path=database/migrations/platform --force >> "$LOG_FILE" 2>&1 \
    || fail "La migration platform a échoué"
log "✓ Schéma platform migré"

# ── 6. Migrations tenant ──────────────────────────────────────────────────────
log "Migration de toutes les bases tenant..."
$PHP artisan migrate:tenants --force >> "$LOG_FILE" 2>&1 \
    || fail "La migration des bases tenant a échoué"
log "✓ Bases tenant migrées"

# ── 7. Caches ─────────────────────────────────────────────────────────────────
log "Régénération des caches (config, route, view)..."
$PHP artisan config:cache >> "$LOG_FILE" 2>&1 \
    || fail "config:cache a échoué"
$PHP artisan route:cache >> "$LOG_FILE" 2>&1 \
    || fail "route:cache a échoué"
$PHP artisan view:cache >> "$LOG_FILE" 2>&1 \
    || fail "view:cache a échoué"
log "✓ Caches régénérés"

# ── 8. Queue ──────────────────────────────────────────────────────────────────
log "Redémarrage des workers de queue..."
$PHP artisan queue:restart >> "$LOG_FILE" 2>&1 || true
log "✓ Workers de queue redémarrés"

# ── 9. Désactivation maintenance ─────────────────────────────────────────────
log "Désactivation du mode maintenance..."
$PHP artisan up >> "$LOG_FILE" 2>&1 \
    || fail "Impossible de désactiver le mode maintenance"
log "✓ Mode maintenance désactivé"

log "══════════════════════════════════════════════"
log "✓ Mise à jour terminée avec succès"
log "══════════════════════════════════════════════"

$PHP artisan pladigit:update-status success "Mise à jour terminée avec succès" >> "$LOG_FILE" 2>&1 || true

exit 0
