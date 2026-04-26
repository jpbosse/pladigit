#!/bin/bash
# =============================================================================
# Pladigit — Installation Collabora Online via Docker
# Exécuté en root via sudoers (appelé par le wizard www-data)
# Usage : /var/www/pladigit/install/install-collabora.sh <log_file> <app_url> <root_dir>
# =============================================================================

LOG_FILE="${1:-/var/www/pladigit/install/install.log}"
APP_URL="${2:-}"
ROOT_DIR="${3:-/var/www/pladigit}"

log() {
    echo "[$(date '+%H:%M:%S')] $1" >> "$LOG_FILE"
}

fail() {
    log "✗ ERREUR Collabora : $1"
    exit 1
}

log "--- Début installation Collabora Online ---"

# ── 1. Docker ────────────────────────────────────────────────────────────────
log "Collabora : mise à jour des paquets..."
DEBIAN_FRONTEND=noninteractive apt-get update -qq >> "$LOG_FILE" 2>&1 \
    || fail "apt-get update échoué"

log "Collabora : installation de Docker..."
DEBIAN_FRONTEND=noninteractive apt-get install -y docker.io >> "$LOG_FILE" 2>&1 \
    || fail "apt-get install docker.io échoué"

log "Collabora : démarrage du service Docker..."
systemctl enable docker >> "$LOG_FILE" 2>&1
systemctl start docker  >> "$LOG_FILE" 2>&1
sleep 2

# Vérifier que Docker tourne
if ! systemctl is-active --quiet docker; then
    fail "Le service Docker n'a pas démarré."
fi
log "✓ Docker installé et démarré"

# ── 2. Téléchargement de l'image Collabora ───────────────────────────────────
log "Collabora : téléchargement de l'image Docker (collabora/code ~1.5 Go)..."
log "Collabora : cette étape peut durer 10 à 20 minutes selon votre connexion..."

# docker pull avec suivi de progression via events
docker pull collabora/code >> "$LOG_FILE" 2>&1 &
PULL_PID=$!

# Surveiller la progression toutes les 10 secondes
ELAPSED=0
DOTS=0
while kill -0 "$PULL_PID" 2>/dev/null; do
    sleep 10
    ELAPSED=$((ELAPSED + 10))
    DOTS=$((DOTS + 1))
    MINUTES=$((ELAPSED / 60))
    SECONDS=$((ELAPSED % 60))

    # Estimer la couche en cours via le log docker
    LAYER_INFO=$(tail -5 "$LOG_FILE" 2>/dev/null | grep -o 'Pull complete\|Downloading\|Extracting' | tail -1)

    if [ -n "$LAYER_INFO" ]; then
        log "Collabora : téléchargement en cours... ${MINUTES}m${SECONDS}s (${LAYER_INFO})"
    else
        log "Collabora : téléchargement en cours... ${MINUTES}m${SECONDS}s"
    fi

    # Sécurité : timeout après 30 minutes
    if [ "$ELAPSED" -gt 1800 ]; then
        kill "$PULL_PID" 2>/dev/null
        fail "Timeout — téléchargement Collabora trop long (>30 min). Vérifiez votre connexion."
    fi
done

wait "$PULL_PID"
PULL_EXIT=$?
if [ "$PULL_EXIT" -ne 0 ]; then
    fail "docker pull collabora/code a échoué (code $PULL_EXIT)"
fi
log "✓ Image Collabora téléchargée"

# ── 3. Démarrage du conteneur ────────────────────────────────────────────────
log "Collabora : démarrage du conteneur..."

# Supprimer un conteneur existant éventuellement
docker rm -f collabora >> "$LOG_FILE" 2>&1 || true

WOPI=$(echo "$APP_URL" | sed "s/'//g")
docker run -d \
    --name collabora \
    --restart always \
    -p 9980:9980 \
    -e "aliasgroup1=${WOPI}" \
    -e "username=admin" \
    -e "password=pladigit_$(openssl rand -hex 4)" \
    --cap-add MKNOD \
    collabora/code >> "$LOG_FILE" 2>&1 \
    || fail "docker run collabora/code échoué"

sleep 3

# Vérifier que le conteneur tourne
if ! docker ps --filter "name=collabora" --filter "status=running" | grep -q collabora; then
    fail "Le conteneur Collabora n'est pas démarré. Vérifiez : docker logs collabora"
fi
log "✓ Conteneur Collabora démarré"

# ── 4. Proxy Nginx ───────────────────────────────────────────────────────────
log "Collabora : configuration du proxy Nginx..."

NGINX_CONF="/etc/nginx/sites-available/pladigit"
if [ -f "$NGINX_CONF" ] && ! grep -q "collabora" "$NGINX_CONF"; then
    COLLABORA_BLOCK="
    # Collabora Online
    location /collabora/ {
        proxy_pass http://localhost:9980/;
        proxy_set_header Host \$http_host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 36000s;
        proxy_send_timeout 36000s;
        proxy_connect_timeout 36000s;
    }
    location /collabora/cool/ {
        proxy_pass http://localhost:9980/cool/;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$http_host;
        proxy_read_timeout 36000s;
    }
"
    # Insérer avant le bloc location ~ /\.(?!well-known)
    sed -i "s|location ~ /\\\\.(?!well-known)|${COLLABORA_BLOCK}\n    location ~ /\\.(?!well-known)|" "$NGINX_CONF" \
        >> "$LOG_FILE" 2>&1

    nginx -t >> "$LOG_FILE" 2>&1 \
        && systemctl reload nginx >> "$LOG_FILE" 2>&1 \
        || fail "Configuration Nginx invalide après ajout Collabora"
fi
log "✓ Proxy Nginx Collabora configuré"

# ── 5. Mise à jour .env ──────────────────────────────────────────────────────
log "Collabora : mise à jour de .env..."

ENV_FILE="${ROOT_DIR}/.env"
if [ -f "$ENV_FILE" ]; then
    if grep -q "COLLABORA_URL" "$ENV_FILE"; then
        sed -i "s|COLLABORA_URL=.*|COLLABORA_URL=${APP_URL}/collabora|" "$ENV_FILE"
    else
        echo "COLLABORA_URL=${APP_URL}/collabora" >> "$ENV_FILE"
    fi
fi
log "✓ COLLABORA_URL configurée dans .env"

log "✓ Installation Collabora Online terminée"
exit 0
