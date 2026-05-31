#!/usr/bin/env bash
# ==============================================================================
#  Provisionnement Collabora Online (Docker)
#  Module : collabora — capacité « office »
#
#  Autonome et idempotent. Deux appelants prévus :
#    • le runner du wizard, pendant l'installation initiale
#    • install.sh --add-module collabora, pour une activation après coup
#
#  Ne touche PAS au .env (géré par build_env du wizard) ni au vhost Nginx
#  (géré par le rendu Nginx central). Se limite à Docker + coolwsd.xml.
#
#  Usage :
#    DOMAIN=demo.pladigit.fr bash provision.sh
#    bash provision.sh demo.pladigit.fr
# ==============================================================================
set -euo pipefail

DOMAIN="${DOMAIN:-${1:-}}"
LOG_FILE="${LOG_FILE:-/var/log/pladigit-install.log}"
COOLWSD_PATH="${COOLWSD_PATH:-/opt/collabora/coolwsd.xml}"

log() { echo "[collabora] $(date '+%H:%M:%S') $*" | tee -a "$LOG_FILE" >/dev/null; echo "[collabora] $*"; }

[[ -z "$DOMAIN" ]] && { log "✗ DOMAIN manquant — abandon."; exit 1; }
mkdir -p "$(dirname "$LOG_FILE")"

# ── 1. Docker ─────────────────────────────────────────────────────────────────
if ! command -v docker >/dev/null 2>&1; then
    log "Installation de Docker..."
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq docker.io >>"$LOG_FILE" 2>&1 \
        || { log "✗ Docker non installé."; exit 1; }
fi
systemctl enable docker >>"$LOG_FILE" 2>&1 || true
systemctl start  docker >>"$LOG_FILE" 2>&1 || true
sleep 2
systemctl is-active --quiet docker || { log "✗ Docker ne démarre pas."; exit 1; }
log "✓ Docker prêt."

# ── 2. Image (~1.5 Go) ────────────────────────────────────────────────────────
if ! docker image inspect collabora/code >/dev/null 2>&1; then
    log "Téléchargement de l'image collabora/code (~1.5 Go, 10 à 20 min)..."
    docker pull collabora/code >>"$LOG_FILE" 2>&1 || { log "✗ docker pull échoué."; exit 1; }
fi
log "✓ Image Collabora présente."

# ── 3. coolwsd.xml ────────────────────────────────────────────────────────────
mkdir -p "$(dirname "$COOLWSD_PATH")"
APP_WILDCARD="https://*.${DOMAIN}"
# Regex Collabora : host nu avec points échappés (ex: pladigit\.fr)
DOMAIN_RE="${DOMAIN//./\\.}"
cat > "$COOLWSD_PATH" <<XML
<coolwsd>
  <net>
    <content_security_policy>frame-ancestors https://${DOMAIN} ${APP_WILDCARD}</content_security_policy>
  </net>
  <ssl>
    <enable>false</enable>
    <termination>true</termination>
    <as_scheme>true</as_scheme>
  </ssl>
  <logging>
    <level>warning</level>
  </logging>
  <user_interface>
    <mode>compact</mode>
  </user_interface>
  <storage>
    <wopi allow="true">
      <alias_groups mode="groups">
        <group>
          <host allow="true">${DOMAIN_RE}</host>
          <host allow="true">.*\\.${DOMAIN_RE}</host>
        </group>
      </alias_groups>
    </wopi>
  </storage>
</coolwsd>
XML
log "✓ coolwsd.xml écrit (${COOLWSD_PATH})."

# ── 4. Conteneur ──────────────────────────────────────────────────────────────
docker rm -f collabora >>"$LOG_FILE" 2>&1 || true
docker run -d \
    --name collabora \
    --restart always \
    -p 127.0.0.1:9980:9980 \
    -v "${COOLWSD_PATH}:/etc/coolwsd/coolwsd.xml:ro" \
    --cap-add MKNOD \
    collabora/code >>"$LOG_FILE" 2>&1 \
    || { log "✗ docker run échoué."; exit 1; }
log "✓ Conteneur Collabora lancé."

# ── 5. Attente de disponibilité (max 60 s) ────────────────────────────────────
for _ in $(seq 1 12); do
    sleep 5
    if curl -sk http://127.0.0.1:9980/hosting/discovery 2>/dev/null | grep -q "wopi-discovery"; then
        log "✓ Collabora opérationnel (discovery interne OK)."
        exit 0
    fi
done

log "⚠ Conteneur démarré mais discovery interne non confirmé sous 60 s."
log "  → Vérifier : curl -sk http://127.0.0.1:9980/hosting/discovery | grep wopi-discovery"
exit 0
