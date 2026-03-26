# Annexe M — Plan de Reprise d'Activité (PRA)

## M.1 — Objectifs et indicateurs

## M.2 — Architecture de sauvegarde
- Dump SQL quotidien complet : base centrale pladigit + toutes les bases organisation.
- Fichiers de configuration Laravel (.env, config/*.php).
- Clés de chiffrement et certificats SSL dans un coffre-fort (Vaultwarden, auto-hébergé).
- Sauvegarde locale sur le VPS (/backups, rotation 30 jours) + transfert chiffré GPG vers stockage distant (S3-compatible ou NAS distant via rclone).

## M.3 — Procédures de reprise
### Scénario 1 — Panne serveur (redémarrage)
systemctl restart nginx php8.4-fpm mysql redis soketi
Vérifier les logs (/var/log/nginx, storage/logs/laravel.log). Tester la connexion web.
Durée estimée : 15–30 minutes.

### Scénario 2 — Corruption ou perte de base de données
Arrêter nginx pour bloquer les écritures. Identifier la dernière sauvegarde valide.
Restaurer : gunzip < backup_org.sql.gz | mysql -u root -p pladigit_org
Vérifier l'intégrité via l'interface admin. Relancer nginx. Notifier les utilisateurs.
Durée estimée : 1–2 heures.

### Scénario 3 — Compromission sécurité / intrusion
- Isoler immédiatement le serveur (couper le réseau via pare-feu hébergeur).
- Analyser les logs (audit_logs, /var/log/auth.log, nginx access.log).
- Révoquer tous les tokens et sessions : TRUNCATE sessions en base de données.
- Forcer la réinitialisation des mots de passe de tous les comptes locaux.
- Corriger la vulnérabilité identifiée AVANT toute remise en ligne.
- Notifier les organisations concernées (obligation RGPD si données exfiltrées, 72h CNIL).
Durée estimée : 4 heures à plusieurs jours selon gravité.

## M.4 — Surveillance et tests
- Uptime monitoring : Statping (open source) avec alertes SMS/e-mail toutes les 5 minutes.
- Health check natif : GET /health → {status, checks:{database,redis,disk}}.
- Alertes : disque > 80 %, certificat SSL expirant < 30 jours, erreurs 5xx répétées.
- Test PRA semestriel : simulation de restauration sur environnement staging.