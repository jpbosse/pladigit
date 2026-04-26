# Politique de sécurité — Pladigit

## Versions supportées

| Version | Supportée |
|---------|-----------|
| 0.8.x (branche main) | ✅ Oui |
| Versions antérieures | ❌ Non |

## Signaler une vulnérabilité

**Ne pas ouvrir d'issue publique GitHub pour une vulnérabilité de sécurité.**

Envoyer un email à : **contact@pladigit.fr**

Inclure :
- La description de la vulnérabilité
- Les étapes pour reproduire
- L'impact potentiel estimé
- Si possible, une suggestion de correctif

**Délai de réponse :** sous 72 heures pour un accusé de réception.  
**Divulgation responsable :** correctif publié avant toute divulgation publique.

## Périmètre

Sont dans le périmètre :
- Authentification et gestion des sessions
- Isolation multi-tenant (fuite de données entre organisations)
- Injection SQL, XSS, CSRF
- Accès non autorisé aux fichiers NAS
- Contournement des droits (GED, photothèque, projets)
- Endpoints WOPI

Hors périmètre :
- Vulnérabilités dans les dépendances tierces (signaler directement à leurs mainteneurs)
- Attaques nécessitant un accès physique au serveur
- Déni de service par volumétrie
