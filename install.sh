#!/usr/bin/env bash
# ==============================================================================
#  Pladigit — Script d'installation automatique
#  Version : 2.0.0
#  Cible   : Ubuntu 22.04 LTS / 24.04 LTS
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
STEPS_TODO=("Mise à jour système" "PHP 8.4 + Composer" "MySQL 8" "Services (Redis, Nginx...)" "Pladigit" "Configuration Nginx" "SSL + finalisation" "Collabora Online")

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

# ── Choix du profil ───────────────────────────────────────────────────────────
choose_profil() {
    local choice
    # dialog écrit le choix sur stderr — redirection 3>&1 1>/dev/tty 2>&3
    choice=$(dialog --title "Pladigit — Qui êtes-vous ?" \
        --menu "\
Choisissez votre situation pour adapter l'installation :" \
        20 78 3 \
        "1" "Je suis une commune ou une petite collectivité" \
        "2" "Je gère l'informatique de plusieurs communes (maison des communes...)" \
        "3" "Je suis technicien d'une communauté de communes" \
        3>&1 1>/dev/tty 2>&3) || die "Installation annulée."

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

    local msg_ip
    case "$PROFIL" in
        1) msg_ip="Pour protéger l'accès à l'administration de Pladigit,\nentrez l'adresse IP de votre ordinateur.\n\n⚠ Attention : l'IP ci-dessous est celle du SERVEUR, pas la vôtre !\n\n👉 Pour connaître votre IP, ouvrez dans un navigateur :\n   https://www.mon-ip.com\n   https://ifconfig.me\n\nIP de ce serveur (pour référence) : ${server_ip}" ;;
        2) msg_ip="Entrez la ou les adresses IP autorisées à accéder\nau Super Admin (interface de gestion des communes).\n\n👉 Pour connaître votre IP, ouvrez dans un navigateur :\n   https://www.mon-ip.com\n   https://ifconfig.me\n\nVous pouvez saisir plusieurs IP séparées par des virgules :\n  88.123.45.67,192.168.1.10\n\nIP de ce serveur (pour référence) : ${server_ip}" ;;
        3) msg_ip="Entrez l'adresse IP du ou des techniciens autorisés\nà accéder au Super Admin.\n\n👉 Pour connaître votre IP, ouvrez dans un navigateur :\n   https://www.mon-ip.com\n   https://ifconfig.me\n\nSéparez plusieurs IP par des virgules :\n  88.123.45.67,88.123.45.68\n\nIP de ce serveur (pour référence) : ${server_ip}" ;;
    esac

    while [[ -z "$ADMIN_IPS" ]]; do
        ADMIN_IPS=$(dialog --title "Pladigit — IP Super Admin" \
            --inputbox "${msg_ip}" 18 70 "" 3>&1 1>/dev/tty 2>&3) || die "Installation annulée."
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
        if ! php -m 2>/dev/null | grep -qi imagick; then
            if apt-get install -y -qq "php${PHP_VERSION}-imagick" >> "$LOG_FILE" 2>&1 \
            || apt-get install -y -qq php-imagick >> "$LOG_FILE" 2>&1; then
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

    local mysql_cmd="mysql -u root"
    [[ -n "${MYSQL_ROOT_PASSWORD}" ]] && mysql_cmd="mysql -u root -p${MYSQL_ROOT_PASSWORD}"

    if [[ -z "${MYSQL_ROOT_PASSWORD}" ]]; then
        ${mysql_cmd} -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY ''; FLUSH PRIVILEGES;" \
            >> "$LOG_FILE" 2>&1 || warn "ALTER USER root ignoré — déjà configuré."
    fi

    log "Authentification MySQL configurée"
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
    sudo -u www-data composer install \
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

    # Écrire config.json pour le wizard
    write_wizard_config
    chown -R www-data:www-data "${PLADIGIT_DIR}/install"

    log "Script Collabora intégré — sera installé après SSL"

    supervisorctl reread >> "$LOG_FILE" 2>&1 || true
    supervisorctl update >> "$LOG_FILE" 2>&1 || true
    log "Workers Supervisor activés"
    step_done "Pladigit"
    update_progress 85 "Pladigit ✅"
}

# ── 7. Nginx ──────────────────────────────────────────────────────────────────
configure_nginx() {
    update_progress 86 "Configuration de Nginx..."

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

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self' wss: ws:; frame-src 'self' blob:; worker-src 'self' blob:; object-src 'none'; base-uri 'self';" always;

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
    step_done "Configuration Nginx"
    update_progress 90 "Nginx ✅"
}

# ── SSL ───────────────────────────────────────────────────────────────────────
setup_ssl() {
    local env_file="${PLADIGIT_DIR}/.env"

    [[ -z "$DOMAIN" ]] && { warn "Aucun domaine — SSL ignoré."; return; }

    # Vérifier si le certificat est valide (pas expiré, pas dans moins de 30 jours)
    local cert_valid=false
    if [[ -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]]; then
        if openssl x509 -checkend 2592000 -noout \
            -in "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" >> "$LOG_FILE" 2>&1; then
            cert_valid=true
        else
            warn "Certificat présent mais expiré ou expirant sous 30 jours — renouvellement."
            certbot renew --cert-name "${DOMAIN}" --non-interactive >> "$LOG_FILE" 2>&1 \
                && cert_valid=true \
                || warn "Renouvellement échoué — on continue."
        fi
    fi

    # Cert valide — vérifier que le bloc 443 existe dans Nginx
    if [[ "$cert_valid" == true ]]; then
        log "Certificat SSL valide pour ${DOMAIN}"
        if grep -q "listen 443" /etc/nginx/sites-available/pladigit 2>/dev/null; then
            log "Bloc HTTPS Nginx déjà présent"
        else
            update_progress 92 "Injection du bloc HTTPS Nginx..."
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
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self' wss: ws:; frame-src 'self' blob:; worker-src 'self' blob:; object-src 'none'; base-uri 'self';" always;
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

    # Cert absent ou invalide — on demande un nouveau certificat
    update_progress 91 "Obtention du certificat HTTPS... ⏳ Merci de patienter"

    # Vérification DNS avant de contacter Let's Encrypt
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
        warn "Échec Let's Encrypt — génération d'un certificat auto-signé temporaire."
        setup_self_signed_cert
    fi
}

# ── Certificat auto-signé (fallback si Let's Encrypt échoue) ─────────────────
setup_self_signed_cert() {
    local env_file="${PLADIGIT_DIR}/.env"
    local key_path="/etc/ssl/private/pladigit-selfsigned.key"
    local cert_path="/etc/ssl/certs/pladigit-selfsigned.crt"

    update_progress 93 "Génération d'un certificat de sécurité temporaire..."

    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$key_path" \
        -out "$cert_path" \
        -subj "/CN=${DOMAIN}" >> "$LOG_FILE" 2>&1 || { warn "Impossible de créer le certificat auto-signé."; return; }

    # Config Nginx avec certificat auto-signé
    local nginx_server_name="${DOMAIN} *.${DOMAIN}"
    cat > /etc/nginx/sites-available/pladigit << NGINX_SELFSIGNED
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
    ssl_certificate     ${cert_path};
    ssl_certificate_key ${key_path};
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;
    root ${PLADIGIT_DIR}/public;
    index index.php index.html;
    client_max_body_size 100M;
    server_tokens off;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
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
NGINX_SELFSIGNED

    nginx -t >> "$LOG_FILE" 2>&1 && systemctl reload nginx >> "$LOG_FILE" 2>&1 \
        && log "Nginx configuré avec certificat auto-signé"

    # .env : HTTPS activé mais SESSION_SECURE_COOKIE=false (cert non reconnu par navigateur)
    if [[ -f "$env_file" ]]; then
        sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" "$env_file"
        grep -q "^SESSION_DOMAIN=" "$env_file" \
            && sed -i "s|^SESSION_DOMAIN=.*|SESSION_DOMAIN=.${DOMAIN}|" "$env_file" \
            || echo "SESSION_DOMAIN=.${DOMAIN}" >> "$env_file"
        grep -q "^SESSION_SECURE_COOKIE=" "$env_file" \
            && sed -i "s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=false|" "$env_file" \
            || echo "SESSION_SECURE_COOKIE=false" >> "$env_file"
        # Marquer le type de certificat pour Pladigit
        grep -q "^SSL_TYPE=" "$env_file" \
            && sed -i "s|^SSL_TYPE=.*|SSL_TYPE=self_signed|" "$env_file" \
            || echo "SSL_TYPE=self_signed" >> "$env_file"
        log ".env mis à jour (auto-signé)"
    fi

    log "Certificat auto-signé généré — HTTPS temporaire actif."
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

# ── Collabora Online — installation intégrée ─────────────────────────────────
install_collabora() {
    # Appelé uniquement si l'utilisateur a choisi "local" dans le wizard
    # (COLLABORA_MODE est positionné par le wizard via config.json)
    # En mode install.sh seul (sans wizard), on installe toujours Collabora.

    update_progress 95 "Collabora Online : installation Docker... ⏳ 10 à 20 min"

    # ── 1. Docker ──────────────────────────────────────────────────────────
    if ! command -v docker &>/dev/null; then
        DEBIAN_FRONTEND=noninteractive apt-get install -y -qq docker.io >> "$LOG_FILE" 2>&1             || { warn "Docker non installé — Collabora ignoré."; return; }
    fi
    systemctl enable docker >> "$LOG_FILE" 2>&1
    systemctl start  docker >> "$LOG_FILE" 2>&1
    sleep 2
    if ! systemctl is-active --quiet docker; then
        warn "Docker ne démarre pas — Collabora ignoré."
        return
    fi
    log "Docker prêt"

    # ── 2. Image Docker ────────────────────────────────────────────────────
    if ! docker image inspect collabora/code &>/dev/null; then
        log "Collabora : téléchargement de l'image (~1.5 Go, 10-20 min)..."
        docker pull collabora/code >> "$LOG_FILE" 2>&1 &
        PULL_PID=$!
        ELAPSED=0
        while kill -0 "$PULL_PID" 2>/dev/null; do
            sleep 15
            ELAPSED=$((ELAPSED + 15))
            MINUTES=$((ELAPSED / 60))
            SECS=$((ELAPSED % 60))
            update_progress 95 "Collabora : téléchargement en cours... ${MINUTES}m${SECS}s ⏳"
            [[ "$ELAPSED" -gt 1800 ]] && { kill "$PULL_PID" 2>/dev/null; warn "Timeout pull Docker."; return; }
        done
        wait "$PULL_PID"
        [[ $? -ne 0 ]] && { warn "docker pull échoué — Collabora ignoré."; return; }
    fi
    log "✓ Image Collabora présente"

    # ── 3. coolwsd.xml ─────────────────────────────────────────────────────
    mkdir -p /opt/collabora
    local APP_WILDCARD
    APP_WILDCARD=$(echo "https://${DOMAIN}" | sed 's|://|://*.|')

    cat > /opt/collabora/coolwsd.xml << XML
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
          <host allow="true">https://${DOMAIN}</host>
          <alias>${APP_WILDCARD}</alias>
        </group>
      </alias_groups>
    </wopi>
  </storage>
</coolwsd>
XML
    log "✓ coolwsd.xml créé"

    # ── 4. Conteneur Docker ────────────────────────────────────────────────
    docker rm -f collabora >> "$LOG_FILE" 2>&1 || true

    docker run -d         --name collabora         --restart always         -p 127.0.0.1:9980:9980         -v /opt/collabora/coolwsd.xml:/etc/coolwsd/coolwsd.xml:ro         --cap-add MKNOD         collabora/code >> "$LOG_FILE" 2>&1         || { warn "docker run échoué — Collabora ignoré."; return; }

    # Attendre que Collabora soit prêt (max 60s)
    update_progress 96 "Collabora : démarrage en cours..."
    local READY=0
    for i in $(seq 1 12); do
        sleep 5
        if curl -sk http://127.0.0.1:9980/hosting/discovery 2>/dev/null | grep -q "wopi-discovery"; then
            READY=1; break
        fi
    done
    [[ "$READY" -eq 0 ]] && { warn "Collabora ne répond pas — blocs Nginx ajoutés quand même."; }
    log "✓ Conteneur Collabora démarré"

    # ── 5. Blocs Nginx proxy ───────────────────────────────────────────────
    local NGINX_CONF="/etc/nginx/sites-available/pladigit"
    if grep -q "location ^~ /browser" "$NGINX_CONF" 2>/dev/null; then
        log "Blocs Nginx Collabora déjà présents"
    else
        local TMPFILE
        TMPFILE=$(mktemp)
        local INSERTED=0
        while IFS= read -r line; do
            if [[ "$INSERTED" -eq 0 ]] && echo "$line" | grep -qF 'location ~ /\.(?!well-known)'; then
                cat >> "$TMPFILE" << 'NGINX_BLOCKS'
    # ── Collabora Online ─────────────────────────────────────────────────────
    location ^~ /browser {
        proxy_pass         http://127.0.0.1:9980;
        proxy_set_header   Host              $http_host;
        proxy_set_header   X-Forwarded-Proto https;
        proxy_read_timeout 600s;
    }

    location ^~ /hosting/discovery {
        proxy_pass       http://127.0.0.1:9980;
        proxy_set_header Host              $http_host;
        proxy_set_header X-Forwarded-Proto https;
    }

    location ^~ /hosting/capabilities {
        proxy_pass       http://127.0.0.1:9980;
        proxy_set_header Host              $http_host;
        proxy_set_header X-Forwarded-Proto https;
    }

    location ^~ /cool {
        proxy_pass             http://127.0.0.1:9980;
        proxy_http_version     1.1;
        proxy_set_header       Upgrade    $http_upgrade;
        proxy_set_header       Connection "Upgrade";
        proxy_set_header       Host       $http_host;
        proxy_set_header       X-Forwarded-Proto https;
        proxy_read_timeout     36000s;
        proxy_send_timeout     36000s;
        proxy_connect_timeout  36000s;
    }

NGINX_BLOCKS
                INSERTED=1
            fi
            echo "$line" >> "$TMPFILE"
        done < "$NGINX_CONF"

        if [[ "$INSERTED" -eq 1 ]]; then
            mv "$TMPFILE" "$NGINX_CONF"
            nginx -t >> "$LOG_FILE" 2>&1                 && systemctl reload nginx >> "$LOG_FILE" 2>&1                 && log "✓ Blocs Nginx Collabora injectés et rechargés"                 || warn "Nginx invalide après injection — vérifiez $NGINX_CONF"
        else
            rm -f "$TMPFILE"
            warn "Marqueur Nginx non trouvé — blocs Collabora non injectés."
        fi
    fi

    # ── 6. .env ────────────────────────────────────────────────────────────
    local ENV_FILE="${PLADIGIT_DIR}/.env"
    if [[ -f "$ENV_FILE" ]]; then
        grep -q "^COLLABORA_URL=" "$ENV_FILE"             && sed -i "s|^COLLABORA_URL=.*|COLLABORA_URL=https://${DOMAIN}|" "$ENV_FILE"             || echo "COLLABORA_URL=https://${DOMAIN}" >> "$ENV_FILE"
        grep -q "^COLLABORA_INTERNAL_URL=" "$ENV_FILE"             && sed -i "s|^COLLABORA_INTERNAL_URL=.*|COLLABORA_INTERNAL_URL=http://127.0.0.1:9980|" "$ENV_FILE"             || echo "COLLABORA_INTERNAL_URL=http://127.0.0.1:9980" >> "$ENV_FILE"
    fi
    sudo -u www-data php "${PLADIGIT_DIR}/artisan" config:cache >> "$LOG_FILE" 2>&1 || true
    sudo -u www-data php "${PLADIGIT_DIR}/artisan" cache:forget collabora.discovery_editor_path >> "$LOG_FILE" 2>&1 || true
    log "✓ .env Collabora configuré"

    # ── 7. Vérification finale ─────────────────────────────────────────────
    local ERRORS=0
    docker ps --filter "name=collabora" --filter "status=running" | grep -q collabora         && log "✓ Conteneur Collabora : actif"         || { log "✗ Conteneur Collabora : ARRÊTÉ"; ERRORS=$((ERRORS+1)); }

    curl -sk http://127.0.0.1:9980/hosting/discovery 2>/dev/null | grep -q "wopi-discovery"         && log "✓ Collabora discovery (interne) : OK"         || { log "✗ Collabora discovery interne : ÉCHEC"; ERRORS=$((ERRORS+1)); }

    # Discovery public : non bloquant (SSL peut ne pas être actif au moment de l'install)
    if curl -sk "https://${DOMAIN}/hosting/discovery" 2>/dev/null | grep -q "wopi-discovery"; then
        log "✓ Collabora discovery (public) : OK"
    else
        log "⚠ Collabora discovery public : non joignable — normal si SSL pas encore actif."
        log "  → Vérifiez après activation SSL : curl -sk https://${DOMAIN}/hosting/discovery | grep wopi-discovery"
    fi

    grep -q "location ^~ /browser" "$NGINX_CONF"         && log "✓ Blocs Nginx Collabora : présents"         || { log "✗ Blocs Nginx Collabora : ABSENTS"; ERRORS=$((ERRORS+1)); }

    if [[ "$ERRORS" -eq 0 ]]; then
        log "✅ Collabora Online installé et opérationnel"
        step_done "Collabora Online"
        update_progress 98 "Collabora Online ✅"
    else
        log "⚠ Collabora installé avec ${ERRORS} problème(s) — Pladigit fonctionne sans."
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
        elif ls /etc/ssl/certs/pladigit-selfsigned.crt > /dev/null 2>&1; then
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

    log "Installation terminée — ${install_url} (ssl: ${ssl_mode})"

    if [ -n "${DISPLAY:-}" ] || [ -n "${WAYLAND_DISPLAY:-}" ]; then
        xdg-open "${install_url}" 2>/dev/null &
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
    # ── Mode --collabora-only (appelé depuis le wizard PHP) ───────────────
    if [[ "${1:-}" == "--collabora-only" ]]; then
        APP_URL="${2:-}"
        PLADIGIT_DIR="${3:-/var/www/pladigit}"
        # Extraire le domaine depuis l'URL
        DOMAIN=$(echo "$APP_URL" | sed 's|https\?://||' | sed 's|/.*||')
        LOG_FILE="/var/log/pladigit-install.log"
        mkdir -p "$(dirname "$LOG_FILE")"
        log "Mode --collabora-only : DOMAIN=${DOMAIN}, DIR=${PLADIGIT_DIR}"
        install_collabora
        exit $?
    fi

    # Forcer stdin sur le terminal — indispensable via curl | bash
    exec < /dev/tty

    mkdir -p "$(dirname "$LOG_FILE")"
    echo "=== Pladigit Install Log v${INSTALL_VERSION} — $(date) ===" > "$LOG_FILE"

    # Vérifier que dialog est disponible (préinstallé sur Ubuntu)
    if ! command -v dialog &>/dev/null; then
        apt-get install -y -qq dialog >> "$LOG_FILE" 2>&1 || true
    fi

    # Vérifier les droits root en amont
    [[ $EUID -ne 0 ]] && { echo "Ce script doit être exécuté en tant que root (sudo)."; exit 1; }

    show_welcome

    # Installation existante détectée ? (suffit que .env existe — pas besoin du .lock)
    if [[ -f "${PLADIGIT_DIR}/.env" ]]; then
        do_update
    fi

    # Saisies interactives
    set +e
    choose_profil
    ask_domain
    ask_email
    ask_admin_ip
    show_recap

    # Installation
    check_prerequisites

    if [[ "$ALL_INSTALLED" == true ]] && [[ -d "${PLADIGIT_DIR}/.git" ]]; then
        start_progress
        install_pladigit
        configure_nginx
        set +e; setup_ssl; set -e
        set +e; install_collabora; set -e
        setup_cron
        setup_super_admin_ip
        update_progress 100 "Installation terminée !"
        stop_progress
        show_success
        return
    fi

    start_progress
    update_system
    install_php
    install_mysql
    install_services
    setup_logs
    install_pladigit
    configure_nginx
    set +e; setup_ssl; set -e
    set +e; install_collabora; set -e
    setup_cron
    setup_super_admin_ip
    update_progress 100 "Installation terminée !"
    stop_progress
    show_success
    rm -f "$DIALOGRC" 2>/dev/null || true
}

main "$@"
