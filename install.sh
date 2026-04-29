#!/usr/bin/env bash
# ==============================================================================
#  Pladigit — Script d'installation automatique
#  Version : 1.0.0
#  Cible   : Ubuntu 22.04 LTS / 24.04 LTS
#  Usage   : curl -fsSL https://pladigit.fr/get-install | sudo bash
# ==============================================================================
set -euo pipefail

# ── Couleurs ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

# ── Configuration ─────────────────────────────────────────────────────────────
PLADIGIT_DIR="/var/www/pladigit"
PLADIGIT_REPO="https://github.com/jpbosse/pladigit.git"
PLADIGIT_USER="www-data"
LOG_FILE="/var/log/pladigit-install.log"
MIN_RAM_MB=2048
MIN_DISK_GB=10
PHP_VERSION="8.4"

# ── Helpers ───────────────────────────────────────────────────────────────────
log()     { echo -e "${GREEN}✓${NC} $*" | tee -a "$LOG_FILE"; }
warn()    { echo -e "${YELLOW}⚠${NC}  $*" | tee -a "$LOG_FILE"; }
error()   { echo -e "${RED}✗${NC}  $*" | tee -a "$LOG_FILE"; }
info()    { echo -e "${CYAN}→${NC}  $*" | tee -a "$LOG_FILE"; }
title()   { echo -e "\n${BOLD}${BLUE}$*${NC}" | tee -a "$LOG_FILE"; }
step()    { echo -e "\n${BOLD}[ $* ]${NC}" | tee -a "$LOG_FILE"; }
die()     { error "$*"; echo -e "\n${RED}Installation interrompue. Consultez : $LOG_FILE${NC}"; exit 1; }

banner() {
    clear
    echo -e "${BLUE}"
    echo "  ██████╗ ██╗      █████╗ ██████╗ ██╗ ██████╗ ██╗████████╗"
    echo "  ██╔══██╗██║     ██╔══██╗██╔══██╗██║██╔════╝ ██║╚══██╔══╝"
    echo "  ██████╔╝██║     ███████║██║  ██║██║██║  ███╗██║   ██║   "
    echo "  ██╔═══╝ ██║     ██╔══██║██║  ██║██║██║   ██║██║   ██║   "
    echo "  ██║     ███████╗██║  ██║██████╔╝██║╚██████╔╝██║   ██║   "
    echo "  ╚═╝     ╚══════╝╚═╝  ╚═╝╚═════╝ ╚═╝ ╚═════╝ ╚═╝   ╚═╝   "
    echo -e "${NC}"
    echo -e "  ${BOLD}Plateforme de Digitalisation pour Collectivités${NC}"
    echo -e "  Installation automatique — v1.0.0"
    echo -e "  ─────────────────────────────────────────────────"
    echo ""
}

progress() {
    local current=$1
    local total=$2
    local label=$3
    local pct=$(( current * 100 / total ))
    local filled=$(( pct / 5 ))
    local bar=""
    for ((i=0; i<filled; i++)); do bar+="█"; done
    for ((i=filled; i<20; i++)); do bar+="░"; done
    printf "\r  [${GREEN}%s${NC}] %3d%%  %s" "$bar" "$pct" "$label"
    echo "" | tee -a "$LOG_FILE" > /dev/null
}

# ── 0. Vérifications préalables ───────────────────────────────────────────────
check_prerequisites() {
    step "Étape 1/7 — Vérification du système"

    # Root
    if [[ $EUID -ne 0 ]]; then
        die "Ce script doit être exécuté en tant que root (sudo)."
    fi
    log "Droits root : OK"

    # OS
    if [[ ! -f /etc/os-release ]]; then
        die "Système d'exploitation non reconnu."
    fi
    source /etc/os-release
    if [[ "$ID" != "ubuntu" ]]; then
        die "Pladigit nécessite Ubuntu 22.04 ou 24.04. Système détecté : $ID $VERSION_ID"
    fi
    if [[ "$VERSION_ID" != "22.04" && "$VERSION_ID" != "24.04" ]]; then
        die "Version Ubuntu non supportée : $VERSION_ID. Pladigit nécessite Ubuntu 22.04 ou 24.04 LTS."
    else
        log "Système : Ubuntu $VERSION_ID — OK"
    fi

    # RAM
    local ram_mb
    ram_mb=$(awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo)
    if (( ram_mb < MIN_RAM_MB )); then
        die "RAM insuffisante : ${ram_mb} Mo détectés, minimum requis : ${MIN_RAM_MB} Mo."
    fi
    log "RAM : ${ram_mb} Mo — OK"


    # Extension LVM automatique (Ubuntu Server alloue ~50% par défaut)
    if command -v lvextend &>/dev/null; then
        local lv_path
        lv_path=$(lvdisplay 2>/dev/null | awk '/LV Path/{print $3}' | head -1)
        if [[ -n "$lv_path" ]]; then
            local free_pe
            free_pe=$(vgdisplay 2>/dev/null | awk '/Free.*PE/{print $5}' | head -1)
            if [[ -n "$free_pe" && "$free_pe" -gt 0 ]]; then
                info "Extension automatique du volume LVM..."
                lvextend -l +100%FREE "$lv_path" >> "$LOG_FILE" 2>&1 || true
                resize2fs "$lv_path" >> "$LOG_FILE" 2>&1 || true
                log "Volume LVM étendu automatiquement"
            fi
        fi
    fi

    # Disque
    local disk_gb
    disk_gb=$(df / | awk 'NR==2 {printf "%d", $4/1024/1024}')
    if (( disk_gb < MIN_DISK_GB )); then
        die "Espace disque insuffisant : ${disk_gb} Go disponibles, minimum requis : ${MIN_DISK_GB} Go."
    fi
    log "Disque : ${disk_gb} Go disponibles — OK"

    # Connexion internet
    if ! curl -sf --max-time 5 https://github.com > /dev/null 2>&1; then
        die "Pas de connexion internet. Vérifiez votre réseau."
    fi
    log "Connexion internet — OK"

    # Ports 80 et 443
    for port in 80 443; do
        if ss -tlnp | grep -q ":${port} "; then
            warn "Port ${port} déjà utilisé. Nginx pourrait ne pas démarrer correctement."
        else
            log "Port ${port} disponible — OK"
        fi
    done

    progress 1 7 "Vérification système"
}

# ── 1. Mise à jour système ────────────────────────────────────────────────────
update_system() {
    # Attendre que le verrou apt soit libéré (unattended-upgrades au boot)
    local waited=0
    while fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1; do
        if [ "$waited" -eq 0 ]; then
            info "Mise à jour du système en attente (apt en cours d'utilisation)..."
        fi
        sleep 5
        waited=$((waited + 5))
        if [ "$waited" -gt 300 ]; then
            warn "apt toujours occupé après 5 minutes — on force..."
            rm -f /var/lib/dpkg/lock-frontend /var/lib/dpkg/lock /var/cache/apt/archives/lock
            dpkg --configure -a >> "$LOG_FILE" 2>&1 || true
            break
        fi
    done
    step "Étape 2/7 — Mise à jour du système"
    info "Mise à jour des paquets (peut prendre quelques minutes)..."

    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq >> "$LOG_FILE" 2>&1 || die "Impossible de mettre à jour les sources apt."
    apt-get upgrade -y -qq >> "$LOG_FILE" 2>&1 || warn "Mise à jour partielle — on continue."
    apt-get install -y -qq \
        curl wget git unzip zip \
        software-properties-common \
        apt-transport-https ca-certificates \
        gnupg lsb-release \
        >> "$LOG_FILE" 2>&1 || die "Impossible d'installer les outils de base."

    log "Système mis à jour"
    progress 2 7 "Mise à jour système"
}

# ── 2. PHP 8.4 ────────────────────────────────────────────────────────────────
install_php() {
    step "Étape 3/7 — Installation de PHP ${PHP_VERSION}"

    # Dépôt Ondrej
    if ! grep -q "ondrej/php" /etc/apt/sources.list.d/*.list 2>/dev/null; then
        add-apt-repository -y ppa:ondrej/php >> "$LOG_FILE" 2>&1 || die "Impossible d'ajouter le dépôt PHP."
        apt-get update -qq >> "$LOG_FILE" 2>&1
    fi

    local php_packages=(
        "php${PHP_VERSION}-fpm"
        "php${PHP_VERSION}-cli"
        "php${PHP_VERSION}-mysql"
        "php${PHP_VERSION}-redis"
        "php${PHP_VERSION}-xml"
        "php${PHP_VERSION}-curl"
        "php${PHP_VERSION}-mbstring"
        "php${PHP_VERSION}-zip"
        "php${PHP_VERSION}-gd"
        "php${PHP_VERSION}-intl"
        "php${PHP_VERSION}-bcmath"
        "php${PHP_VERSION}-opcache"
        "php${PHP_VERSION}-imagick"
        "php${PHP_VERSION}-ldap"
    )

    apt-get install -y -qq "${php_packages[@]}" >> "$LOG_FILE" 2>&1 \
        || die "Impossible d'installer PHP ${PHP_VERSION}."

    log "PHP ${PHP_VERSION} installé"

    # Composer
    if ! command -v composer &>/dev/null; then
        info "Installation de Composer..."
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
            >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Composer."
    fi
    log "Composer : $(composer --version --no-ansi 2>/dev/null | head -1)"

    progress 3 7 "PHP ${PHP_VERSION} + Composer"
}

# ── 3. MySQL 8 ────────────────────────────────────────────────────────────────
install_mysql() {
    step "Étape 4/7 — Installation de MySQL 8"

    if ! command -v mysql &>/dev/null; then
        apt-get install -y -qq mysql-server >> "$LOG_FILE" 2>&1 \
            || die "Impossible d'installer MySQL."
        systemctl enable mysql >> "$LOG_FILE" 2>&1
        systemctl start mysql >> "$LOG_FILE" 2>&1
    else
        log "MySQL déjà installé — on continue"
    fi

    log "MySQL : $(mysql --version 2>/dev/null | head -1)"

    # Activer authentification par mot de passe pour root (Ubuntu auth_socket par défaut)
    info "Configuration authentification MySQL root..."
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY ''; FLUSH PRIVILEGES;"         >> "$LOG_FILE" 2>&1 || warn "ALTER USER root échoué — root a peut-être déjà un mot de passe."
    log "Authentification MySQL configurée"

    progress 4 7 "MySQL 8"
}

# ── 4. Redis + Nginx + Supervisor + Node.js ───────────────────────────────────
install_services() {
    step "Étape 5/7 — Installation Redis, Nginx, Supervisor, Node.js"

    # Redis
    apt-get install -y -qq redis-server >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Redis."
    systemctl enable redis-server >> "$LOG_FILE" 2>&1
    systemctl start redis-server >> "$LOG_FILE" 2>&1
    log "Redis installé"

    # Nginx
    apt-get install -y -qq nginx >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Nginx."
    systemctl enable nginx >> "$LOG_FILE" 2>&1
    log "Nginx installé"

    # Supervisor
    apt-get install -y -qq supervisor >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Supervisor."
    systemctl enable supervisor >> "$LOG_FILE" 2>&1
    log "Supervisor installé"

    # Node.js 20
    if ! command -v node &>/dev/null; then
        curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >> "$LOG_FILE" 2>&1
        apt-get install -y -qq nodejs >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Node.js."
    fi
    log "Node.js : $(node --version 2>/dev/null)"

    # UFW
    if command -v ufw &>/dev/null; then
        ufw allow 22/tcp  >> "$LOG_FILE" 2>&1 || true
        ufw allow 80/tcp  >> "$LOG_FILE" 2>&1 || true
        ufw allow 443/tcp >> "$LOG_FILE" 2>&1 || true
        ufw --force enable >> "$LOG_FILE" 2>&1 || true
        log "Pare-feu UFW configuré (22, 80, 443)"
    fi

    progress 5 7 "Services (Redis, Nginx, Supervisor, Node.js)"
}

# ── 5. Clonage et dépendances Pladigit ───────────────────────────────────────
install_pladigit() {
    step "Étape 6/7 — Installation de Pladigit"

    # Clonage
    if [[ -d "$PLADIGIT_DIR/.git" ]]; then
        info "Répertoire existant détecté — mise à jour..."
        git -C "$PLADIGIT_DIR" pull origin main >> "$LOG_FILE" 2>&1 || warn "git pull échoué — on continue."
    else
        info "Clonage du dépôt..."
        git clone "$PLADIGIT_REPO" "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
            || die "Impossible de cloner le dépôt. Vérifiez votre connexion."
    fi
    log "Code source Pladigit : $PLADIGIT_DIR"

    # Permissions
    chown -R www-data:www-data "$PLADIGIT_DIR"
    chmod -R 755 "$PLADIGIT_DIR"
    chmod -R 775 "$PLADIGIT_DIR/storage" "$PLADIGIT_DIR/bootstrap/cache"
    log "Permissions configurées"

    # Dépendances PHP
    info "Installation des dépendances PHP (Composer)..."
    sudo -u www-data composer install \
        --no-dev --optimize-autoloader --no-interaction \
        --working-dir="$PLADIGIT_DIR" \
        >> "$LOG_FILE" 2>&1 || die "Composer install échoué."
    log "Dépendances PHP installées"

    # Dépendances JS
    info "Installation des dépendances JS (npm)..."
    mkdir -p /var/www/.npm
    chown -R www-data:www-data /var/www/.npm
    sudo -u www-data npm ci --prefix "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
        || die "npm install échoué."
    sudo -u www-data npm run build --prefix "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
        || die "npm build échoué."
    log "Assets JS compilés"

    # Téléchargement du wizard d'installation
    info "Téléchargement du wizard d'installation..."
    mkdir -p "${PLADIGIT_DIR}/install"
    curl -fsSL https://pladigit.fr/install-wizard.php -o "${PLADIGIT_DIR}/install/index.php"         >> "$LOG_FILE" 2>&1 || warn "Wizard non disponible — continuez manuellement."
    chown www-data:www-data "${PLADIGIT_DIR}/install/index.php" 2>/dev/null || true
    log "Wizard d'installation téléchargé"

    # Téléchargement du wizard d'installation
    info "Téléchargement du wizard..."
    mkdir -p "${PLADIGIT_DIR}/install"
    curl -fsSL https://pladigit.fr/install-wizard.php -o "${PLADIGIT_DIR}/install/index.php"         >> "$LOG_FILE" 2>&1 || warn "Wizard non disponible."
    chown -R www-data:www-data "${PLADIGIT_DIR}/install"
    log "Wizard téléchargé"

    # Déploiement du script d'installation Collabora (exécuté en root via sudo)
    info "Déploiement du script Collabora..."
    curl -fsSL https://pladigit.fr/get-collabora-installer -o "${PLADIGIT_DIR}/install/install-collabora.sh" \
        >> "$LOG_FILE" 2>&1 || warn "Script Collabora non disponible depuis le serveur — copie locale utilisée."
    chmod +x "${PLADIGIT_DIR}/install/install-collabora.sh"
    chown root:root "${PLADIGIT_DIR}/install/install-collabora.sh"
    log "Script Collabora déployé"

    # Autoriser www-data à exécuter install-collabora.sh en root sans mot de passe
    SUDOERS_LINE="www-data ALL=(root) NOPASSWD: ${PLADIGIT_DIR}/install/install-collabora.sh"
    SUDOERS_FILE="/etc/sudoers.d/pladigit-collabora"
    echo "$SUDOERS_LINE" > "$SUDOERS_FILE"
    chmod 440 "$SUDOERS_FILE"
    # Valider la syntaxe sudoers
    visudo -c -f "$SUDOERS_FILE" >> "$LOG_FILE" 2>&1 \
        && log "Règle sudoers Collabora configurée" \
        || { warn "Règle sudoers invalide — suppression."; rm -f "$SUDOERS_FILE"; }

    progress 6 7 "Pladigit installé"
}

# ── 6. Cron Laravel scheduler ────────────────────────────────────────────────
setup_cron() {
    local CRON_ENTRY="* * * * * cd ${PLADIGIT_DIR} && php artisan schedule:run >> /dev/null 2>&1"

    if crontab -u www-data -l 2>/dev/null | grep -qF "schedule:run"; then
        log "Cron Laravel scheduler : déjà configuré"
    else
        (crontab -u www-data -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -u www-data -
        log "Cron Laravel scheduler configuré (www-data)"
    fi
}

# ── 7. Configuration Nginx ────────────────────────────────────────────────────
configure_nginx() {
    step "Étape 7/7 — Configuration Nginx"

    # Récupérer l'IP locale
    local server_ip
    server_ip=$(hostname -I | awk '{print $1}')

    cat > /etc/nginx/sites-available/pladigit << NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    root ${PLADIGIT_DIR}/public;
    index index.php index.html;

    client_max_body_size 100M;

    # Masquer la version Nginx
    server_tokens off;

    # ── Headers HTTP de sécurité ──────────────────────────────────────────
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'self';" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Bloquer les fichiers sensibles (défense en profondeur)
    location ~ ^/(\.env|\.git|composer\.(json|lock)) {
        deny all;
    }

    # Wizard d'installation
    location = /install { return 301 /install/; }
    location /install/ {
        root /var/www/pladigit;
        index index.php;
        location ~ \.php$ {
            fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/pladigit\$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 300;
        }
    }
}
NGINX

    # Activer le site
    ln -sf /etc/nginx/sites-available/pladigit /etc/nginx/sites-enabled/pladigit
    rm -f /etc/nginx/sites-enabled/default

    nginx -t >> "$LOG_FILE" 2>&1 || die "Configuration Nginx invalide."
    systemctl restart nginx >> "$LOG_FILE" 2>&1 || die "Impossible de redémarrer Nginx."
    systemctl restart "php${PHP_VERSION}-fpm" >> "$LOG_FILE" 2>&1

    log "Nginx configuré et démarré"
    progress 7 7 "Configuration finale"
}

# ── 7. Écran de succès ────────────────────────────────────────────────────────

show_success() {
    local server_ip
    server_ip=$(hostname -I | awk '{print $1}')
    echo ""
    echo -e "${GREEN}${BOLD}"
    echo "  ╔══════════════════════════════════════════════════════════╗"
    echo "  ║        ✅  Environnement prêt !                          ║"
    echo "  ║        🚀  Pladigit est installé sur ce serveur.         ║"
    echo "  ╚══════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo -e "  ${BOLD}Ce qui vient d'être installé :${NC}"
    echo -e "  • PHP 8.4, MySQL 8, Redis, Nginx, Supervisor, Node.js"
    echo -e "  • Code source Pladigit et toutes ses dépendances"
    echo ""
    echo -e "  ${BOLD}Étape suivante — Configuration :${NC}"
    echo ""
    echo -e "  Sur votre ordinateur, ouvrez un navigateur et accédez à :"
    echo ""
    echo -e "  ${CYAN}${BOLD}  ➜  http://${server_ip}/install/${NC}"
    echo ""
    echo -e "  L'assistant de configuration vous guidera pour :"
    echo -e "  • Configurer la base de données"
    echo -e "  • Définir l'adresse de votre plateforme"
    echo -e "  • Configurer l'envoi d'emails (optionnel)"
    echo -e "  • Créer le premier compte administrateur"
    echo ""
    echo -e "  ${YELLOW}Journal d'installation : ${LOG_FILE}${NC}"
    echo ""
    if [ -n "${DISPLAY:-}" ] || [ -n "${WAYLAND_DISPLAY:-}" ]; then
        xdg-open "http://${server_ip}/install/" 2>/dev/null &
    fi
}

# ── Point d'entrée ────────────────────────────────────────────────────────────
main() {
    # Initialiser le journal
    mkdir -p "$(dirname "$LOG_FILE")"
    echo "=== Pladigit Install Log — $(date) ===" > "$LOG_FILE"

    banner
    echo -e "  Démarrage de l'installation..."
    echo -e "  Journal : ${LOG_FILE}"
    echo ""


    # ── Vérification installation existante ──────────────────────────────────────
    if [[ -f "${PLADIGIT_DIR}/.env" ]] && [[ -f "${PLADIGIT_DIR}/install/.lock" ]]; then
        echo ""
        echo -e "${YELLOW}${BOLD}  ⚠️  Pladigit est déjà installé sur ce serveur.${NC}"
        echo ""
        echo -e "  Que souhaitez-vous faire ?"
        echo ""
        echo -e "  ${BOLD}1)${NC} Mettre à jour  — git pull + migrations + cache (recommandé)"
        echo -e "  ${BOLD}2)${NC} Réinstaller    — repart de zéro (réécrit le .env)"
        echo -e "  ${BOLD}3)${NC} Annuler        — ne rien faire"
        echo ""
        echo -n "  Votre choix [1/2/3] : "
        read -r choix

        case "$choix" in
            1)
                echo ""
                echo -e "${CYAN}${BOLD}  ── Mise à jour de Pladigit ──${NC}"
                echo ""
                cd "$PLADIGIT_DIR" || die "Impossible d'accéder à ${PLADIGIT_DIR}"

                step "Récupération du code..."
                git pull origin main >> "$LOG_FILE" 2>&1 \
                    && log "Code mis à jour" \
                    || warn "git pull échoué — on continue avec la version actuelle"

                step "Mise à jour des dépendances PHP..."
                sudo -u www-data composer install \
                    --no-dev --optimize-autoloader --no-interaction \
                    --working-dir="$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
                    || warn "composer install échoué"

                step "Compilation des assets..."
                sudo -u www-data npm ci --prefix "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
                    && sudo -u www-data npm run build --prefix "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
                    || warn "npm build échoué"

                step "Migrations base de données..."
                sudo -u www-data php "$PLADIGIT_DIR/artisan" migrate --force >> "$LOG_FILE" 2>&1 \
                    || warn "migrate échoué"
                sudo -u www-data php "$PLADIGIT_DIR/artisan" migrate \
                    --path=database/migrations/platform --force >> "$LOG_FILE" 2>&1 \
                    || warn "migrate platform échoué"

                step "Optimisation du cache..."
                sudo -u www-data php "$PLADIGIT_DIR/artisan" config:cache >> "$LOG_FILE" 2>&1
                sudo -u www-data php "$PLADIGIT_DIR/artisan" route:cache  >> "$LOG_FILE" 2>&1
                sudo -u www-data php "$PLADIGIT_DIR/artisan" view:cache   >> "$LOG_FILE" 2>&1

                step "Redémarrage des workers..."
                supervisorctl restart pladigit-worker:* >> "$LOG_FILE" 2>&1 || true

                step "Vérification du cron scheduler..."
                setup_cron

                echo ""
                echo -e "${GREEN}${BOLD}  ✅  Mise à jour terminée !${NC}"
                echo ""
                echo -e "  Pladigit est à jour. Aucune reconfiguration nécessaire."
                echo ""
                exit 0
                ;;
            2)
                echo ""
                echo -e "  ${RED}Réinstallation — le fichier .env sera réécrit.${NC}"
                echo -e "  Vos données ne seront PAS supprimées."
                echo ""
                echo -e "  Pour confirmer, tapez exactement : ${BOLD}je confirme la réinstallation${NC}"
                echo -n "  > "
                read -r confirm
                if [[ "$confirm" != "je confirme la réinstallation" ]]; then
                    echo ""
                    echo -e "  ${GREEN}Annulé. Votre installation est préservée.${NC}"
                    echo ""
                    exit 0
                fi
                echo ""
                warn "Réinstallation confirmée — poursuite..."
                echo ""
                ;;
            *)
                echo ""
                echo -e "  ${GREEN}Annulé. Votre installation est préservée.${NC}"
                echo ""
                exit 0
                ;;
        esac
    fi

    check_prerequisites
    update_system
    install_php
    install_mysql
    install_services
    install_pladigit
    configure_nginx
    setup_cron
    show_success
}

main "$@"
