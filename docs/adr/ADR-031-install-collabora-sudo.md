# ADR-031 — Script `install-collabora.sh` : installation Docker en root via sudoers

**Date :** Avril 2026  
**Statut :** Accepté  
**Auteur :** Jean-Pierre Bossé

---

## Contexte

Le wizard d'installation (`install/index.php`) s'exécute sous l'utilisateur `www-data` via PHP-FPM. Or l'installation de Docker et le téléchargement de l'image Collabora Online nécessitent les droits root (`apt-get install docker.io`, `systemctl`, `docker pull`).

Les tentatives d'exécution via `shell_exec('apt-get install docker.io')` depuis `www-data` échouent silencieusement — aucune erreur visible, aucune installation effectuée.

---

## Décision

Séparer l'installation Collabora dans un script dédié `install/install-collabora.sh` exécuté en root via une règle sudoers sans mot de passe.

### Architecture

```
wizard (www-data)
    └── popen("sudo install-collabora.sh ...")
            └── install-collabora.sh (root)
                    ├── apt-get install docker.io
                    ├── docker pull collabora/code
                    ├── docker run collabora/code
                    ├── configuration proxy Nginx
                    └── mise à jour .env
```

### Mise en place de la règle sudoers

`install.sh` (qui tourne en root) crée automatiquement :

```
/etc/sudoers.d/pladigit-collabora
www-data ALL=(root) NOPASSWD: /var/www/pladigit/install/install-collabora.sh
```

Le fichier `install-collabora.sh` appartient à `root:root` et est exécutable uniquement par root — `www-data` ne peut pas le modifier.

### Progression temps réel

Le script écrit sa progression dans le fichier log toutes les 10 secondes pendant le `docker pull` :

```
[10:23:45] Collabora : téléchargement en cours... 5m30s
[10:24:15] Collabora : téléchargement en cours... 6m0s (Pull complete)
```

Un timeout de 30 minutes est configuré pour éviter un blocage en cas de connexion lente.

### Paramètres

```bash
install-collabora.sh <log_file> <app_url> <root_dir>
```

### Étapes du script

1. `apt-get install docker.io`
2. `systemctl enable && start docker`
3. `docker pull collabora/code` (~1.5 Go)
4. `docker run collabora/code` avec `aliasgroup1=APP_URL`
5. Ajout du proxy Nginx (`/collabora/` → `localhost:9980`)
6. Mise à jour `COLLABORA_URL` dans `.env`

---

## Sécurité

- La règle sudoers est limitée au seul script `install-collabora.sh` — `www-data` ne peut pas exécuter d'autres commandes en root.
- Le script appartient à `root:root` — `www-data` ne peut pas le modifier.
- La règle sudoers est placée dans `/etc/sudoers.d/` et validée par `visudo -c`.

---

## Alternatives écartées

**`shell_exec` direct depuis www-data** — échoue silencieusement, pas de droits root.

**Séparation via un service systemd** — plus propre architecturalement mais complexité accrue pour un usage unique (installation).

**Installation Docker dans `install.sh`** — possible mais obligerait à connaître le choix Collabora avant l'exécution de `install.sh`, ce qui n'est pas le cas (le choix se fait dans le wizard).

---

## Conséquences

- L'installation Collabora est entièrement automatique depuis le wizard, sans intervention manuelle.
- Le script peut être relancé manuellement si l'installation a échoué :
  ```bash
  sudo /var/www/pladigit/install/install-collabora.sh /tmp/collab.log https://pladigit.fr /var/www/pladigit
  ```
- Le script reste présent après installation pour permettre une réinstallation de Collabora si nécessaire.
