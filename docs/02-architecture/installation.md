# Module d'installation Pladigit — Architecture & Cahier des charges

> Statut du document : **vivant**. Décrit ce que l'installeur *fait réellement* aujourd'hui,
> les décisions de conception qui ne doivent pas être remises en cause sans raison,
> et ce qui reste à construire (clairement marqué « cible »).
>
> Règle d'or de ce document : **il ne décrit jamais un idéal comme s'il existait.**
> Une doc qui ment au lecteur futur est pire qu'une absence de doc.

---

## 1. Pourquoi ce document existe

L'installation de Pladigit est devenue un composant produit à part entière. Deux fichiers
en portent l'essentiel :

- `install.sh` — phase **système** (paquets, PHP, MySQL, Redis, Nginx, SSL, worker, cron),
  exécutée en terminal, en root.
- `install/index.php` — phase **applicative** (le wizard navigateur), qui collecte la
  configuration et lance l'installation Laravel en arrière-plan.

Pendant une période, chaque correction dans cette zone en déclenchait une autre ailleurs.
La cause n'était pas la qualité du code mais un **couplage transitif** : un seul mécanisme
(la régénération d'un runner exécutable à partir d'un état dédoublé) reliait silencieusement
config → .env → script → Laravel. Ce document fige l'architecture qui supprime ce couplage.

---

## 2. Principe fondateur : une seule source de vérité

> **`config.json` est l'unique source de vérité de la configuration d'installation.**
> Tout le reste en découle ou n'est que transitoire.

### Ce qui est une source de vérité

| Élément        | Rôle                                              | Persistant ? |
| -------------- | ------------------------------------------------- | ------------ |
| `config.json`  | Toute la configuration choisie dans le wizard     | **Oui**      |

### Ce qui n'en est PAS une (et ne doit jamais le devenir)

| Élément             | Rôle réel                                                        |
| ------------------- | --------------------------------------------------------------- |
| `$_SESSION`         | État d'IHM transitoire **uniquement** : `step`, `errors`        |
| `install.log`       | Journal de sortie (lecture humaine + polling navigateur)        |
| `done` / `fail`     | Signaux IPC entre le runner en arrière-plan et le navigateur    |
| `dialog` (install.sh) | Saisie terminal de la phase système, hors périmètre du wizard |

**Conséquence directe :** `$_SESSION` ne stocke plus aucune donnée de configuration
(`db`, `app`, `smtp`, `admin`, `collabora`, `security`). Chaque page du wizard écrit
directement dans `config.json` via `save_config()`, et la session ne retient que la
navigation (`step`) et les erreurs à réafficher (`errors`).

---

## 3. Le contrat `config.json`

Le wizard remplit `config.json` étape par étape. C'est **le** contrat entre la phase
de collecte (les pages) et la phase d'exécution (le runner). Aucune autre structure
ne doit dupliquer ces données.

```jsonc
{
  "db": {
    "host":          "127.0.0.1",
    "port":          "3306",
    "name":          "pladigit",
    "root_user":     "root",
    "root_password": "…",        // secret — jamais journalisé
    "app_user":      "pladigit",
    "app_password":  "…"         // secret — min. 8 caractères
  },
  "app": {
    "mode":     "domain",        // "domain" | "ip"
    "domain":   "pladigit.macommune.fr", // en mode "ip", contient l'IP (pas de clé "ip" séparée)
    "url":      "https://pladigit.macommune.fr", // http:// en mode "ip"
    "name":     "Pladigit",
    "timezone": "Europe/Paris"
  },
  "smtp": {
    "host":       "ssl0.ovh.net",
    "port":       "587",
    "username":   "…",
    "password":   "…",           // secret
    "from":       "no-reply@macommune.fr",
    "from_name":  "Pladigit",
    "encryption": "tls"          // valeur de MAIL_SCHEME
  },
  "admin": {
    "name":     "…",
    "email":    "…",
    "password": "…"              // secret — min. 12 car. ; haché (bcrypt) au runner. "password_confirm" n'est pas stocké
  },
  "collabora": {
    "mode": "skip",              // "skip" | "local" | "external"
    "url":  ""                   // requis si mode = "external"
  },
  "security": {
    "gpg_passphrase": "…"        // secret — transmis en présentiel uniquement
  },
  "install": {
    "ssl_mode": "letsencrypt",   // "letsencrypt" | "selfsigned" | "none"
    "domain":   "pladigit.macommune.fr"
  }
}
```

> **Règle de sécurité.** Les valeurs marquées « secret » ne doivent jamais apparaître
> dans `install.log` ni dans une URL. `config.json` est protégé en `0600`, propriété
> `www-data`. La passphrase GPG ne transite jamais par e-mail ni SMS (cf. principe
> « secret en présentiel »).

### 3.1 Correspondance `config.json` → `.env` (via `build_env`)

Le `.env` est **dérivé** de `config.json`. Aucune valeur n'y est saisie indépendamment.

| Clé `.env`                  | Source `config.json`        | Note                                            |
| --------------------------- | --------------------------- | ----------------------------------------------- |
| `APP_NAME`                  | `app.name`                  |                                                 |
| `APP_URL`                   | `app.url`                   |                                                 |
| `APP_TIMEZONE`              | `app.timezone`              |                                                 |
| `DB_HOST/PORT/DATABASE`     | `db.host/port/name`         |                                                 |
| `DB_USERNAME`               | `db.app_user`               |                                                 |
| `DB_PASSWORD`               | `db.app_password`           | secret                                          |
| `TENANT_DB_*`               | mêmes valeurs `db.*`        | réutilise `app_user` / `app_password`           |
| `MAIL_SCHEME`               | `smtp.encryption`           |                                                 |
| `MAIL_HOST/PORT/USERNAME`   | `smtp.host/port/username`   |                                                 |
| `MAIL_PASSWORD`             | `smtp.password`             | secret                                          |
| `MAIL_FROM_ADDRESS`         | `smtp.from`                 |                                                 |
| `MAIL_FROM_NAME`            | `smtp.from_name`            |                                                 |
| `SUPER_ADMIN_EMAIL`         | `admin.email`               |                                                 |
| `SUPER_ADMIN_PASSWORD_HASH` | `admin.password`            | **haché bcrypt au runner**, jamais en clair     |
| `OFFICE_DRIVER` + `COLLABORA_*` | `collabora.*`           | calculé par `office_extra_env` (voir §6)        |
| `SESSION_SECURE_COOKIE`     | dérivé de `install.ssl_mode`| `true` si SSL, sinon `false`                    |
| `SESSION_DOMAIN`            | `app.domain`                | uniquement en mode `domain`                     |

**Valeurs non issues de `config.json` :**

- `APP_KEY` : généré aléatoirement par le runner à chaque installation.
- `SUPER_ADMIN_ALLOWED_IPS` : capturé depuis l'IP du navigateur (`REMOTE_ADDR`) au
  moment de `api_run`. ⚠️ **Piège** : si le wizard est lancé depuis une IP différente
  de celle d'administration, l'administrateur est verrouillé dehors. À documenter pour
  l'opérateur, et à reconsidérer lors du refactor.
- Constantes de production : `APP_ENV=production`, `APP_DEBUG=false`, `BCRYPT_ROUNDS=12`,
  pile Redis (`CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER`), `LOG_LEVEL=error`.

> **Dette repérée (cible refactor, pas dictionnaire).** L'échappement des valeurs dans
> `build_env` est **incohérent** : `APP_NAME`/`MAIL_FROM_NAME` via `addslashes`,
> `DB_PASSWORD`/`MAIL_PASSWORD` via `str_replace('"', '\"')`. À unifier quand `build_env`
> deviendra l'écriture d'un `.env` propre depuis le runner fixe.

> **Bonne propriété à préserver.** `db.root_password` n'est **jamais** écrit dans `.env` :
> il n'est consommé que transitoirement par le runner (connexion PDO pour créer base +
> utilisateur applicatif). Le refactor doit conserver cette séparation.

---

## 4. Le runner : fichier fixe, jamais généré

> **Décision figée.** `install/runner.php` est un **fichier versionné, fixe**.
> Il n'est plus généré dynamiquement. Il lit `config.json` à son démarrage.

### Pourquoi (et pourquoi pas l'inverse)

La génération à la volée du runner (valeurs cuites par `addslashes` dans un heredoc)
posait trois problèmes structurels :

1. **Intestable** — un fichier qui n'existe qu'à l'exécution échappe à Pint, PHPStan,
   PHPUnit. On ne peut pas le garder vert.
2. **Fragile** — des dizaines de valeurs échappées manuellement ; un caractère
   inattendu dans un mot de passe casse le PHP produit.
3. **Secrets dupliqués** — le mot de passe root MySQL se retrouvait en clair, en
   littéral, dans un `.php` généré, en plus du `.env`.

Un runner fixe qui lit `config.json` règle les trois et, surtout, **supprime le
couplage transitif** : modifier une clé de configuration ne réécrit plus jamais de
code exécutable.

### Flux cible

```
Navigateur (wizard)                Arrière-plan
───────────────────                ────────────
page → save_config() ─┐
                       ├─► config.json ◄──── runner.php (lit, n'écrit pas la config)
api_run() ────────────┘                          │
   └─ lance: php runner.php > install.log 2>&1 &  │
                                                   ▼
                            preflight → MySQL → .env → migrations → cache → lock
                                                   │
              done / fail  ◄─────────────────────┘  (signaux IPC)
                   ▲
   api_status() ───┘  (le navigateur interroge la progression)
```

`runner.php` :

- lit `config.json` (unique entrée),
- écrit `.env` à partir de cette configuration (`build_env`),
- exécute les étapes (preflight, création base/utilisateur MySQL, migrations,
  caches, storage:link, provisionnement des modules actifs),
- signale sa progression via `install.log` et son issue via `done` / `fail`,
- ne renvoie **jamais** d'état dans `config.json` (lecture seule de la config).

---

## 5. État actuel (ce qui fonctionne aujourd'hui)

### Phase système (`install.sh`)

`main()` force `PROFIL=1` (commune / public unique ; le multi-organisations est géré
ensuite par le wizard) puis enchaîne, de façon idempotente :

```
ask_domain → ask_email → ask_admin_ip → recap
check_prerequisites → [install paquets/PHP/MySQL/services] → install_pladigit
setup_worker → setup_ssl → setup_cron → write_wizard_config → check_install_ok → success
```

Points acquis :

- **SSL wildcard OVH** (`*.pladigit.fr`) via `certbot-dns-ovh`.
- **Spécificités Ubuntu 26.04** prises en compte (PHP natif récent, Apache2
  pré-installé, MySQL root en `auth_socket`).
- **Rendu Nginx central déterministe** (`render_nginx`) : la config Nginx est
  recalculée à partir de l'état `{ SSL, modules actifs }`, en-têtes de sécurité
  inclus (CSP, HSTS en mode Let's Encrypt, X-Frame-Options, etc.).

### Phase applicative (`install/index.php`)

Wizard en étapes : bienvenue → vérifs → base → app → SMTP → (Collabora) → admin →
sécurité → installation → succès. Validation par page (`validate_database`,
`validate_app`, `validate_admin`). Verrou final (`LOCK_FILE`) qui ferme le wizard
après succès. API AJAX `api_log` / `api_status` / `api_run` pour la progression.

> ⚠️ **Bug connu** : le bouton « Accéder à Pladigit » de la page de succès peut être
> cliqué avant la fin du runner (HTTP 500). À traiter : ne l'activer que sur réception
> du signal `done`.

---

## 6. Modèle de modules (Collabora et au-delà)

> **Décision figée.** Un module est **opt-in**. Le flux d'installation standard
> n'installe **aucun** module par défaut.

Collabora n'est pas dans `main()`. Il s'active explicitement :

```bash
sudo /var/www/pladigit/install.sh --collabora-only
# ou, générique :
sudo /var/www/pladigit/install.sh --add-module collabora
```

Un module est déclaré par un manifeste `install/modules/<id>/module.json` :

- `id`, `label`, `description`
- `capability` : la capacité fournie (ex. `office`) et la variable `.env` pilote
  (ex. `OFFICE_DRIVER`)
- `provision` : script autonome et idempotent (ex. `provision.sh` — Docker + config)
- `preflight` : pré-requis vérifiables (ex. espace disque)
- `env` : variables `.env` à poser (avec substitution `{{DOMAIN}}`)
- `nginx` : gabarit de bloc Nginx fusionné par `render_nginx`
- `post_install` : actions Laravel post-activation (ex. invalidation de cache)

Ce modèle est **la voie d'extension** pour les services futurs (cf. §7).

---

## 7. Cible — pas encore construit

Cette section décrit l'intention, **non l'existant**. À ne pas confondre avec le code
actuel.

- **Catalogue multi-modules Docker** via le même mécanisme `module.json` :
  EuroOffice, OCR, signature, IA documentaire, connecteurs (LDAP, FranceConnect…).
- **GED multi-pilotes** : détecter les éditeurs réellement actifs et proposer
  « Ouvrir dans… » selon `OFFICE_DRIVER` (choix par organisation si plusieurs pilotes).
- **Niveaux de souveraineté** (standard / européen / souverain / personnalisé)
  influençant le catalogue de modules proposé.
- **Sélection de modules dans le wizard** (aujourd'hui : Collabora uniquement, en CLI).

Chaque élément de cette liste ne rejoint le §5/§6 qu'une fois réellement implémenté
et testé.

---

## 8. Travaux restants pour atteindre l'architecture cible

Ordre recommandé, du plus sûr au plus structurant :

1. ~~**Dictionnaire complet de `config.json`**~~ → **fait** (§3).
2. ~~**Dictionnaire complet du `.env` généré**~~ → **fait** (§3.1).
3. **Suppression de la redondance session ↔ config** — retirer de `$_SESSION` toute
   donnée de configuration ; ne garder que `step` et `errors`. Retirer les
   `?? $_SESSION[...]` de `write_runner()`. *(prochaine étape de code recommandée)*
4. **Extraction du runner** — transformer la génération en `install/runner.php` fixe,
   versionné, qui lit `config.json`. Passe sous Pint + PHPStan + (idéalement) un test.
5. **Inventaire des appels système** — lister tous les `shell_exec`/`exec`/`system`,
   confirmer que chacun est justifié et que ses erreurs ne sont pas avalées.
6. **Activation du bouton de succès sur signal `done`** (bug §5).

---

## 9. Scénarios de non-régression

L'installeur doit être validé sur la matrice suivante. Chaque ligne est un parcours
complet, vierge à chaque fois (réinitialiser HSTS du navigateur entre deux essais —
Firefox en navigation privée est le plus fiable).

| Dimension        | Variantes                          |
| ---------------- | ---------------------------------- |
| Installation     | Vierge / Réinstallation            |
| Accès            | Domaine / Adresse IP               |
| HTTPS            | Let's Encrypt / Auto-signé / Aucun |
| Base de données  | Vierge / Existante                 |
| Module Office    | Aucun / Collabora local / Externe  |

Critères de réussite (tous requis) : prérequis validés, `config.json` cohérent,
migrations passées, administrateur capable de se connecter, plateforme opérationnelle.

---

## 10. Invariants de travail (rappel)

- Sur le VPS, commandes artisan/composer en `sudo -u www-data`.
- Téléchargements dans `/tmp/` uniquement ; travail dans `/var/www/pladigit/`.
- Le VPS **tire** depuis GitHub, ne pousse jamais.
- Développement sur `develop` → PR → `main`. Jamais de commit direct sur `main`.
- Secrets (root MySQL, passphrase GPG) : jamais dans les logs, jamais dans une URL,
  jamais par e-mail/SMS.
