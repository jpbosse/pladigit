# ADR-035 — Audit cross-tenant : hors périmètre

**Date :** Mai 2026  
**Statut :** Accepté  
**Auteur :** Jean-Pierre Bossé

---

## Contexte

L'architecture multi-tenant de Pladigit repose sur une base de données MySQL dédiée par organisation. Le middleware `ResolveTenant` reconfigure dynamiquement la connexion `tenant` à chaque requête en fonction du sous-domaine, avant tout traitement applicatif.

La question posée : faut-il implémenter un audit cross-tenant — c'est-à-dire détecter et logguer les tentatives d'accès d'un tenant aux données d'un autre ?

---

## Décision

**L'audit cross-tenant n'est pas implémenté dans Pladigit.**

---

## Raisons

### Isolation garantie architecturalement

L'isolation entre tenants est assurée par la séparation physique des bases de données, pas par du code applicatif. Un tenant ne peut pas accéder à la base d'un autre tenant indépendamment de toute vérification logicielle — la connexion MySQL est reconfigurée au niveau du middleware avant que la moindre requête SQL ne soit exécutée.

Un audit cross-tenant ne renforcerait pas cette isolation — il se contenterait de l'observer.

### Public cible inadapté à la supervision des alertes

Un audit cross-tenant produit des logs d'anomalies qui n'ont de valeur que si quelqu'un les lit et agit en conséquence. Les collectivités cibles (moins de 20 000 habitants) ne disposent pas d'équipe de sécurité pour traiter ces alertes. Des logs non lus donnent une fausse impression de sécurité.

### Rapport effort / bénéfice défavorable

Implémenter une détection d'anomalies fiable (sans faux positifs) sur les tentatives cross-tenant demande un effort significatif pour un bénéfice nul dans le contexte d'hébergement mono-instance avec quelques dizaines d'organisations au maximum.

---

## Protections en place

L'isolation tenant repose sur les éléments suivants, qui sont suffisants :

- **Base de données dédiée par organisation** — séparation physique des données
- **`ResolveTenant` middleware** — reconfiguration de la connexion avant tout traitement, prepend au pipeline (première exécution garantie)
- **`TenantManager` singleton** — point d'accès unique à l'organisation courante, pas d'accès direct possible
- **Audit trail applicatif** — toutes les actions utilisateur sont loggées dans la base tenant concernée
- **Fail2ban + UFW** — protection réseau en amont

---

## Conséquences

- Aucun code de détection cross-tenant n'est développé ni planifié.
- Cette décision est révisable si le projet évolue vers un hébergement mutualisé à grande échelle avec une équipe technique capable de traiter les alertes.

---

## Alternatives écartées

| Alternative | Raison du rejet |
|-------------|-----------------|
| Log des tentatives de résolution de tenant inconnue | Faux positifs fréquents (bots, scanners), pas d'équipe pour traiter |
| Alerte email Super Admin sur anomalie | Complexité, risque de spam, pas de valeur sans procédure de réponse |
| Audit tiers (SIEM) | Hors budget et hors compétence du public cible |
