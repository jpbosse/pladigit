# ADR-032 — Rotation des clés AES : hors périmètre

**Date :** Mai 2026  
**Statut :** Accepté  
**Auteur :** Jean-Pierre Bossé

---

## Contexte

Pladigit utilise AES-256 (via `APP_KEY` Laravel) pour chiffrer deux types de données sensibles en base :

- Les codes de secours TOTP (double authentification)
- Les mots de passe LDAP stockés pour la synchronisation

La rotation des clés AES est une bonne pratique de sécurité qui consiste à remplacer périodiquement la clé de chiffrement, en déchiffrant toutes les valeurs existantes avec l'ancienne clé puis en les rechiffrant avec la nouvelle.

---

## Décision

**La rotation automatique des clés AES n'est pas implémentée dans Pladigit.**

---

## Raisons

### Public cible inadapté à la complexité opérationnelle

Les collectivités cibles (moins de 20 000 habitants) ne disposent pas d'équipe informatique dédiée. Une rotation de clé implique :

- Une procédure planifiée (quand ? à quelle fréquence ?)
- Un risque de perte de données en cas d'erreur (déchiffrement partiel, interruption)
- Un rollback complexe si la migration échoue en cours de route
- Une fenêtre de maintenance avec indisponibilité partielle

Ce niveau de complexité opérationnelle n'est pas justifiable pour le public cible.

### Protections compensatoires suffisantes

La clé AES (`APP_KEY`) est protégée par plusieurs couches :

- Stockée dans `.env`, hors webroot, non versionnée dans Git
- Accès SSH par clé uniquement, root désactivé
- UFW — seuls les ports 22, 80 et 443 sont ouverts
- Fail2ban actif sur SSH et les endpoints d'authentification
- MySQL non accessible depuis l'extérieur

Si un attaquant accède au `.env`, la compromission de la clé AES est le cadet des problèmes — l'ensemble de l'infrastructure est compromis.

### Rapport effort / bénéfice défavorable

La surface d'attaque réelle ciblée par la rotation AES (exfiltration de la base sans accès au `.env`) est quasi nulle dans ce contexte d'hébergement VPS mono-instance avec les protections en place.

---

## Conséquences

- Aucun code de rotation n'est développé ni planifié.
- La gestion de l'`APP_KEY` reste manuelle : générée à l'installation, sauvegardée hors serveur par l'administrateur, remplacée uniquement en cas de compromission avérée.
- Cette décision est révisable si le projet évolue vers un hébergement mutualisé avec des dizaines d'organisations sur une même instance, ou si une collectivité partenaire dispose d'une équipe technique capable de gérer la procédure.

---

## Alternatives écartées

| Alternative | Raison du rejet |
|-------------|-----------------|
| Rotation manuelle annuelle avec script | Trop de risques d'erreur sans supervision technique |
| Rotation automatique via artisan command | Complexité de la gestion des erreurs et du rollback |
| Chiffrement par clé dérivée par organisation | Complexité architecturale incompatible avec le public cible |
