#!/usr/bin/env bash
# ==============================================================================
#  Pladigit — Script d'installation automatique
#  Version : 2.0.0
#  Cible   : Ubuntu 22.04 LTS / 24.04 LTS / 26.04 LTS
#
#  Usage :
#    curl -fsSL https://raw.githubusercontent.com/jpbosse/pladigit/main/install.sh -o /tmp/install.sh
#    sudo bash /tmp/install.sh
# ==============================================================================

# ── Vérification TTY ─────────────────────────────────────────────────────────
# Ce script nécessite un terminal interactif — incompatible avec curl | bash.
if [ ! -t 0 ] || [ ! -t 1 ]; then
    echo ""
    echo "  ✗ Ce script nécessite un terminal interactif."
    echo ""
    echo "  Lancez les commandes suivantes :"
    echo "  curl -fsSL https://raw.githubusercontent.com/jpbosse/pladigit/main/install.sh -o /tmp/install.sh"
    echo "  sudo bash /tmp/install.sh"
    echo ""
    exit 1
fi
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
SSL_MODE=""

# ── Helpers log (sans Whiptail — pour le journal) ─────────────────────────────
LOG_FILE="/var/log/pladigit-install.log"
_log() { echo -e "$*" >> "$LOG_FILE"; }
log()  { _log "✓ $*"; }
warn() { _log "⚠  $*"; }
info() { _log "→  $*"; }
die()  { _log "✗  $*"; dialog --title "Erreur fatale" --msgbox "❌ $*\n\nConsultez le journal :\n$LOG_FILE" 12 60 2>/dev/tty; exit 1; }

# ── Whiptail helpers ──────────────────────────────────────────────────────────
# Toutes les saisies lisent depuis /dev/tty — fonctionne via curl | bash
# ── Couleurs dialog ──────────────────────────────────────────────────────────
export DIALOGRC
DIALOGRC=$(mktemp /tmp/pladigit-dialogrc-XXXXXX)
cat > "$DIALOGRC" << 'DIALOGRC_EOF'
use_colors = ON
screen_color = (WHITE,BLUE,ON)
dialog_color = (BLACK,WHITE,OFF)
title_color = (BLUE,WHITE,ON)
border_color = (WHITE,WHITE,ON)
button_active_color = (WHITE,BLUE,ON)
button_inactive_color = (BLACK,WHITE,OFF)
tag_color = (BLUE,WHITE,ON)
tag_selected_color = (WHITE,BLUE,ON)
check_color = (BLACK,WHITE,OFF)
check_selected_color = (WHITE,BLUE,ON)
DIALOGRC_EOF

wt_msg() {
    # wt_msg "Titre" "Message" [hauteur] [largeur]
    dialog --title "${1}" --msgbox "${2}" "${3:-14}" "${4:-72}" 2>/dev/tty
    clear
}

wt_info() {
    # Boîte non bloquante pendant les opérations longues
    dialog --title "${1}" --infobox "${2}" "${3:-8}" "${4:-72}" 2>/dev/tty
}

wt_input() {
    # wt_input "Titre" "Question" "Valeur par défaut" → stdout
    dialog --title "${1}" --inputbox "${2}" 12 72 "${3}" 3>&1 1>/dev/tty 2>&3
}

wt_yesno() {
    # wt_yesno "Titre" "Question" → 0=oui 1=non
    dialog --title "${1}" --yesno "${2}" 12 72 2>/dev/tty
    local ret=$?
    clear
    return $ret
}

# ── Fenêtre de progression avec liste d'étapes ───────────────────────────────
# Usage : start_progress
#         update_progress PCT "Étape en cours..."
#         stop_progress
PROGRESS_PIPE=""
PROGRESS_PID=""

STEPS_DONE=()
STEPS_TODO=("Mise à jour système" "PHP 8.4 + Composer" "MySQL 8" "Services (Redis, Nginx...)" "Pladigit" "Configuration Nginx" "SSL + finalisation")

_render_steps() {
    local current_msg="${1}"
    local pct="${2}"
    local text=""

    for step in "${STEPS_DONE[@]}"; do
        text+="✅ ${step}\n"
    done
    if [[ -n "$current_msg" ]]; then
        text+="⏳ ${current_msg}\n"
    fi
    local remaining=$(( ${#STEPS_TODO[@]} ))
    for (( i=0; i<remaining; i++ )); do
        text+="○  ${STEPS_TODO[$i]}\n"
    done
    echo "$text"
}

start_progress() {
    PROGRESS_PIPE=$(mktemp -u /tmp/pladigit-progress-XXXXXX)
    mkfifo "$PROGRESS_PIPE"
    dialog --title "Pladigit — Installation en cours"         --gauge "Démarrage..." 20 72 0 < "$PROGRESS_PIPE" 2>/dev/tty &
    PROGRESS_PID=$!
    exec 3>"$PROGRESS_PIPE"
}

update_progress() {
    local pct="${1}"
    local msg="${2}"
    _log "[${pct}%] ${msg}"
    # Écrire dans le gauge uniquement si le pipe est ouvert (start_progress appelé)
    if [[ -n "${PROGRESS_PIPE:-}" && -p "${PROGRESS_PIPE:-}" ]]; then
        printf "XXX\n%s\n%s\nXXX\n" "$pct" "$msg" >&3 2>/dev/null || true
    fi
}

step_done() {
    local step="${1}"
    STEPS_DONE+=("$step")
    if [[ ${#STEPS_TODO[@]} -gt 0 ]]; then
        STEPS_TODO=("${STEPS_TODO[@]:1}")
    fi
}

stop_progress() {
    exec 3>&- 2>/dev/null || true
    sleep 0.3
    kill "$PROGRESS_PID" 2>/dev/null || true
    wait "$PROGRESS_PID" 2>/dev/null || true
    rm -f "$PROGRESS_PIPE"
    clear
}

# ── Écran de bienvenue ────────────────────────────────────────────────────────
show_welcome() {
    dialog --title "Pladigit v${INSTALL_VERSION} — Installation" \
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


# ── Saisie domaine ────────────────────────────────────────────────────────────
ask_domain() {
    local msg_domaine
    case "$PROFIL" in
        1) msg_domaine="Entrez le nom de domaine de votre commune.\n\nExemple : pladigit.macommune.fr\n\n⚠ Ce domaine doit déjà pointer vers ce serveur\n  (configuré chez votre hébergeur ou registrar)." ;;
        2) msg_domaine="Entrez le nom de domaine principal de votre structure.\n\nExemple : pladigit.maison85.fr\n\nLes communes seront accessibles sur des sous-domaines :\n  mairie-soullans.pladigit.maison85.fr\n  mairie-olonne.pladigit.maison85.fr" ;;
        3) msg_domaine="Entrez le nom de domaine de votre communauté de communes.\n\nExemple : numerique.cc-example.fr\n\nChaque commune membre sera un sous-domaine :\n  mairie-a.numerique.cc-example.fr" ;;
    esac

    while [[ -z "$DOMAIN" ]]; do
        DOMAIN=$(dialog --title "Pladigit — Nom de domaine" \
            --inputbox "${msg_domaine}" 18 70 "" 3>&1 1>/dev/tty 2>&3) || die "Installation annulée."
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

    SSL_EMAIL=$(dialog --title "Pladigit — Email Let's Encrypt" \
        --inputbox "\
Entrez une adresse email pour le certificat HTTPS (Let's Encrypt).

Cet email recevra des alertes si votre certificat approche
de sa date d'expiration (renouvellement automatique prévu).

Laissez vide pour utiliser : ${default_email}" \
        14 70 "" 3>&1 1>/dev/tty 2>&3) || die "Installation annulée."

    [[ -z "$SSL_EMAIL" ]] && SSL_EMAIL="$default_email"
    log "Email SSL : ${SSL_EMAIL}"
}

# ── Saisie IP Super Admin ─────────────────────────────────────────────────────
ask_admin_ip() {
    local server_ip
    server_ip=$(curl -4 -sf --max-time 5 https://ifconfig.me 2>/dev/null \
        || hostname -I | awk '{print $1}')

    # Récupérer l'IP du poste qui a lancé la connexion SSH
    # SSH_CLIENT/SSH_CONNECTION peuvent être absents sous sudo — on cherche aussi via who/ss
    local ssh_client_ip=""
    if [[ -n "${SSH_CLIENT:-}" ]]; then
        ssh_client_ip=$(echo "$SSH_CLIENT" | awk '{print $1}')
    elif [[ -n "${SSH_CONNECTION:-}" ]]; then
        ssh_client_ip=$(echo "$SSH_CONNECTION" | awk '{print $1}')
    else
        # Fallback : dernière connexion SSH active via who ou ss
        ssh_client_ip=$(who | grep -oE '\([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+\)' | head -1 | tr -d '()')
        if [[ -z "$ssh_client_ip" ]]; then
            ssh_client_ip=$(ss -tnp 2>/dev/null | grep ':22 ' | grep ESTAB | awk '{print $5}' | cut -d: -f1 | head -1)
        fi
        if [[ -z "$ssh_client_ip" ]]; then
            ssh_client_ip=$(last -n1 -i "$USER" 2>/dev/null | awk 'NR==1{print $3}' | grep -E '^[0-9]+\.')
        fi
    fi

    if [[ "$PROFIL" == "1" ]]; then
        # Profil commune : on connaît déjà l'IP — confirmation simple
        if [[ -n "$ssh_client_ip" ]]; then
            dialog --title "Pladigit — Accès administrateur" \
                --yesno "\
Votre adresse IP a été détectée automatiquement :

  👤 IP de votre ordinateur : ${ssh_client_ip}

Seul votre ordinateur pourra accéder à l'interface
d'administration de Pladigit.

Si vous changez de réseau (ex: connexion depuis chez vous
puis depuis la mairie), il faudra mettre à jour cette IP
depuis l'interface Super Admin.

✅ Confirmer cette adresse IP ?" \
                16 70 >/dev/tty 2>&1
            local ret=$?
            clear
            if [[ $ret -eq 0 ]]; then
                ADMIN_IPS="$ssh_client_ip"
            else
                # Refus — saisie manuelle
                ADMIN_IPS=""
            fi
        fi

        # Si pas de SSH_CLIENT ou refus de confirmation → saisie manuelle
        if [[ -z "$ADMIN_IPS" ]]; then
            while [[ -z "$ADMIN_IPS" ]]; do
                ADMIN_IPS=$(dialog --title "Pladigit — IP de votre ordinateur" \
                    --inputbox "\
Entrez l'adresse IP de votre ordinateur.

👉 Pour la connaître, ouvrez dans un navigateur :
   https://www.mon-ip.com

Exemple : 88.123.45.67

IP de ce serveur (pour référence) : ${server_ip}" \
                    16 70 "${ssh_client_ip}" 3>&1 1>/dev/tty 2>&3) || die "Installation annulée."
                ADMIN_IPS="${ADMIN_IPS// /}"
                if [[ -z "$ADMIN_IPS" ]]; then
                    wt_msg "Champ obligatoire" "⚠ L'adresse IP est obligatoire.\n\nSans restriction d'IP, n'importe qui pourrait tenter\nd'accéder à l'administration de votre plateforme." 10 60
                fi
            done
        fi
    else
        # Profils 2 et 3 : saisie manuelle avec pré-remplissage SSH si disponible
        local msg_ip
        case "$PROFIL" in
            2) msg_ip="Entrez la ou les adresses IP autorisées à accéder\nau Super Admin (interface de gestion des communes).\n\n👉 Pour connaître votre IP : https://www.mon-ip.com\n\nVous pouvez saisir plusieurs IP séparées par des virgules :\n  88.123.45.67,192.168.1.10\n\nIP de ce serveur (pour référence) : ${server_ip}" ;;
            3) msg_ip="Entrez l'adresse IP du ou des techniciens autorisés\nà accéder au Super Admin.\n\n👉 Pour connaître votre IP : https://www.mon-ip.com\n\nSéparez plusieurs IP par des virgules :\n  88.123.45.67,88.123.45.68\n\nIP de ce serveur (pour référence) : ${server_ip}" ;;
        esac

        while [[ -z "$ADMIN_IPS" ]]; do
            ADMIN_IPS=$(dialog --title "Pladigit — IP Super Admin" \
                --inputbox "${msg_ip}" 18 70 "${ssh_client_ip}" 3>&1 1>/dev/tty 2>&3) || die "Installation annulée."
            ADMIN_IPS="${ADMIN_IPS// /}"
            if [[ -z "$ADMIN_IPS" ]]; then
                wt_msg "Champ obligatoire" "⚠ L'adresse IP est obligatoire.\n\nSans restriction d'IP, n'importe qui pourrait tenter\nd'accéder à l'administration de votre plateforme." 10 60
            fi
        done
    fi

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

    dialog --title "Pladigit — Récapitulatif" \
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
        24 70 >/dev/tty 2>&1 || die "Installation annulée par l'utilisateur."
}

# ── Écrire config.json pour le wizard ────────────────────────────────────────
write_wizard_config() {
    mkdir -p "${PLADIGIT_DIR}/install"

    # Section db uniquement si le provisionnement MySQL a réellement eu lieu
    # (chemin réinstallation : install_mysql est sauté, le wizard garde alors
    # sa page base de données pour une saisie manuelle).
    local db_block=""
    if [[ -n "${DB_APP_PASSWORD:-}" ]]; then
        db_block=",
    \"db\": {
        \"host\": \"127.0.0.1\",
        \"port\": \"3306\",
        \"name\": \"${DB_NAME:-pladigit}\",
        \"app_user\": \"${DB_APP_USER:-pladigit}\",
        \"app_password\": \"${DB_APP_PASSWORD}\",
        \"provisioned\": true
    }"
    fi

    cat > "${PLADIGIT_DIR}/install/config.json" << CONFIG
{
    "install": {
        "domain": "${DOMAIN}",
        "email": "${SSL_EMAIL}",
        "profil": "${PROFIL}",
        "admin_ips": "${ADMIN_IPS}",
        "ssl_mode": "${SSL_MODE:-none}",
        "version": "${INSTALL_VERSION}",
        "installed_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    }${db_block}
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
    [[ "$ID" != "ubuntu" ]] && die "Pladigit nécessite Ubuntu 22.04, 24.04 ou 26.04 LTS. Système détecté : $ID $VERSION_ID"
    [[ "$VERSION_ID" != "22.04" && "$VERSION_ID" != "24.04" && "$VERSION_ID" != "26.04" ]] && die "Version Ubuntu non supportée : $VERSION_ID (versions supportées : 22.04, 24.04, 26.04 LTS)"
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
            MYSQL_ROOT_PASSWORD=$(dialog --title "MySQL — Mot de passe root" \
                --passwordbox "MySQL est déjà installé.\n\nEntrez le mot de passe root MySQL :" \
                10 60 3>&1 1>/dev/tty 2>&3) || die "Installation annulée."
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

    update_progress 5 "Mise à jour du système... ⏳ Merci de patienter (2 à 5 min)"

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
    step_done "Mise à jour système"
    update_progress 14 "Système mis à jour ✅"
}

# ── 2. PHP 8.4 ────────────────────────────────────────────────────────────────
install_php() {
    update_progress 15 "Installation de PHP ${PHP_VERSION}... ⏳ Merci de patienter (3 à 5 min)"

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

        # ── Forcer php${PHP_VERSION} comme version PHP par défaut du système ──
        # Sur Ubuntu 26.04, PHP 8.5 est natif et reste le « php » par défaut.
        # Sans cela, composer/artisan et les vérifications ci-dessous
        # s'exécuteraient sous 8.5 (mauvaises extensions, incompatibilités).
        if [[ -x "/usr/bin/php${PHP_VERSION}" ]]; then
            update-alternatives --set php "/usr/bin/php${PHP_VERSION}" >> "$LOG_FILE" 2>&1 \
                || update-alternatives --install /usr/bin/php php "/usr/bin/php${PHP_VERSION}" 90 >> "$LOG_FILE" 2>&1 || true
            log "PHP par défaut forcé sur ${PHP_VERSION} (php -> /usr/bin/php${PHP_VERSION})"
        fi

        # ── Extension redis : apt d'abord, PECL en fallback ───────────────────
        if ! php -m 2>/dev/null | grep -qi redis; then
            if apt-get install -y -qq "php${PHP_VERSION}-redis" >> "$LOG_FILE" 2>&1; then
                log "Extension redis installée (apt)"
            else
                update_progress 18 "Compilation extension redis... ⏳ Cela peut prendre 5 à 10 min"
                apt-get install -y -qq php-pear "php${PHP_VERSION}-dev" >> "$LOG_FILE" 2>&1 || true
                printf "\n" | pecl install redis >> "$LOG_FILE" 2>&1 || true
                # Trouver le .so compilé et l'activer
                local redis_so
                redis_so=$(find /usr/lib/php -name "redis.so" 2>/dev/null | head -1)
                if [[ -n "$redis_so" ]]; then
                    echo "extension=${redis_so}" > "/etc/php/${PHP_VERSION}/mods-available/redis.ini"
                    phpenmod -v "${PHP_VERSION}" redis
                    systemctl restart "php${PHP_VERSION}-fpm" >> "$LOG_FILE" 2>&1 || true
                    log "Extension redis installée (PECL) : ${redis_so}"
                else
                    warn "Extension redis non installée — à configurer manuellement."
                fi
            fi
        fi

        # ── Extension imagick : apt d'abord, PECL en fallback ────────────────
        # --no-install-recommends sur le paquet générique : chez sury, il tire
        # la dernière branche PHP et recommande libapache2-mod-php → apache2,
        # qui squatte le port 80 et empêche Nginx de démarrer.
        if ! php -m 2>/dev/null | grep -qi imagick; then
            if apt-get install -y -qq "php${PHP_VERSION}-imagick" >> "$LOG_FILE" 2>&1 \
            || apt-get install -y -qq --no-install-recommends php-imagick >> "$LOG_FILE" 2>&1; then
                log "Extension imagick installée (apt)"
            else
                update_progress 21 "Compilation extension imagick... ⏳ Cela peut prendre 5 à 10 min"
                apt-get install -y -qq php-pear "php${PHP_VERSION}-dev" libmagickwand-dev >> "$LOG_FILE" 2>&1 || true
                printf "\n" | pecl install imagick >> "$LOG_FILE" 2>&1 || true
                local imagick_so
                imagick_so=$(find /usr/lib/php -name "imagick.so" 2>/dev/null | head -1)
                if [[ -n "$imagick_so" ]]; then
                    echo "extension=${imagick_so}" > "/etc/php/${PHP_VERSION}/mods-available/imagick.ini"
                    phpenmod -v "${PHP_VERSION}" imagick
                    systemctl restart "php${PHP_VERSION}-fpm" >> "$LOG_FILE" 2>&1 || true
                    log "Extension imagick installée (PECL) : ${imagick_so}"
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
        update_progress 26 "Installation de Composer..."
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
            >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Composer."
        log "Composer installé"
    fi
}

# ── 3. MySQL 8 ────────────────────────────────────────────────────────────────
install_mysql() {
    update_progress 29 "Installation de MySQL 8... ⏳ Merci de patienter (2 à 4 min)"

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

    # ── Provisionnement base + utilisateur applicatif ─────────────────────────
    # root reste en auth_socket (durci par défaut Ubuntu) : l'accès admin passe
    # par `sudo mysql`, sans mot de passe à gérer. Le wizard n'a plus besoin
    # d'AUCUN accès root — sa page MySQL disparaît du flux standard.
    local mysql_cmd="mysql -u root"
    [[ -n "${MYSQL_ROOT_PASSWORD}" ]] && mysql_cmd="mysql -u root -p${MYSQL_ROOT_PASSWORD}"

    DB_NAME="pladigit"
    DB_APP_USER="pladigit"
    DB_APP_PASSWORD="$(openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | cut -c1-24)"
    [[ ${#DB_APP_PASSWORD} -ge 16 ]] || die "Génération du mot de passe applicatif échouée."

    ${mysql_cmd} >> "$LOG_FILE" 2>&1 <<SQL || die "Impossible de provisionner la base de données."
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_APP_USER}'@'localhost' IDENTIFIED BY '${DB_APP_PASSWORD}';
ALTER USER '${DB_APP_USER}'@'localhost' IDENTIFIED BY '${DB_APP_PASSWORD}';
GRANT ALL PRIVILEGES ON *.* TO '${DB_APP_USER}'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

    log "Base '${DB_NAME}' et utilisateur '${DB_APP_USER}' provisionnés (mot de passe généré)"
    step_done "MySQL 8"
    update_progress 42 "MySQL 8 ✅"
}

# ── 4. Services (Redis, Nginx, Supervisor, Node.js, Certbot) ──────────────────
install_services() {
    update_progress 43 "Installation des services... ⏳ Merci de patienter"

    # Redis
    command -v redis-server &>/dev/null || {
        apt-get install -y -qq redis-server >> "$LOG_FILE" 2>&1 || die "Impossible d'installer Redis."
        log "Redis installé"
    }
    systemctl enable redis-server >> "$LOG_FILE" 2>&1
    systemctl is-active --quiet redis-server || systemctl start redis-server >> "$LOG_FILE" 2>&1

    # ── Apache2 : éviction systématique avant Nginx ──────────────────────────
    # Apache peut arriver en recommandation des paquets PHP génériques (sury)
    # ou être préinstallé sur certaines images cloud. Il squatte le port 80
    # et empêche Nginx de démarrer. Garde-fou quel que soit le vecteur.
    if dpkg -l apache2 2>/dev/null | grep -q '^ii'; then
        warn "Apache2 détecté — retrait (conflit de port 80 avec Nginx)."
        systemctl stop apache2 >> "$LOG_FILE" 2>&1 || true
        systemctl disable apache2 >> "$LOG_FILE" 2>&1 || true
        apt-get purge -y -qq 'apache2*' 'libapache2-mod-php*' >> "$LOG_FILE" 2>&1 || true
        log "Apache2 retiré"
    fi

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
    log "Supervisor installé (worker posé après le clonage)"

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
    step_done "Services (Redis, Nginx...)"
    update_progress 57 "Services ✅"
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
    update_progress 58 "Téléchargement de Pladigit depuis GitHub..."

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

    update_progress 65 "Installation des dépendances PHP... ⏳ Merci de patienter (2 à 3 min)"
    sudo -u www-data "php${PHP_VERSION}" /usr/local/bin/composer install \
        --no-dev --optimize-autoloader --no-interaction \
        --working-dir="$PLADIGIT_DIR" \
        >> "$LOG_FILE" 2>&1 || die "Composer install échoué."
    log "Dépendances PHP installées"

    update_progress 75 "Compilation des assets JS/CSS... ⏳ Merci de patienter (1 à 2 min)"
    mkdir -p /var/www/.npm
    chown -R www-data:www-data /var/www/.npm
    sudo -u www-data npm ci --prefix "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
        || die "npm install échoué."
    sudo -u www-data npm run build --prefix "$PLADIGIT_DIR" >> "$LOG_FILE" 2>&1 \
        || die "npm build échoué."
    log "Assets compilés"

    chown -R www-data:www-data "${PLADIGIT_DIR}/install"

    supervisorctl reread >> "$LOG_FILE" 2>&1 || true
    supervisorctl update >> "$LOG_FILE" 2>&1 || true
    log "Workers Supervisor activés"
    step_done "Pladigit"
    update_progress 85 "Pladigit ✅"
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


# ── Écran de succès ───────────────────────────────────────────────────────────
show_success() {
    local install_url
    local ssl_mode="none"

    if [[ -n "$DOMAIN" ]]; then
        if ls /etc/letsencrypt/live/"${DOMAIN}"/fullchain.pem > /dev/null 2>&1; then
            install_url="https://${DOMAIN}/install/"
            ssl_mode="letsencrypt"
        elif ls /etc/ssl/pladigit/${DOMAIN}.crt > /dev/null 2>&1; then
            install_url="https://${DOMAIN}/install/"
            ssl_mode="selfsigned"
        else
            install_url="http://${DOMAIN}/install/"
            ssl_mode="none"
        fi
    else
        install_url="http://$(hostname -I | awk '{print $1}')/install/"
    fi

    local ssl_note=""
    if [[ "$ssl_mode" == "selfsigned" ]]; then
        ssl_note="\n\n⚠️  HTTPS temporaire activé (certificat auto-signé).\nVotre navigateur affichera un avertissement — cliquez\nsur 'Avancé' puis 'Continuer' pour accéder au site.\nL'activation HTTPS définitive se fait depuis le Super Admin."
    fi

    local msg_success
    case "$PROFIL" in
        1) msg_success="🎉 Votre plateforme Pladigit est prête !\n\nÉtape suivante — ouvrez votre navigateur et accédez à :\n\n  ${install_url}\n\nL'assistant de configuration vous guidera pour :\n  • Configurer la base de données\n  • Créer votre compte administrateur\n  • Configurer l'envoi d'emails (optionnel)\n\n💡 Durée : environ 5 minutes.${ssl_note}" ;;
        2) msg_success="🎉 Pladigit est installé en mode multi-organisations !\n\nÉtape suivante — ouvrez votre navigateur :\n\n  ${install_url}\n\nUne fois le wizard terminé, connectez-vous au Super Admin\npour créer les organisations (communes).${ssl_note}" ;;
        3) msg_success="🎉 Pladigit est installé !\n\nÉtape suivante :\n\n  ${install_url}\n\nConnectez-vous au Super Admin pour créer\nles communes membres.${ssl_note}" ;;
    esac

    dialog --title "✅ Installation terminée !" \
        --msgbox "${msg_success}" 26 70 2>/dev/tty

    # Nettoyer l'écran après fermeture de la boîte dialog
    clear

    log "Installation terminée — ${install_url} (ssl: ${ssl_mode})"

    # Afficher l'URL en clair dans le terminal pour copier-coller facile
    echo ""
    echo "╔══════════════════════════════════════════════════════════════════════╗"
    echo "║                   ✅  INSTALLATION TERMINÉE                         ║"
    echo "╠══════════════════════════════════════════════════════════════════════╣"
    echo "║                                                                      ║"
    printf "║  👉  Ouvrez cette adresse dans votre navigateur :                    ║\n"
    printf "║                                                                      ║\n"
    printf "║      %-66s║\n" "${install_url}"
    echo "║                                                                      ║"
    echo "╚══════════════════════════════════════════════════════════════════════╝"
    echo ""

    if [ -n "${DISPLAY:-}" ] || [ -n "${WAYLAND_DISPLAY:-}" ]; then
        xdg-open "${install_url}" 2>/dev/null &
    fi
}

# ── Vérification finale de l'installation ─────────────────────────────────────
check_install_ok() {
    local errors=0
    local warnings=""
    local app_url

    # Déterminer l'URL de base
    if [[ -n "$DOMAIN" ]]; then
        if ls /etc/letsencrypt/live/"${DOMAIN}"/fullchain.pem > /dev/null 2>&1 \
            || ls /etc/ssl/pladigit/${DOMAIN}.crt > /dev/null 2>&1; then
            app_url="https://${DOMAIN}"
        else
            app_url="http://${DOMAIN}"
        fi
    else
        app_url="http://$(hostname -I | awk '{print $1}')"
    fi

    # 1. Nginx actif
    if systemctl is-active --quiet nginx 2>/dev/null; then
        log "✓ Nginx : actif"
    else
        log "✗ Nginx : ARRÊTÉ"
        warnings+="\n• Nginx n'est pas démarré"
        errors=$((errors+1))
    fi

    # 2. Nginx écoute sur le bon port (443 si SSL, sinon 80)
    if echo "$app_url" | grep -q "^https"; then
        if ss -tlnp 2>/dev/null | grep -q ':443 '; then
            log "✓ Nginx port 443 : ouvert"
        else
            log "✗ Nginx port 443 : FERMÉ — vérifier la config SSL"
            warnings+="\n• Nginx n'écoute pas sur le port 443"
            errors=$((errors+1))
        fi
    else
        if ss -tlnp 2>/dev/null | grep -q ':80 '; then
            log "✓ Nginx port 80 : ouvert"
        else
            log "✗ Nginx port 80 : FERMÉ"
            warnings+="\n• Nginx n'écoute pas sur le port 80"
            errors=$((errors+1))
        fi
    fi

    # 3. PHP-FPM actif
    if systemctl is-active --quiet "php${PHP_VERSION}-fpm" 2>/dev/null; then
        log "✓ PHP-FPM ${PHP_VERSION} : actif"
    else
        log "✗ PHP-FPM ${PHP_VERSION} : ARRÊTÉ"
        warnings+="\n• PHP-FPM n'est pas démarré"
        errors=$((errors+1))
    fi

    # 4. Laravel répond sur /health/ping (max 10s)
    local http_code
    http_code=$(curl -sk -o /dev/null -w "%{http_code}" --max-time 10 "${app_url}/health/ping" 2>/dev/null || echo "000")
    if [[ "$http_code" == "200" ]]; then
        log "✓ Application Laravel : répond (HTTP 200)"
    else
        log "✗ Application Laravel : ne répond pas (HTTP ${http_code})"
        warnings+="\n• L'application ne répond pas encore (HTTP ${http_code})"
        # Non bloquant — le runner peut encore tourner en arrière-plan
    fi

    # 5. Nginx -t (config valide)
    if nginx -t >> "$LOG_FILE" 2>&1; then
        log "✓ Configuration Nginx : valide"
    else
        log "✗ Configuration Nginx : INVALIDE"
        warnings+="\n• La configuration Nginx contient des erreurs (voir le log)"
        errors=$((errors+1))
    fi

    # 6. Worker de queue : configuré et planifié pour démarrage automatique.
    #    Le worker ne tourne PAS encore à ce stade (l'application est finalisée
    #    dans le wizard navigateur, après ce script). Le watchdog le démarrera
    #    automatiquement dans les 2 minutes suivant la fin du wizard. On vérifie
    #    donc la présence du dispositif, pas l'état RUNNING immédiat.
    if [[ -f /etc/supervisor/conf.d/pladigit-worker.conf ]] \
        && [[ -f /etc/cron.d/pladigit-worker-watchdog ]]; then
        log "✓ Worker de queue : configuré (démarrage auto après le wizard)"
    else
        log "✗ Worker de queue : dispositif INCOMPLET"
        warnings+="\n• Le worker de file d'attente n'est pas correctement configuré"
        errors=$((errors+1))
    fi

    # Bilan
    if [[ $errors -gt 0 ]]; then
        log "⚠ Installation terminée avec ${errors} problème(s) — voir ${LOG_FILE}"
        dialog --title "⚠ Points à vérifier" \
            --msgbox "L'installation s'est terminée mais ${errors} problème(s) ont été détectés :\n${warnings}\n\nConsultez le journal : ${LOG_FILE}\n\nVous pouvez contacter le support avec ce fichier." \
            16 70 2>/dev/tty
    else
        log "✓ Vérification finale : tout est OK"
    fi
}


# ── Mise à jour ───────────────────────────────────────────────────────────────
do_update() {
    dialog --title "Pladigit — Mise à jour" \
        --yesno "Une installation Pladigit a été détectée sur ce serveur.\n\nSouhaitez-vous la mettre à jour ?\n\n• Le code sera mis à jour (git pull)\n• Les dépendances seront mises à jour\n• Les migrations seront appliquées\n• Vos données ne seront PAS supprimées" \
        16 70 2>/dev/tty || { log "Mise à jour annulée."; exit 0; }

    wt_info "Mise à jour" "⬆️  Mise à jour en cours..." 6 60
    cd "$PLADIGIT_DIR" || die "Impossible d'accéder à ${PLADIGIT_DIR}"

    git pull origin main >> "$LOG_FILE" 2>&1 || warn "git pull échoué"
    sudo -u www-data "php${PHP_VERSION}" /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction \
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

# ==============================================================================
#  REFONTE — Registre de modules, rendu Nginx central, SSL, points d'entrée
# ==============================================================================

ensure_jq() {
    command -v jq >/dev/null 2>&1 && return 0
    apt-get install -y -qq jq >> "${LOG_FILE:-/var/log/pladigit-install.log}" 2>&1 \
        || warn "jq introuvable — lecture des descriptifs de modules indisponible."
}

config_get() {
    local f="${PLADIGIT_DIR}/install/config.json"
    [[ -f "$f" ]] && jq -r "$1 // empty" "$f" 2>/dev/null || true
}

# ── Registre de modules ───────────────────────────────────────────────────────
MODULES_DIR="${PLADIGIT_DIR}/install/modules"
ACTIVE_MODULES_FILE="${PLADIGIT_DIR}/install/active-modules"

mj() { jq -r "$2" "${MODULES_DIR}/$1/module.json" 2>/dev/null; }

module_exists()    { [[ -f "${MODULES_DIR}/$1/module.json" ]]; }
is_module_active() { [[ -f "$ACTIVE_MODULES_FILE" ]] && grep -qxF "$1" "$ACTIVE_MODULES_FILE"; }

mark_module_active() {
    mkdir -p "$(dirname "$ACTIVE_MODULES_FILE")"
    is_module_active "$1" || echo "$1" >> "$ACTIVE_MODULES_FILE"
    sort -u -o "$ACTIVE_MODULES_FILE" "$ACTIVE_MODULES_FILE" 2>/dev/null || true
    chown www-data:www-data "$ACTIVE_MODULES_FILE" 2>/dev/null || true
}

# Vérifie les préconditions du descriptif. 0 = tout passe, 1 = au moins un échec.
module_preflight() {
    local mod="$1" n i type val label ok=0
    module_exists "$mod" || { warn "Module ${mod} introuvable."; return 1; }
    n=$(mj "$mod" '.preflight | length'); [[ "$n" =~ ^[0-9]+$ ]] || n=0
    for ((i=0; i<n; i++)); do
        type=$(mj "$mod" ".preflight[$i].type")
        val=$(mj  "$mod" ".preflight[$i].value")
        label=$(mj "$mod" ".preflight[$i].label")
        case "$type" in
            disk_gb)
                local free_gb
                free_gb=$(df -BG --output=avail "$PLADIGIT_DIR" 2>/dev/null | tail -1 | tr -dc '0-9')
                [[ -n "$free_gb" && "$free_gb" -ge "$val" ]] \
                    || { warn "Preflight ${mod} : ${label} (dispo ${free_gb:-?} Go)"; ok=1; } ;;
            ram_mb)
                local ram_mb; ram_mb=$(free -m | awk '/^Mem:/{print $2}')
                [[ -n "$ram_mb" && "$ram_mb" -ge "$val" ]] \
                    || { warn "Preflight ${mod} : ${label}"; ok=1; } ;;
            command)
                command -v "$val" >/dev/null 2>&1 \
                    || { warn "Preflight ${mod} : ${label} ('${val}' absent)"; ok=1; } ;;
            *) warn "Preflight ${mod} : type inconnu '${type}' ignoré." ;;
        esac
    done
    return $ok
}

# Provisionnement système d'un module : preflight → provision.sh → marque actif.
provision_module() {
    local mod="$1" dir prov
    [[ -z "$mod" ]] && { warn "provision_module : identifiant manquant."; return 1; }
    module_exists "$mod" || { warn "Module ${mod} introuvable — ignoré."; return 1; }
    dir="${MODULES_DIR}/${mod}"

    if ! module_preflight "$mod"; then
        warn "Preflight du module ${mod} non satisfait — module non installé."
        return 1
    fi

    prov=$(mj "$mod" '.provision // empty')
    if [[ -n "$prov" && -f "${dir}/${prov}" ]]; then
        info "Provisionnement du module ${mod}..."
        DOMAIN="$DOMAIN" LOG_FILE="$LOG_FILE" bash "${dir}/${prov}" "$DOMAIN" >> "$LOG_FILE" 2>&1 \
            || { warn "Provisionnement du module ${mod} incomplet."; return 1; }
    fi

    mark_module_active "$mod"
    log "Module ${mod} provisionné et actif."
}

# Insère ou met à jour une clé dans le .env (sans réécrire le reste).
env_upsert() {
    local key="$1" val="$2" env="${PLADIGIT_DIR}/.env"
    [[ -f "$env" ]] || return 0
    if grep -q "^${key}=" "$env"; then
        sed -i "s|^${key}=.*|${key}=${val}|" "$env"
    else
        echo "${key}=${val}" >> "$env"
    fi
}

# Fusionne les variables d'environnement déclarées par un module dans le .env.
env_upsert_module() {
    local mod="$1" keys k v drv
    keys=$(mj "$mod" '.env | keys[]' 2>/dev/null) || return 0
    while IFS= read -r k; do
        [[ -z "$k" ]] && continue
        v=$(mj "$mod" ".env[\"$k\"]")
        v="${v//\{\{DOMAIN\}\}/$DOMAIN}"
        env_upsert "$k" "$v"
    done <<< "$keys"
    drv=$(mj "$mod" '.capability.driver_var // empty')
    [[ -n "$drv" ]] && env_upsert "$drv" "$mod"
}

# Exécute les commandes artisan post-installation déclarées par le module.
run_post_install() {
    local mod="$1" n i cmd
    n=$(mj "$mod" '.post_install | length'); [[ "$n" =~ ^[0-9]+$ ]] || n=0
    for ((i=0; i<n; i++)); do
        cmd=$(mj "$mod" ".post_install[$i]")
        [[ -z "$cmd" ]] && continue
        sudo -u www-data php "${PLADIGIT_DIR}/artisan" $cmd >> "$LOG_FILE" 2>&1 || true
    done
}

# Activation autonome et complète d'un module (DGS après coup / ligne de commande).
add_module() {
    local mod="$1"
    [[ -z "$DOMAIN" ]] && DOMAIN=$(config_get '.install.domain')
    provision_module "$mod" || return 1
    env_upsert_module "$mod"
    render_nginx "${SSL_MODE:-none}"
    run_post_install "$mod"
    sudo -u www-data php "${PLADIGIT_DIR}/artisan" config:cache >> "$LOG_FILE" 2>&1 || true
    log "Module ${mod} activé."
}

# ── Rendu Nginx central — déterministe à partir de l'état { SSL, modules } ─────
render_nginx() {
    # Détection de la syntaxe HTTP/2 selon la version de Nginx.
    # Nginx >= 1.25.1 : directive « http2 on; ». Avant : « listen ... ssl http2; ».
    local _nginx_ver _http2_line _listen443 _listen443_6
    _nginx_ver=$(nginx -v 2>&1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)
    if [[ -n "$_nginx_ver" ]] && dpkg --compare-versions "$_nginx_ver" ge "1.25.1"; then
        _listen443="listen 443 ssl;"
        _listen443_6="listen [::]:443 ssl;"
        _http2_line="    http2 on;"
    else
        _listen443="listen 443 ssl http2;"
        _listen443_6="listen [::]:443 ssl;"
        _http2_line=""
    fi
    local ssl_mode="${1:-none}"
    local conf="/etc/nginx/sites-available/pladigit"
    local server_name="_"
    [[ -n "$DOMAIN" ]] && server_name="${DOMAIN} *.${DOMAIN}"

    local module_blocks="" mod tpl
    if [[ -f "$ACTIVE_MODULES_FILE" ]]; then
        while IFS= read -r mod; do
            [[ -z "$mod" ]] && continue
            tpl="${MODULES_DIR}/${mod}/nginx.conf.tpl"
            [[ -f "$tpl" ]] && module_blocks+=$'\n'"$(cat "$tpl")"
        done < "$ACTIVE_MODULES_FILE"
    fi

    local body
    read -r -d '' body <<NGINX_BODY || true
    root ${PLADIGIT_DIR}/public;
    index index.php index.html;
    client_max_body_size 100M;
    server_tokens off;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self' https://${DOMAIN} https://*.${DOMAIN} wss://${DOMAIN} wss://*.${DOMAIN} wss: ws:; frame-src 'self' https://${DOMAIN} https://*.${DOMAIN} blob:; worker-src 'self' blob:; object-src 'none'; base-uri 'self';" always;

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
        root ${PLADIGIT_DIR};
        index index.php;
        location ~ \.php\$ {
            fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
            fastcgi_param SCRIPT_FILENAME ${PLADIGIT_DIR}\$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 300;
        }
    }
${module_blocks}
NGINX_BODY

    if [[ "$ssl_mode" == "none" || -z "$DOMAIN" ]]; then
        cat > "$conf" <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${server_name};
${body}
}
NGINX
    else
        local cert key extra=""
        if [[ "$ssl_mode" == "letsencrypt" ]]; then
            cert="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
            key="/etc/letsencrypt/live/${DOMAIN}/privkey.pem"
            extra="    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
    add_header Strict-Transport-Security \"max-age=31536000; includeSubDomains\" always;"
        else
            cert="/etc/ssl/pladigit/${DOMAIN}.crt"
            key="/etc/ssl/pladigit/${DOMAIN}.key"
        fi
        cat > "$conf" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${server_name};
    return 301 https://\$host\$request_uri;
}
server {
    ${_listen443}
    ${_listen443_6}
${_http2_line}
    server_name ${server_name};
    ssl_certificate     ${cert};
    ssl_certificate_key ${key};
${extra}
${body}
}
NGINX
    fi

    ln -sf "$conf" /etc/nginx/sites-enabled/pladigit
    rm -f /etc/nginx/sites-enabled/default
    if ! nginx -t >> "$LOG_FILE" 2>&1; then
        warn "Configuration Nginx invalide après rendu."; return 1
    fi
    systemctl reload nginx >> "$LOG_FILE" 2>&1 || systemctl restart nginx >> "$LOG_FILE" 2>&1
    systemctl restart "php${PHP_VERSION}-fpm" >> "$LOG_FILE" 2>&1 || true
    log "Nginx rendu (ssl=${ssl_mode})"
}

# ── Certificat auto-signé (repli si Let's Encrypt indisponible) ───────────────
setup_self_signed_cert() {
    mkdir -p /etc/ssl/pladigit
    if [[ ! -f "/etc/ssl/pladigit/${DOMAIN}.crt" ]]; then
        openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
            -keyout "/etc/ssl/pladigit/${DOMAIN}.key" \
            -out    "/etc/ssl/pladigit/${DOMAIN}.crt" \
            -subj "/CN=${DOMAIN}" \
            -addext "subjectAltName=DNS:${DOMAIN},DNS:*.${DOMAIN}" >> "$LOG_FILE" 2>&1 \
            || warn "Génération du certificat auto-signé échouée."
    fi
    chmod 600 "/etc/ssl/pladigit/${DOMAIN}.key" 2>/dev/null || true
    log "Certificat auto-signé prêt (${DOMAIN})."
}

# ── SSL : décide le mode puis délègue le rendu Nginx ──────────────────────────
setup_ssl() {
    render_nginx none

    if [[ -z "$DOMAIN" ]]; then
        SSL_MODE="none"; warn "Aucun domaine — SSL ignoré."; return
    fi

    if [[ -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]] \
       && openssl x509 -checkend 2592000 -noout -in "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" >/dev/null 2>&1; then
        SSL_MODE="letsencrypt"; render_nginx letsencrypt
        log "Certificat Let's Encrypt déjà valide."
        return
    fi

    update_progress 92 "Obtention du certificat HTTPS... ⏳"
    local server_ip dns_ip
    server_ip=$(curl -4 -sf --max-time 5 https://ifconfig.me 2>/dev/null || hostname -I | awk '{print $1}')
    dns_ip=$(dig +short "${DOMAIN}" A 2>/dev/null | tail -1)

    if [[ -n "$dns_ip" && "$dns_ip" != "$server_ip" ]]; then
        warn "DNS : ${DOMAIN} pointe vers ${dns_ip} mais ce serveur est ${server_ip}."
    fi

    if [[ -z "$dns_ip" || "$dns_ip" == "$server_ip" ]] \
       && certbot certonly --nginx -d "${DOMAIN}" --non-interactive --agree-tos \
            --email "${SSL_EMAIL}" >> "$LOG_FILE" 2>&1; then
        SSL_MODE="letsencrypt"; render_nginx letsencrypt
        if ! crontab -l 2>/dev/null | grep -q "certbot renew"; then
            (crontab -l 2>/dev/null; echo "0 3 * * * certbot renew --quiet --post-hook 'systemctl reload nginx'") | crontab -
        fi
        log "Certificat Let's Encrypt obtenu."
    else
        warn "Let's Encrypt indisponible — repli sur certificat auto-signé."
        setup_self_signed_cert
        SSL_MODE="selfsigned"; render_nginx selfsigned
    fi
}

# ── Certificat wildcard *.DOMAIN via OVH DNS-01 (mutualisé) ───────────────────
# Lancé UNE FOIS par l'hébergeur du mutualisé (jamais par le prestataire de base).
# Demande les 3 clés API OVH en interactif — elles ne sont JAMAIS stockées dans le dépôt.
setup_wildcard() {
    local domain="$1"
    local cred="/etc/letsencrypt/ovh-${domain}.ini"

    echo ""
    echo "=== Configuration du certificat wildcard *.${domain} (OVH DNS) ==="
    echo "Cette étape obtient un certificat couvrant ${domain} ET tous ses sous-domaines"
    echo "(une commune = un sous-domaine). À lancer une seule fois, par l'hébergeur."
    echo ""
    echo "Prérequis : un token API OVH créé sur https://api.ovh.com/createToken/"
    echo "  Validité : illimitée"
    echo "  Droits   : GET, POST, PUT, DELETE sur /domain/zone/*"
    echo ""

    # Plugin certbot OVH (absent par défaut)
    if ! certbot plugins 2>/dev/null | grep -q 'dns-ovh'; then
        log "Installation du plugin certbot-dns-ovh..."
        apt-get install -y -qq python3-certbot-dns-ovh >> "$LOG_FILE" 2>&1 \
            || { warn "Échec installation du plugin certbot-dns-ovh."; return 1; }
    fi

    # Saisie interactive des 3 clés (jamais journalisées)
    local ak as ck
    read -rp "OVH Application Key    : " ak
    read -rp "OVH Application Secret : " as
    read -rp "OVH Consumer Key       : " ck
    if [[ -z "$ak" || -z "$as" || -z "$ck" ]]; then
        warn "Les trois clés sont requises. Abandon."; return 1
    fi

    # Fichier de credentials, lisible par root seul
    umask 077
    cat > "$cred" <<OVH
dns_ovh_endpoint = ovh-eu
dns_ovh_application_key = ${ak}
dns_ovh_application_secret = ${as}
dns_ovh_consumer_key = ${ck}
OVH
    chmod 600 "$cred"
    log "Credentials OVH écrits (${cred}, chmod 600)."

    # Obtention du certificat wildcard
    log "Demande du certificat wildcard *.${domain} (peut prendre 1-2 min)..."
    if certbot certonly --dns-ovh \
        --dns-ovh-credentials "$cred" \
        --dns-ovh-propagation-seconds 60 \
        -d "${domain}" -d "*.${domain}" \
        --non-interactive --agree-tos --email "${SSL_EMAIL:-contact@${domain}}" \
        --cert-name "${domain}" >> "$LOG_FILE" 2>&1; then
        log "Certificat wildcard obtenu."
        # Drapeau applicatif : les nouvelles organisations naîtront en HTTPS.
        if grep -q '^WILDCARD_SSL=' "${PLADIGIT_DIR}/.env" 2>/dev/null; then
            sed -i 's/^WILDCARD_SSL=.*/WILDCARD_SSL=true/' "${PLADIGIT_DIR}/.env"
        else
            echo "WILDCARD_SSL=true" >> "${PLADIGIT_DIR}/.env"
        fi
        # Les organisations existantes encore en 'none' passent en letsencrypt.
        sudo -u www-data php "${PLADIGIT_DIR}/artisan" tinker --execute="\App\Models\Platform\Organization::where('ssl_type','none')->update(['ssl_type'=>'letsencrypt']);" >> "$LOG_FILE" 2>&1 || true
        sudo -u www-data php "${PLADIGIT_DIR}/artisan" config:cache >> "$LOG_FILE" 2>&1 || true
        SSL_MODE="letsencrypt"; render_nginx letsencrypt
        systemctl restart nginx >> "$LOG_FILE" 2>&1 || true
        echo ""
        echo "✓ Wildcard *.${domain} actif. Tous les sous-domaines sont désormais en HTTPS."
        echo "  Le renouvellement automatique est géré par certbot."
        return 0
    else
        warn "Échec de l'obtention du wildcard. Voir ${LOG_FILE}."
        echo "  Vérifiez les droits du token OVH : GET/POST/PUT/DELETE sur /domain/zone/*"
        return 1
    fi
}

# ── Worker de queue (posé en root, après le clonage du code) ──────────────────
#
# Important : pendant l'installation, l'application n'est PAS encore finalisée
# (APP_KEY définitive, migrations et premier admin sont créés par le wizard
# navigateur, APRÈS la fin de ce script). Démarrer le worker maintenant le ferait
# échouer et passer en FATAL définitif sous supervisor.
#
# Stratégie retenue :
#   - autostart=false  → supervisor ne tente PAS de lancer le worker pendant l'install
#   - un watchdog cron  → démarre/maintient le worker dès que l'application répond,
#                         c.-à-d. une fois le wizard navigateur terminé, puis le
#                         surveille en permanence (auto-réparation à vie).
setup_worker() {
    cat > /etc/supervisor/conf.d/pladigit-worker.conf <<WORKER
[program:pladigit-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php${PHP_VERSION} ${PLADIGIT_DIR}/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=false
autorestart=true
startretries=3
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/pladigit-worker.log
stopwaitsecs=3600
WORKER
    supervisorctl reread >> "$LOG_FILE" 2>&1 || true
    supervisorctl update >> "$LOG_FILE" 2>&1 || true

    # Watchdog : toutes les 2 minutes, si l'application est finalisée
    # (présence d'une APP_KEY non vide dans .env), s'assure que le worker tourne.
    # « supervisorctl start » est sans effet si le worker tourne déjà → aucune
    # interruption des jobs en cours.
    cat > /usr/local/bin/pladigit-worker-watchdog.sh <<'WATCHDOG'
#!/bin/bash
# Démarre le worker Pladigit dès que l'application est prête, puis le maintient.
ENV_FILE="/var/www/pladigit/.env"
# Ne rien faire tant que l'application n'est pas finalisée (APP_KEY absente/vide).
if ! grep -qE '^APP_KEY=base64:.+' "$ENV_FILE" 2>/dev/null; then
    exit 0
fi
/usr/bin/supervisorctl start pladigit-worker:* >/dev/null 2>&1
exit 0
WATCHDOG
    chmod 755 /usr/local/bin/pladigit-worker-watchdog.sh

    cat > /etc/cron.d/pladigit-worker-watchdog <<'CRON'
# Maintien du worker de queue Pladigit (auto-démarrage après le wizard + auto-réparation)
*/2 * * * * root /usr/local/bin/pladigit-worker-watchdog.sh
CRON
    chmod 644 /etc/cron.d/pladigit-worker-watchdog

    log "Worker de queue Pladigit configuré (démarrage différé via watchdog)"
}

# ── Point d'entrée ────────────────────────────────────────────────────────────
main() {
    # Modes non interactifs (appelés par le wizard ou en ligne de commande)
    case "${1:-}" in
        --setup-wildcard)
            [[ $EUID -ne 0 ]] && { echo "Mode --setup-wildcard : root requis (sudo)."; exit 1; }
            LOG_FILE="/var/log/pladigit-install.log"; mkdir -p "$(dirname "$LOG_FILE")"
            ensure_jq
            DOMAIN=$(config_get '.install.domain')
            [[ -z "$DOMAIN" ]] && { echo "Domaine introuvable dans config.json. Installation d'abord."; exit 1; }
            setup_wildcard "$DOMAIN"; exit $?
            ;;
        --provision-module|--add-module|--collabora-only)
            [[ $EUID -ne 0 ]] && { echo "Mode ${1} : root requis (sudo)."; exit 1; }
            LOG_FILE="/var/log/pladigit-install.log"; mkdir -p "$(dirname "$LOG_FILE")"
            ensure_jq
            case "$1" in
                --collabora-only)
                    # Compatibilité ascendante avec l'ancien wizard
                    APP_URL="${2:-}"; PLADIGIT_DIR="${3:-/var/www/pladigit}"
                    DOMAIN=$(echo "$APP_URL" | sed -e 's|https\?://||' -e 's|/.*||')
                    MODULES_DIR="${PLADIGIT_DIR}/install/modules"
                    ACTIVE_MODULES_FILE="${PLADIGIT_DIR}/install/active-modules"
                    SSL_MODE=$(config_get '.install.ssl_mode'); [[ -z "$SSL_MODE" ]] && SSL_MODE="letsencrypt"
                    add_module collabora; exit $?
                    ;;
                --provision-module)
                    DOMAIN=$(config_get '.install.domain')
                    SSL_MODE=$(config_get '.install.ssl_mode'); [[ -z "$SSL_MODE" ]] && SSL_MODE="none"
                    provision_module "${2:-}" || exit 1
                    render_nginx "$SSL_MODE"; exit 0
                    ;;
                --add-module)
                    DOMAIN=$(config_get '.install.domain')
                    SSL_MODE=$(config_get '.install.ssl_mode'); [[ -z "$SSL_MODE" ]] && SSL_MODE="none"
                    add_module "${2:-}"; exit $?
                    ;;
            esac
            ;;
    esac

    exec < /dev/tty
    mkdir -p "$(dirname "$LOG_FILE")"
    echo "=== Pladigit Install Log v${INSTALL_VERSION} — $(date) ===" > "$LOG_FILE"
    command -v dialog &>/dev/null || apt-get install -y -qq dialog >> "$LOG_FILE" 2>&1 || true
    [[ $EUID -ne 0 ]] && { echo "Ce script doit être exécuté en tant que root (sudo)."; exit 1; }
    ensure_jq

    show_welcome

    # Installation existante détectée ? (suffit que .env existe)
    [[ -f "${PLADIGIT_DIR}/.env" ]] && do_update

    # Public unique (commune) — le multi-organisations est géré par le wizard
    PROFIL=1

    set +e
    ask_domain
    ask_email
    ask_admin_ip
    show_recap

    check_prerequisites
    start_progress
    if [[ "$ALL_INSTALLED" == true ]] && [[ -d "${PLADIGIT_DIR}/.git" ]]; then
        install_pladigit
    else
        update_system
        install_php
        install_mysql
        install_services
        setup_logs
        install_pladigit
    fi
    setup_worker
    setup_ssl
    setup_cron
    write_wizard_config
    update_progress 100 "Préparation terminée — finalisez dans le navigateur"
    stop_progress
    check_install_ok
    show_success
    rm -f "$DIALOGRC" 2>/dev/null || true
}

main "$@"
