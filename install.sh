#!/usr/bin/env bash
# ==============================================================================
#  Pladigit — Script d'installation automatique
#  Version : 2.0.0
#  Cible   : Ubuntu 22.04 LTS / 24.04 LTS

# ── Relancement avec TTY forcé (indispensable via curl | bash) ────────────────
# Whiptail nécessite stdin/stdout/stderr connectés au terminal réel.
# Via curl | bash, $0 = /dev/stdin — pas de fichier à relancer.
# On s'écrit dans un fichier temporaire puis on se relance depuis ce fichier.
if [ ! -t 0 ] || [ ! -t 1 ]; then
    _tmp=$(mktemp /tmp/pladigit-install-XXXXXX.sh)
    cat "$0" > "$_tmp" 2>/dev/null || cat > "$_tmp"
    chmod +x "$_tmp"
    exec bash "$_tmp" "$@" </dev/tty >/dev/tty 2>/dev/tty
fi
#  Usage   : curl -fsSL https://pladigit.fr/install.sh | sudo bash
# ==============================================================================
set -euo pipefail

# ── Configuration ─────────────────────────────────────────────────────────────
PLADIGIT_DIR="/var/www/pladigit"
PLADIGIT_REPO="https://github.com/jpbosse/pladigit.git"
LOG_FILE="/var/log/pladigit-install.log"
MIN_RAM_MB=2048
MIN_DISK_GB=10
PHP_VERSION="8.4"
INSTALL_VERSION="2.0.0"

# ── Variables globales ────────────────────────────────────────────────────────
PROFIL=""          # 1=commune 2=maison-communes 3=communaute
DOMAIN=""
SSL_EMAIL=""
ADMIN_IPS=""
NEED_SYSTEM_UPDATE=false
NEED_PHP=false
NEED_MYSQL=false
NEED_REDIS=false
NEED_NGINX=false
NEED_SUPERVISOR=false
NEED_NODE=false
ALL_INSTALLED=false
MYSQL_ROOT_PASSWORD=""

# ── Helpers log (sans Whiptail — pour le journal) ─────────────────────────────
LOG_FILE="/var/log/pladigit-install.log"
_log() { echo -e "$*" | tee -a "$LOG_FILE"; }
log()  { _log "✓ $*"; }
warn() { _log "⚠  $*"; }
info() { _log "→  $*"; }
die()  { _log "✗  $*"; whiptail --title "Erreur fatale" --msgbox "❌ $*\n\nConsultez le journal :\n$LOG_FILE" 12 60 2>/dev/tty; exit 1; }

# ── Whiptail helpers ──────────────────────────────────────────────────────────
# Toutes les saisies lisent depuis /dev/tty — fonctionne via curl | bash
wt_msg() {
    # wt_msg "Titre" "Message" [hauteur] [largeur]
    whiptail --title "${1}" --msgbox "${2}" "${3:-12}" "${4:-70}" 2>/dev/tty
}

wt_info() {
    # Boîte non bloquante pendant les opérations longues
    whiptail --title "${1}" --infobox "${2}" "${3:-8}" "${4:-70}" 2>/dev/tty
}

wt_input() {
    # wt_input "Titre" "Question" "Valeur par défaut" → stdout
    whiptail --title "${1}" --inputbox "${2}" 10 70 "${3}" 2>/dev/tty
}

wt_yesno() {
    # wt_yesno "Titre" "Question" → 0=oui 1=non
    whiptail --title "${1}" --yesno "${2}" 10 70 2>/dev/tty
}

wt_gauge() {
    # wt_gauge "Message" pct
    echo "${2}" | whiptail --title "Pladigit — Installation" \
        --gauge "${1}" 8 70 0 2>/dev/tty
}

wt_progress() {
    # Affiche une progression via gauge (pipe)
    local pct="${1}"
    local msg="${2}"
    echo "$pct"
    _log "[${pct}%] ${msg}"
}

# ── Écran de bienvenue ────────────────────────────────────────────────────────
show_welcome() {
    whiptail --title "Pladigit v${INSTALL_VERSION} — Installation" \
        --msgbox "\
Bienvenue dans l'assistant d'installation de Pladigit !

Pladigit est une plateforme libre de digitalisation pour les
collectivités françaises (gestion documentaire, photothèque,
projets, données, édition collaborative).

Ce script va installer et configurer tout le nécessaire
sur votre serveur Ubuntu automatiquement.

─────────────────────────────────────────
⏱  Durée estimée : 15 à 30 minutes
📋  Journal : ${LOG_FILE}
─────────────────────────────────────────

Appuyez sur Entrée pour commencer." 20 70 2>/dev/tty
}

# ── Choix du profil ───────────────────────────────────────────────────────────
choose_profil() {
    local choice
    choice=$(whiptail --title "Pladigit — Qui êtes-vous ?" \
        --menu "\
Choisissez votre situation pour adapter l'installation :" \
        20 78 3 \
        "1" "Je suis une commune ou une petite collectivité" \
        "2" "Je gère l'informatique de plusieurs communes (maison des communes...)" \
        "3" "Je suis technicien d'une communauté de communes" \
        2>/dev/tty) || die "Installation annulée."

    PROFIL="$choice"

    case "$PROFIL" in
        1)
            wt_msg "Profil — Commune" "\
✅ Parfait !

Pladigit sera installé pour votre commune uniquement.
Aucune autre organisation ne partagera votre serveur.

L'installation sera la plus simple possible.
Vous n'aurez pas besoin de connaissances techniques particulières.

👉 Deux informations vous seront demandées :
   • Votre nom de domaine (ex: pladigit.macommune.fr)
   • Une adresse email (pour le certificat de sécurité HTTPS)" 18 70
            ;;
        2)
            wt_msg "Profil — Maison des communes" "\
✅ Parfait !

Pladigit sera installé en mode multi-organisations.
Vous pourrez gérer plusieurs communes depuis un seul serveur
via l'interface Super Administrateur.

Ce mode nécessite :
   • Un nom de domaine principal (ex: pladigit.maison85.fr)
   • Un accès SSH au serveur
   • Pour chaque nouvelle commune : lancer une commande SSL
     (affichée automatiquement dans l'interface)" 18 70
            ;;
        3)
            wt_msg "Profil — Communauté de communes" "\
✅ Parfait !

Pladigit sera installé en mode multi-organisations.
Vous pourrez administrer les communes membres depuis un seul serveur.

Ce mode suppose que vous savez :
   • Installer Ubuntu Server
   • Utiliser un terminal SSH
   • Configurer un nom de domaine (enregistrement DNS)

Une commande SSL sera à lancer pour chaque nouvelle commune
(affichée automatiquement dans l'interface)." 18 70
            ;;
    esac

    log "Profil sélectionné : ${PROFIL}"
}

# ── Saisie domaine ────────────────────────────────────────────────────────────
ask_domain() {
    local msg_domaine
    case "$PROFIL" in
        1) msg_domaine="Entrez le nom de domaine de votre commune.\n\nExemple : pladigit.macommune.fr\n\n⚠ Ce domaine doit déjà pointer vers ce serveur\n  (configuré chez votre hébergeur ou registrar)." ;;
        2) msg_domaine="Entrez le nom de domaine principal de votre structure.\n\nExemple : pladigit.maison85.fr\n\nLes communes seront accessibles sur des sous-domaines :\n  mairie-soullans.pladigit.maison85.fr\n  mairie-olonne.pladigit.maison85.fr" ;;
        3) msg_domaine="Entrez le nom de domaine de votre communauté de communes.\n\nExemple : numerique.cc-example.fr\n\nChaque commune membre sera un sous-domaine :\n  mairie-a.numerique.cc-example.fr" ;;
    esac

    while [[ -z "$DOMAIN" ]]; do
        DOMAIN=$(whiptail --title "Pladigit — Nom de domaine" \
            --inputbox "${msg_domaine}" 18 70 "" 2>/dev/tty) || die "Installation annulée."
        DOMAIN="${DOMAIN// /}"
        if [[ -z "$DOMAIN" ]]; then
            wt_msg "Champ obligatoire" "⚠ Le nom de domaine est obligatoire.\n\nSans domaine, le certificat HTTPS ne peut pas être obtenu\net votre installation ne sera pas sécurisée." 10 60
        fi
    done

    log "Domaine : ${DOMAIN}"
}

# ── Saisie email ──────────────────────────────────────────────────────────────
ask_email() {
    local default_email="contact@${DOMAIN}"

    SSL_EMAIL=$(whiptail --title "Pladigit — Email Let's Encrypt" \
        --inputbox "\
Entrez une adresse email pour le certificat HTTPS (Let's Encrypt).

Cet email recevra des alertes si votre certificat approche
de sa date d'expiration (renouvellement automatique prévu).

Laissez vide pour utiliser : ${default_email}" \
        14 70 "" 2>/dev/tty) || die "Installation annulée."

    [[ -z "$SSL_EMAIL" ]] && SSL_EMAIL="$default_email"
    log "Email SSL : ${SSL_EMAIL}"
}

# ── Saisie IP Super Admin ─────────────────────────────────────────────────────
ask_admin_ip() {
    local server_ip
    server_ip=$(curl -4 -sf --max-time 5 https://ifconfig.me 2>/dev/null \
        || hostname -I | awk '{print $1}')

    local msg_ip
    case "$PROFIL" in
        1) msg_ip="Pour protéger l'accès à l'administration de Pladigit,\nentrez l'adresse IP de votre ordinateur.\n\n⚠ Attention : l'IP affichée ci-dessous est celle du SERVEUR,\n  pas la vôtre !\n\nPour connaître votre IP, visitez : https://www.whatismyip.com\n\nIP de ce serveur (pour référence) : ${server_ip}" ;;
        2) msg_ip="Entrez la ou les adresses IP autorisées à accéder\nau Super Admin (interface de gestion des communes).\n\nVous pouvez saisir plusieurs IP séparées par des virgules :\n  88.123.45.67,192.168.1.10\n\nIP de ce serveur (pour référence) : ${server_ip}" ;;
        3) msg_ip="Entrez l'adresse IP du ou des techniciens autorisés\nà accéder au Super Admin.\n\nSéparez plusieurs IP par des virgules :\n  88.123.45.67,88.123.45.68\n\nIP de ce serveur (pour référence) : ${server_ip}" ;;
    esac

    while [[ -z "$ADMIN_IPS" ]]; do
        ADMIN_IPS=$(whiptail --title "Pladigit — IP Super Admin" \
            --inputbox "${msg_ip}" 18 70 "" 2>/dev/tty) || die "Installation annulée."
        ADMIN_IPS="${ADMIN_IPS// /}"
        if [[ -z "$ADMIN_IPS" ]]; then
            wt_msg "Champ obligatoire" "⚠ L'adresse IP est obligatoire.\n\nSans restriction d'IP, n'importe qui pourrait tenter\nd'accéder à l'administration de votre plateforme." 10 60
        fi
    done

    log "IP Super Admin : ${ADMIN_IPS}"
}

# ── Récap avant installation ──────────────────────────────────────────────────
show_recap() {
    local profil_label
    case "$PROFIL" in
        1) profil_label="Commune / petite collectivité" ;;
        2) profil_label="Maison des communes / structure départementale" ;;
        3) profil_label="Communauté de communes" ;;
    esac

    whiptail --title "Pladigit — Récapitulatif" \
        --yesno "\
Voici ce qui va être installé :

  Profil      : ${profil_label}
  Domaine     : ${DOMAIN}
  Email HTTPS : ${SSL_EMAIL}
  IP Admin    : ${ADMIN_IPS}

─────────────────────────────────────────
Ce qui sera installé sur ce serveur :
  • PHP ${PHP_VERSION}, MySQL 8, Redis, Nginx, Supervisor
  • Node.js 20, Certbot (HTTPS automatique)
  • Pladigit et toutes ses dépendances
─────────────────────────────────────────

⏱  Durée estimée : 15 à 30 minutes

Lancer l'installation ?" \
        24 70 2>/dev/tty || die "Installation annulée par l'utilisateur."
}

# ── Écrire config.json pour le wizard ────────────────────────────────────────
write_wizard_config() {
    mkdir -p "${PLADIGIT_DIR}/install"
    cat > "${PLADIGIT_DIR}/install/config.json" << CONFIG
{
    "install": {
        "domain": "${DOMAIN}",
        "email": "${SSL_EMAIL}",
        "profil": "${PROFIL}",
        "version": "${INSTALL_VERSION}",
        "installed_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    }
}
CONFIG
    chown www-data:www-data "${PLADIGIT_DIR}/install/config.json" 2>/dev/null || true
    log "config.json wizard écrit"
}

# ── 0. Vérifications système ──────────────────────────────────────────────────
check_prerequisites() {
    wt_info "Pladigit — Vérification" "🔍 Analyse de votre serveur en cours..." 6 60

    [[ $EUID -ne 0 ]] && die "Ce script doit être exécuté en tant que root (sudo)."
    log "Droits root : OK"

    if [[ ! -f /etc/os-release ]]; then
        die "Système d'exploitation non reconnu."
    fi
    source /etc/os-release
    [[ "$ID" != "ubuntu" ]] && die "Pladigit nécessite Ubuntu 22.04 ou 24.04. Système détecté : $ID $VERSION_ID"
    [[ "$VERSION_ID" != "22.04" && "$VERSION_ID" != "24.04" ]] && die "Version Ubuntu non supportée : $VERSION_ID"
    log "Système : Ubuntu $VERSION_ID — OK"

    local ram_mb
    ram_mb=$(awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo)
    (( ram_mb < MIN_RAM_MB )) && die "RAM insuffisante : ${ram_mb} Mo détectés, minimum requis : ${MIN_RAM_MB} Mo."
    log "RAM : ${ram_mb} Mo — OK"

    # Extension LVM automatique
    if command -v lvextend &>/dev/null; then
        local lv_path
        lv_path=$(lvdisplay 2>/dev/null | awk '/LV Path/{print $3}' | head -1)
        if [[ -n "$lv_path" ]]; then
            local free_pe
            free_pe=$(vgdisplay 2>/dev/null | awk '/Free.*PE/{print $5}' | head -1)
            if [[ -n "$free_pe" && "$free_pe" -gt 0 ]]; then
                lvextend -l +100%FREE "$lv_path" >> "$LOG_FILE" 2>&1 || true
                resize2fs "$lv_path" >> "$LOG_FILE" 2>&1 || true
                log "Volume LVM étendu automatiquement"
            fi
        fi
    fi

    local disk_gb
    disk_gb=$(df / | awk 'NR==2 {printf "%d", $4/1024/1024}')
    (( disk_gb < MIN_DISK_GB )) && die "Espace disque insuffisant : ${disk_gb} Go disponibles, minimum requis : ${MIN_DISK_GB} Go."
    log "Disque : ${disk_gb} Go disponibles — OK"

    if ! curl -sf --max-time 5 https://github.com > /dev/null 2>&1; then
        die "Pas de connexion internet. Vérifiez votre réseau."
    fi
    log "Connexion internet — OK"

    # Inventaire composants
    [[ "$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)" != "${PHP_VERSION}" ]] && NEED_PHP=true && NEED_SYSTEM_UPDATE=true
    command -v composer &>/dev/null || NEED_PHP=true

    if command -v mysql &>/dev/null && systemctl is-active --quiet mysql 2>/dev/null; then
        if ! mysql -u root --connect-timeout=3 -e "SELECT 1;" >> "$LOG_FILE" 2>&1; then
            MYSQL_ROOT_PASSWORD=$(whiptail --title "MySQL — Mot de passe root" \
                --passwordbox "MySQL est déjà installé.\n\nEntrez le mot de passe root MySQL :" \
                10 60 2>/dev/tty) || die "Installation annulée."
            mysql -u root --connect-timeout=3 -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT 1;" >> "$LOG_FILE" 2>&1 \
                || die "Mot de passe MySQL root incorrect."
        fi
    else
        NEED_MYSQL=true
        NEED_SYSTEM_UPDATE=true
    fi

    command -v redis-server &>/dev/null && systemctl is-active --quiet redis-server 2>/dev/null || { NEED_REDIS=true; NEED_SYSTEM_UPDATE=true; }
    command -v nginx &>/dev/null && systemctl is-active --quiet nginx 2>/dev/null || { NEED_NGINX=true; NEED_SYSTEM_UPDATE=true; }
    command -v supervisorctl &>/dev/null && systemctl is-active --quiet supervisor 2>/dev/null || { NEED_SUPERVISOR=true; NEED_SYSTEM_UPDATE=true; }
    command -v node &>/dev/null || { NEED_NODE=true; NEED_SYSTEM_UPDATE=true; }

    if [[ "$NEED_PHP" == false && "$NEED_MYSQL" == false && \
          "$NEED_REDIS" == false && "$NEED_NGINX" == false && \
          "$NEED_SUPERVISOR" == false && "$NEED_NODE" == false ]]; then
        ALL_INSTALLED=true
    fi

    for port in 80 443; do
        ss -tlnp | grep -q ":${port} " && warn "Port ${port} déjà utilisé." || true
    done
}

# ── 1. Mise à jour système ────────────────────────────────────────────────────
update_system() {
    [[ "$NEED_SYSTEM_UPDATE" == false ]] && { log "Mise à jour système : non nécessaire"; return; }

    wt_info "Installation (1/7)" "📦 Mise à jour du système en cours...\n\nCela peut prendre quelques minutes." 8 60

    local waited=0
    while fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1; do
        sleep 5; waited=$((waited + 5))
        if [ "$waited" -gt 300 ]; then
            rm -f /var/lib/dpkg/lock-frontend /var/lib/dpkg/lock /var/cache/apt/archives/lock
            dpkg --configure -a >> "$LOG_FILE" 2>&1 || true
            break
        fi
    done

    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq >> "$LOG_FILE" 2>&1 || die "Impossible de mettre à jour les sources apt."
    apt-get upgrade -y -qq >> "$LOG_FILE" 2>&1 || warn "Mise à jour partielle — on continue."
    apt-get install -y -qq \
        curl wget git unzip zip \
        software-properties-common \
        apt-transport-https ca-certificates \
        gnupg lsb-release dnsutils \
        >> "$LOG_FILE" 2>&1 || die "Impossible d'installer les outils de base."

    log "Système mis à jour"
}

# ── 2. PHP 8.4 ────────────────────────────────────────────────────────────────
install_php() {
    wt_info "Installation (2/7)" "🐘 Installation de PHP ${PHP_VERSION}...\n\nCela peut prendre 3 à 5 minutes." 8 60

    if command -v "php${PHP_VERSION}" &>/dev/null || \
       php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null | grep -q "^${PHP_VERSION}"; then
        log "PHP ${PHP_VERSION} déjà installé"
    else
        curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg \
            >> "$LOG_FILE" 2>&1 || die "Impossible de télécharger la clé GPG sury.org."
        echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -cs) main" \
            > /etc/apt/sources.list.d/php.list
        apt-get update -qq >> "$LOG_FILE" 2>&1

        local php_packages=(
            "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql"
            "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-mbstring"
            "php${PHP_VERSION}-zip" "php${PHP_VERSION}-gd" "php${PHP_VERSION}-intl"
            "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-opcache" "php${PHP_VERSION}-ldap"
        )
        apt-get install -y -qq "${php_packages[@]}" >> "$LOG_FILE" 2>&1 \
            || die "Impossible d'installer PHP ${PHP_VERSION}."

        # ── Extension redis : apt d'abord, PECL en fallback ───────────────────
        if ! php -m 2>/dev/null | grep -qi redis; then
            if apt-get install -y -qq "php${PHP_VERSION}-redis" >> "$LOG_FILE" 2>&1; then
                log "Extension redis installée (apt)"
            else
                wt_info "Installation (2/7)" "⏳ Compilation de l'extension redis...\n\nCette étape peut durer 5 à 10 minutes.\nNe fermez pas ce terminal." 8 60
                apt-get install -y -qq php-pear "php${PHP_VERSION}-dev" >> "$LOG_FILE" 2>&1 || true
                if pecl install redis >> "$LOG_FILE" 2>&1; then
                    echo "extension=redis.so" > "/etc/php/${PHP_VERSION}/mods-available/redis.ini"
                    phpenmod -v "${PHP_VERSION}" redis
                    log "Extension redis installée (PECL)"
                else
                    warn "Extension redis non installée — à configurer manuellement."
                fi
            fi
        fi

        # ── Extension imagick : apt d'abord, PECL en fallback ────────────────
        if ! php -m 2>/dev/null | grep -qi imagick; then
            if apt-get install -y -qq "php${PHP_VERSION}-imagick" >> "$LOG_FILE" 2>&1 \
            || apt-get install -y -qq php-imagick >> "$LOG_FILE" 2>&1; then
                log "Extension imagick installée (apt)"
            else
                wt_info "Installation (2/7)" "⏳ Compilation de l'extension imagick...\n\nCette étape peut durer 5 à 10 minutes.\nNe fermez pas ce terminal." 8 60
                apt-get install -y -qq php-pear "php${PHP_VERSION}-dev" libmagickwand-dev >> "$LOG_FILE" 2>&1 || true
                if pecl install imagick >> "$LOG_FILE" 2>&1; then
                    echo "extension=imagick.so" > "/etc/php/${PHP_VERSION}/mods-available/imagick.ini"
                    phpenmod -v "${PHP_VERSION}" imagick
                    log "Extension imagick installée (PECL)"
                else
                    warn "Extension imagick non installée — à configurer manuellement."
                fi
            fi
        fi

        log "PHP ${PHP_VERSION} installé"
    fi

    # Composer
    if command -v composer &>/dev/null; then
        log "Composer déjà installé"
    else
        wt_info "Installation (2/7)" "📦 Installation de Composer..." 6 60
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
            >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Composer."
        log "Composer installé"
    fi
}

# ── 3. MySQL 8 ────────────────────────────────────────────────────────────────
install_mysql() {
    wt_info "Installation (3/7)" "🗄  Installation de MySQL 8..." 6 60

    if command -v mysql &>/dev/null; then
        log "MySQL déjà installé"
    else
        apt-get install -y -qq mysql-server >> "$LOG_FILE" 2>&1 \
            || die "Impossible d'installer MySQL."
        systemctl enable mysql >> "$LOG_FILE" 2>&1
        systemctl start mysql >> "$LOG_FILE" 2>&1
        log "MySQL installé"
    fi

    systemctl is-active --quiet mysql || systemctl start mysql >> "$LOG_FILE" 2>&1 \
        || die "Impossible de démarrer MySQL."

    local mysql_cmd="mysql -u root"
    [[ -n "${MYSQL_ROOT_PASSWORD}" ]] && mysql_cmd="mysql -u root -p${MYSQL_ROOT_PASSWORD}"

    if [[ -z "${MYSQL_ROOT_PASSWORD}" ]]; then
        ${mysql_cmd} -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY ''; FLUSH PRIVILEGES;" \
            >> "$LOG_FILE" 2>&1 || warn "ALTER USER root ignoré — déjà configuré."
    fi

    log "Authentification MySQL configurée"
}

# ── 4. Services (Redis, Nginx, Supervisor, Node.js, Certbot) ──────────────────
install_services() {
    wt_info "Installation (4/7)" "⚙️  Installation des services...\n\nRedis, Nginx, Supervisor, Node.js, Certbot" 8 60

    # Redis
    command -v redis-server &>/dev/null || {
        apt-get install -y -qq redis-server >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Redis."
        log "Redis installé"
    }
    systemctl enable redis-server >> "$LOG_FILE" 2>&1
    systemctl is-active --quiet redis-server || systemctl start redis-server >> "$LOG_FILE" 2>&1

    # Nginx
    command -v nginx &>/dev/null || {
        apt-get install -y -qq nginx >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Nginx."
        log "Nginx installé"
    }
    systemctl enable nginx >> "$LOG_FILE" 2>&1

    # Supervisor
    command -v supervisorctl &>/dev/null || {
        apt-get install -y -qq supervisor >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Supervisor."
        log "Supervisor installé"
    }
    systemctl enable supervisor >> "$LOG_FILE" 2>&1
    systemctl is-active --quiet supervisor || systemctl start supervisor >> "$LOG_FILE" 2>&1 || true

    cat > /etc/supervisor/conf.d/pladigit.conf << SUPERVISOR
[program:pladigit-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${PLADIGIT_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=${PLADIGIT_DIR}/storage/logs/worker.log
stopwaitsecs=3600
SUPERVISOR
    log "Supervisor configuré"

    # Node.js 20
    command -v node &>/dev/null || {
        curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >> "$LOG_FILE" 2>&1
        apt-get install -y -qq nodejs >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Node.js."
        log "Node.js installé"
    }

    # UFW
    if command -v ufw &>/dev/null; then
        ufw allow 22/tcp  >> "$LOG_FILE" 2>&1 || true
        ufw allow 80/tcp  >> "$LOG_FILE" 2>&1 || true
        ufw allow 443/tcp >> "$LOG_FILE" 2>&1 || true
        ufw --force enable >> "$LOG_FILE" 2>&1 || true
        log "Pare-feu UFW configuré (22, 80, 443)"
    fi

    # Certbot
    command -v certbot &>/dev/null || {
        apt-get install -y -qq certbot python3-certbot-nginx >> "$LOG_FILE" 2>&1 \
            || warn "Impossible d'installer Certbot — SSL à configurer manuellement."
        log "Certbot installé"
    }

    # Sudoers pour www-data (bandeau SSL Super Admin)
    local CERTBOT_SUDOERS="/etc/sudoers.d/pladigit-certbot"
    local CERTBOT_PATH
    CERTBOT_PATH=$(command -v certbot || echo "/usr/bin/certbot")
    cat > "$CERTBOT_SUDOERS" << SUDOERS_EOF
www-data ALL=(root) NOPASSWD: ${CERTBOT_PATH} *
www-data ALL=(root) NOPASSWD: /bin/cp *
www-data ALL=(root) NOPASSWD: /bin/ln *
www-data ALL=(root) NOPASSWD: /usr/sbin/nginx *
www-data ALL=(root) NOPASSWD: /bin/systemctl reload nginx
www-data ALL=(root) NOPASSWD: /bin/chmod 755 /etc/letsencrypt/live/*
SUDOERS_EOF
    chmod 440 "$CERTBOT_SUDOERS"
    visudo -c -f "$CERTBOT_SUDOERS" >> "$LOG_FILE" 2>&1 \
        && log "Règle sudoers SSL configurée" \
        || { warn "Règle sudoers invalide — suppression."; rm -f "$CERTBOT_SUDOERS"; }

    log "Services installés"
}

# ── 5. Logrotate + MySQL logs ─────────────────────────────────────────────────
setup_logs() {
    cat > /etc/logrotate.d/nginx-pladigit << 'LOGROTATE'
/var/log/nginx/*.log {
    daily
    rotate 90
    compress
    delaycompress
    missingok
    notifempty
    sharedscripts
    postrotate
        [ -f /var/run/nginx.pid ] && kill -USR1 $(cat /var/run/nginx.pid) || true
    endscript
}
LOGROTATE
    log "Logrotate Nginx configuré (90 jours)"

    local mycnf="/etc/mysql/mysql.conf.d/mysqld.cnf"
    if ! grep -q "slow_query_log" "$mycnf" 2>/dev/null; then
        cat >> "$mycnf" << 'MYCNF'

# Pladigit — logs MySQL
slow_query_log       = 1
slow_query_log_file  = /var/log/mysql/mysql-slow.log
long_query_time      = 2
log_error            = /var/log/mysql/error.log
MYCNF
        systemctl restart mysql >> "$LOG_FILE" 2>&1 || warn "Redémarrage MySQL ignoré."
        log "MySQL slow query log activé"
    fi
}

# ── 6. Clonage et dépendances Pladigit ───────────────────────────────────────
install_pladigit() {
    wt_info "Installation (5/7)" "📥 Téléchargement de Pladigit...\n\nClonage du code source depuis GitHub." 8 60

    git config --global --add safe.directory "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 || true

    if [[ -d "$PLADIGIT_DIR/.git" ]]; then
        git -C "$PLADIGIT_DIR" pull origin main >> "$LOG_FILE" 2>&1 || warn "git pull échoué — on continue."
    else
        git clone "$PLADIGIT_REPO" "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
            || die "Impossible de cloner le dépôt. Vérifiez votre connexion."
    fi
    log "Code source Pladigit cloné"

    chown -R www-data:www-data "$PLADIGIT_DIR"
    chmod -R 755 "$PLADIGIT_DIR"
    chmod -R 775 "$PLADIGIT_DIR/storage" "$PLADIGIT_DIR/bootstrap/cache"
    mkdir -p "${PLADIGIT_DIR}/storage/app/private/backup"
    chown www-data:www-data "${PLADIGIT_DIR}/storage/app/private/backup"
    chmod 750 "${PLADIGIT_DIR}/storage/app/private/backup"
    log "Permissions configurées"

    wt_info "Installation (6/7)" "📦 Installation des dépendances PHP...\n\nCela peut prendre 2 à 3 minutes." 8 60
    sudo -u www-data composer install \
        --no-dev --optimize-autoloader --no-interaction \
        --working-dir="$PLADIGIT_DIR" \
        >> "$LOG_FILE" 2>&1 || die "Composer install échoué."
    log "Dépendances PHP installées"

    wt_info "Installation (6/7)" "🔨 Compilation des assets (JS/CSS)...\n\nCela peut prendre 1 à 2 minutes." 8 60
    mkdir -p /var/www/.npm
    chown -R www-data:www-data /var/www/.npm
    sudo -u www-data npm ci --prefix "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
        || die "npm install échoué."
    sudo -u www-data npm run build --prefix "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
        || die "npm build échoué."
    log "Assets compilés"

    # Écrire config.json pour le wizard
    write_wizard_config
    chown -R www-data:www-data "${PLADIGIT_DIR}/install"

    # Script Collabora
    curl -fsSL https://pladigit.fr/get-collabora-installer \
        -o "${PLADIGIT_DIR}/install/install-collabora.sh" \
        >> "$LOG_FILE" 2>&1 \
        || warn "Script Collabora non disponible — copie locale utilisée."
    chmod +x "${PLADIGIT_DIR}/install/install-collabora.sh"
    chown root:root "${PLADIGIT_DIR}/install/install-collabora.sh"

    local SUDOERS_COLLAB="/etc/sudoers.d/pladigit-collabora"
    echo "www-data ALL=(root) NOPASSWD: ${PLADIGIT_DIR}/install/install-collabora.sh" > "$SUDOERS_COLLAB"
    chmod 440 "$SUDOERS_COLLAB"
    visudo -c -f "$SUDOERS_COLLAB" >> "$LOG_FILE" 2>&1 \
        && log "Règle sudoers Collabora configurée" \
        || { warn "Règle sudoers Collabora invalide — suppression."; rm -f "$SUDOERS_COLLAB"; }

    supervisorctl reread >> "$LOG_FILE" 2>&1 || true
    supervisorctl update >> "$LOG_FILE" 2>&1 || true
    log "Workers Supervisor activés"
}

# ── 7. Nginx ──────────────────────────────────────────────────────────────────
configure_nginx() {
    wt_info "Installation (7/7)" "🌐 Configuration de Nginx..." 6 60

    local nginx_server_name="_"
    [[ -n "${DOMAIN}" ]] && nginx_server_name="${DOMAIN} *.${DOMAIN}"

    cat > /etc/nginx/sites-available/pladigit << NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${nginx_server_name};

    root ${PLADIGIT_DIR}/public;
    index index.php index.html;

    client_max_body_size 100M;
    server_tokens off;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'self';" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* { deny all; }

    location ~ ^/(\.env|\.git|composer\.(json|lock)) { deny all; }

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

    ln -sf /etc/nginx/sites-available/pladigit /etc/nginx/sites-enabled/pladigit
    rm -f /etc/nginx/sites-enabled/default
    nginx -t >> "$LOG_FILE" 2>&1 || die "Configuration Nginx invalide."
    systemctl restart nginx >> "$LOG_FILE" 2>&1 || die "Impossible de redémarrer Nginx."
    systemctl restart "php${PHP_VERSION}-fpm" >> "$LOG_FILE" 2>&1
    log "Nginx configuré"
}

# ── SSL ───────────────────────────────────────────────────────────────────────
setup_ssl() {
    local env_file="${PLADIGIT_DIR}/.env"

    [[ -z "$DOMAIN" ]] && { warn "Aucun domaine — SSL ignoré."; return; }

    # Cert déjà présent — vérifier que le bloc 443 existe
    if [[ -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]]; then
        log "Certificat SSL déjà présent pour ${DOMAIN}"
        if grep -q "listen 443" /etc/nginx/sites-available/pladigit 2>/dev/null; then
            log "Bloc HTTPS Nginx déjà présent"
        else
            wt_info "SSL" "🔒 Injection du bloc HTTPS Nginx..." 6 60
            local nginx_server_name="${DOMAIN} *.${DOMAIN}"
            cat > /etc/nginx/sites-available/pladigit << NGINX_SSL
server {
    listen 80;
    listen [::]:80;
    server_name ${nginx_server_name};
    return 301 https://\$host\$request_uri;
}
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name ${nginx_server_name};
    ssl_certificate     /etc/letsencrypt/live/${DOMAIN}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${DOMAIN}/privkey.pem;
    include             /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam         /etc/letsencrypt/ssl-dhparams.pem;
    root ${PLADIGIT_DIR}/public;
    index index.php index.html;
    client_max_body_size 100M;
    server_tokens off;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'self';" always;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    location ~ /\.(?!well-known).* { deny all; }
    location ~ ^/(\.env|\.git|composer\.(json|lock)) { deny all; }
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
NGINX_SSL
            nginx -t >> "$LOG_FILE" 2>&1 && systemctl reload nginx >> "$LOG_FILE" 2>&1 \
                && log "Bloc HTTPS Nginx injecté"
        fi

        # Permissions letsencrypt
        chmod 755 /etc/letsencrypt/                             2>/dev/null || true
        chmod 755 /etc/letsencrypt/live/                        2>/dev/null || true
        chmod 755 "/etc/letsencrypt/live/${DOMAIN}/"            2>/dev/null || true
        chmod 755 /etc/letsencrypt/archive/                     2>/dev/null || true
        chmod 755 "/etc/letsencrypt/archive/${DOMAIN}/"         2>/dev/null || true

        # Mettre à jour .env
        if [[ -f "$env_file" ]]; then
            sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" "$env_file"
            grep -q "^SESSION_DOMAIN=" "$env_file" \
                && sed -i "s|^SESSION_DOMAIN=.*|SESSION_DOMAIN=.${DOMAIN}|" "$env_file" \
                || echo "SESSION_DOMAIN=.${DOMAIN}" >> "$env_file"
            grep -q "^SESSION_SECURE_COOKIE=" "$env_file" \
                && sed -i "s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|" "$env_file" \
                || echo "SESSION_SECURE_COOKIE=true" >> "$env_file"
        fi
        return
    fi

    wt_info "SSL" "🔒 Obtention du certificat HTTPS (Let's Encrypt)...\n\nCela prend généralement moins d'une minute." 8 60

    # Vérification DNS
    local server_ip dns_ip
    server_ip=$(curl -4 -sf --max-time 5 https://ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')
    dns_ip=$(dig +short "${DOMAIN}" A 2>/dev/null | tail -1)
    if [[ -n "$dns_ip" && "$dns_ip" != "$server_ip" ]]; then
        warn "DNS : ${DOMAIN} pointe vers ${dns_ip} mais ce serveur est ${server_ip}."
        warn "SSL ignoré — mettez à jour votre DNS puis relancez : certbot --nginx -d ${DOMAIN}"
        return
    fi

    if certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos \
        --email "${SSL_EMAIL}" --redirect >> "$LOG_FILE" 2>&1; then

        log "Certificat SSL obtenu pour ${DOMAIN}"

        chmod 755 /etc/letsencrypt/                             2>/dev/null || true
        chmod 755 /etc/letsencrypt/live/                        2>/dev/null || true
        chmod 755 "/etc/letsencrypt/live/${DOMAIN}/"            2>/dev/null || true
        chmod 755 /etc/letsencrypt/archive/                     2>/dev/null || true
        chmod 755 "/etc/letsencrypt/archive/${DOMAIN}/"         2>/dev/null || true

        if [[ -f "$env_file" ]]; then
            sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" "$env_file"
            grep -q "^SESSION_DOMAIN=" "$env_file" \
                && sed -i "s|^SESSION_DOMAIN=.*|SESSION_DOMAIN=.${DOMAIN}|" "$env_file" \
                || echo "SESSION_DOMAIN=.${DOMAIN}" >> "$env_file"
            grep -q "^SESSION_SECURE_COOKIE=" "$env_file" \
                && sed -i "s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|" "$env_file" \
                || echo "SESSION_SECURE_COOKIE=true" >> "$env_file"
            log "APP_URL et SESSION mis à jour"
        fi

        if ! crontab -l 2>/dev/null | grep -q "certbot renew"; then
            (crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet --post-hook 'systemctl reload nginx'") | crontab -
            log "Renouvellement SSL automatique configuré"
        fi
    else
        warn "Échec SSL — tenant actif en HTTP. Relancez : certbot --nginx -d ${DOMAIN}"
    fi
}

# ── Cron Laravel ──────────────────────────────────────────────────────────────
setup_cron() {
    local CRON_ENTRY="* * * * * cd ${PLADIGIT_DIR} && php artisan schedule:run >> /dev/null 2>&1"
    if crontab -u www-data -l 2>/dev/null | grep -qF "schedule:run"; then
        log "Cron Laravel : déjà configuré"
    else
        (crontab -u www-data -l 2>/dev/null || true; echo "$CRON_ENTRY") | crontab -u www-data -
        log "Cron Laravel configuré"
    fi
}

# ── IP Super Admin dans .env ──────────────────────────────────────────────────
setup_super_admin_ip() {
    local env_file="${PLADIGIT_DIR}/.env"

    if [[ ! -f "$env_file" ]]; then
        [[ -f "${PLADIGIT_DIR}/.env.example" ]] \
            && cp "${PLADIGIT_DIR}/.env.example" "$env_file" \
            || touch "$env_file"
        chown www-data:www-data "$env_file"
        chmod 640 "$env_file"
    fi

    if grep -q "^SUPER_ADMIN_ALLOWED_IPS=" "$env_file" 2>/dev/null; then
        sed -i "s|^SUPER_ADMIN_ALLOWED_IPS=.*|SUPER_ADMIN_ALLOWED_IPS=${ADMIN_IPS}|" "$env_file"
    else
        echo "SUPER_ADMIN_ALLOWED_IPS=${ADMIN_IPS}" >> "$env_file"
    fi

    log "SUPER_ADMIN_ALLOWED_IPS configuré : ${ADMIN_IPS}"
}

# ── Écran de succès ───────────────────────────────────────────────────────────
show_success() {
    local install_url
    if ls /etc/letsencrypt/live/*/fullchain.pem > /dev/null 2>&1; then
        local ssl_domain
        ssl_domain=$(ls /etc/letsencrypt/live/ | grep -v README | head -1)
        install_url="https://${ssl_domain}/install/"
    else
        install_url="http://$(hostname -I | awk '{print $1}')/install/"
    fi

    local msg_success
    case "$PROFIL" in
        1) msg_success="🎉 Votre plateforme Pladigit est prête !\n\nÉtape suivante — ouvrez votre navigateur et accédez à :\n\n  ${install_url}\n\nL'assistant de configuration vous guidera pour :\n  • Configurer la base de données\n  • Créer votre compte administrateur\n  • Configurer l'envoi d'emails (optionnel)\n\n💡 Durée : environ 5 minutes." ;;
        2) msg_success="🎉 Pladigit est installé en mode multi-organisations !\n\nÉtape suivante — ouvrez votre navigateur :\n\n  ${install_url}\n\nUne fois le wizard terminé, connectez-vous au Super Admin\npour créer les organisations (communes).\n\nPour chaque nouvelle commune, un bandeau SSL\nvous indiquera la commande certbot à lancer." ;;
        3) msg_success="🎉 Pladigit est installé !\n\nÉtape suivante :\n\n  ${install_url}\n\nConnectez-vous au Super Admin pour créer\nles communes membres.\n\nPour le SSL de chaque commune : la commande\ncertbot sera affichée automatiquement." ;;
    esac

    whiptail --title "✅ Installation terminée !" \
        --msgbox "${msg_success}" 22 70 2>/dev/tty

    log "Installation terminée — ${install_url}"

    # Ouvrir le navigateur si interface graphique disponible
    if [ -n "${DISPLAY:-}" ] || [ -n "${WAYLAND_DISPLAY:-}" ]; then
        xdg-open "${install_url}" 2>/dev/null &
    fi
}

# ── Mise à jour ───────────────────────────────────────────────────────────────
do_update() {
    whiptail --title "Pladigit — Mise à jour" \
        --yesno "Une installation Pladigit a été détectée sur ce serveur.\n\nSouhaitez-vous la mettre à jour ?\n\n• Le code sera mis à jour (git pull)\n• Les dépendances seront mises à jour\n• Les migrations seront appliquées\n• Vos données ne seront PAS supprimées" \
        16 70 2>/dev/tty || { log "Mise à jour annulée."; exit 0; }

    wt_info "Mise à jour" "⬆️  Mise à jour en cours..." 6 60
    cd "$PLADIGIT_DIR" || die "Impossible d'accéder à ${PLADIGIT_DIR}"

    git pull origin main >> "$LOG_FILE" 2>&1 || warn "git pull échoué"
    sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction \
        --working-dir="$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 || warn "composer install échoué"
    sudo -u www-data npm ci --prefix "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
        && sudo -u www-data npm run build --prefix "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
        || warn "npm build échoué"
    sudo -u www-data php "$PLADIGIT_DIR/artisan" migrate --force >> "$LOG_FILE" 2>&1 || warn "migrate échoué"
    sudo -u www-data php "$PLADIGIT_DIR/artisan" migrate \
        --path=database/migrations/platform --force >> "$LOG_FILE" 2>&1 || warn "migrate platform échoué"
    sudo -u www-data php "$PLADIGIT_DIR/artisan" config:cache >> "$LOG_FILE" 2>&1
    sudo -u www-data php "$PLADIGIT_DIR/artisan" route:cache  >> "$LOG_FILE" 2>&1
    sudo -u www-data php "$PLADIGIT_DIR/artisan" view:cache   >> "$LOG_FILE" 2>&1
    supervisorctl restart pladigit-worker:* >> "$LOG_FILE" 2>&1 || true
    setup_cron

    wt_msg "✅ Mise à jour terminée" "Pladigit a été mis à jour avec succès.\n\nAucune reconfiguration nécessaire." 10 60
    exit 0
}

# ── Point d'entrée ────────────────────────────────────────────────────────────
main() {
    # Forcer stdin sur le terminal — indispensable via curl | bash
    exec < /dev/tty

    mkdir -p "$(dirname "$LOG_FILE")"
    echo "=== Pladigit Install Log v${INSTALL_VERSION} — $(date) ===" > "$LOG_FILE"

    # Vérifier que whiptail est disponible (préinstallé sur Ubuntu)
    if ! command -v whiptail &>/dev/null; then
        apt-get install -y -qq whiptail >> "$LOG_FILE" 2>&1 || true
    fi

    # Vérifier les droits root en amont
    [[ $EUID -ne 0 ]] && { echo "Ce script doit être exécuté en tant que root (sudo)."; exit 1; }

    show_welcome

    # Installation existante détectée ?
    if [[ -f "${PLADIGIT_DIR}/.env" ]] && [[ -f "${PLADIGIT_DIR}/install/.lock" ]]; then
        do_update
    fi

    # Saisies interactives
    choose_profil
    ask_domain
    ask_email
    ask_admin_ip
    show_recap

    # Installation
    check_prerequisites

    if [[ "$ALL_INSTALLED" == true ]] && [[ -d "${PLADIGIT_DIR}/.git" ]]; then
        install_pladigit
        configure_nginx
        set +e; setup_ssl; set -e
        setup_cron
        setup_super_admin_ip
        show_success
        return
    fi

    update_system
    install_php
    install_mysql
    install_services
    setup_logs
    install_pladigit
    configure_nginx
    set +e; setup_ssl; set -e
    setup_cron
    setup_super_admin_ip
    show_success
}

main "$@"
