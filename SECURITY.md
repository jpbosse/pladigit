# Politique de sécurité — Pladigit

## Versions supportées

| Version | Supportée |
|---------|-----------|
| 0.8.x (branche main) | ✅ Oui |
| Versions antérieures | ❌ Non |

Seule la dernière version publiée sur la branche `main` est maintenue. Les correctifs de sécurité ne sont pas rétroportés vers les versions antérieures.

---

## Signaler une vulnérabilité

**Ne pas ouvrir d'issue publique GitHub pour une vulnérabilité de sécurité.**

Envoyer un email à : **contact@pladigit.fr**

Inclure dans le message :
- La description précise de la vulnérabilité
- Les étapes pour reproduire le problème
- L'impact potentiel estimé (quelles données ou fonctionnalités sont concernées)
- La version de Pladigit affectée
- Si possible, une suggestion de correctif ou de contournement

**Délai de réponse :** accusé de réception sous 72 heures ouvrées.
**Divulgation responsable :** le correctif est publié avant toute divulgation publique. Le délai cible est de 30 jours à compter du signalement.

---

## Périmètre — ce qui est dans le champ de la politique

Sont dans le périmètre :
- Authentification et gestion des sessions (contournement, vol de session, fixation)
- Isolation multi-organisation : fuite de données entre organisations distinctes
- Injections SQL, XSS (Cross-Site Scripting), CSRF (Cross-Site Request Forgery)
- Accès non autorisé aux fichiers NAS ou GED
- Contournement des droits (GED, photothèque, projets, audit trail)
- Endpoints WOPI (protocole d'édition Collabora Online)
- Contournement de la restriction d'accès au Super Admin par adresse réseau
- Fuite d'informations sensibles via les journaux ou les messages d'erreur

Hors périmètre :
- Vulnérabilités dans les dépendances tierces (PHP, Laravel, Composer) — signaler directement à leurs mainteneurs
- Attaques nécessitant un accès physique au serveur
- Déni de service par volumétrie (saturation réseau ou disque)
- Attaques sociales ou d'ingénierie sociale ciblant les utilisateurs

---

## Mesures de sécurité en place

Pour information, les mesures suivantes sont implémentées dans Pladigit v0.8 :

- **Mots de passe** : hachage bcrypt coût 12, politique configurable (longueur, complexité, expiration, historique)
- **Double authentification** : TOTP (algorithme TOTP RFC 6238), secret chiffré AES-256 en base, codes de secours chiffrés
- **Annuaire** : connexion LDAP uniquement via LDAPS (chiffré) — connexion non chiffrée refusée
- **Isolation des données** : base MySQL dédiée par organisation — aucun mélange de données entre organisations
- **Audit trail** : journalisation de tous les accès et modifications, export CSV/JSON, rétention configurable
- **Protection réseau** : Fail2ban (bannissement d'adresses IP agressives), pare-feu UFW
- **Super Admin** : accès restreint par adresse réseau autorisée (ADR-027)
- **Dépendances** : audit Composer automatique sur chaque intégration continue — 0 vulnérabilité connue
- **Analyse statique** : PHPStan niveau 5 — 0 erreur de typage tolérée

---

## Transparence et audit

Pladigit est publié sous licence AGPL-3.0. Le code source complet est disponible sur [github.com/jpbosse/pladigit](https://github.com/jpbosse/pladigit). Toute organisation ou société mandatée peut auditer le code sans demander d'autorisation préalable.

Les décisions architecturales liées à la sécurité sont documentées dans les fiches ADR (Architecture Decision Records) numéros 004, 005, 017 et 027.
