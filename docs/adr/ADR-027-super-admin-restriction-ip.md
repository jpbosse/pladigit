# ADR-027 — Super Admin : restriction d'accès par IP

**Date :** Avril 2026  
**Statut :** Accepté — évolution prévue  
**Auteur :** Jean-Pierre Bossé

---

## Contexte

Le Super Admin de Pladigit est une interface critique — il permet de provisionner des organisations, accéder aux statistiques globales et gérer l'infrastructure multi-tenant. Un accès non autorisé au Super Admin compromettrait l'ensemble de la plateforme.

---

## Décision

Restreindre l'accès au Super Admin par liste blanche d'adresses IP, gérée dans le middleware `CheckSuperAdmin`.

```php
private array $allowedIps = [
    '127.0.0.1',
    '::1',
    'IP_ADMIN_1',
    'IP_ADMIN_2',
];
```

Toute requête provenant d'une IP non listée reçoit une réponse `403 Forbidden` — avant même la vérification du mot de passe.

---

## Raisons

**Défense en profondeur :** Même si le mot de passe Super Admin est compromis, l'attaquant ne peut pas accéder au Super Admin depuis une IP non autorisée.

**Surface d'attaque minimale :** Le Super Admin n'a pas besoin d'être accessible depuis l'internet entier. Seul l'administrateur système y accède, depuis un nombre limité d'adresses IP connues.

**Cohérence avec la philosophie Pladigit :** "Chaque choix technique doit pouvoir être justifié devant un élu, un DGS ou un audit."

---

## Limitations actuelles

La liste d'IPs est codée en dur dans le middleware — ce qui pose deux problèmes :

1. **Déploiement :** Chaque installateur doit modifier le code source pour ajouter son IP
2. **IP dynamique :** Les administrateurs avec une IP dynamique (FAI résidentiel) doivent mettre à jour le fichier à chaque changement

---

## Évolution prévue (Niveau 2)

Déplacer la liste d'IPs dans le `.env` :

```env
SUPER_ADMIN_ALLOWED_IPS=127.0.0.1,::1,82.67.203.161
```

Le middleware lirait cette valeur :

```php
private function getAllowedIps(): array
{
    $envIps = env('SUPER_ADMIN_ALLOWED_IPS', '127.0.0.1,::1');
    return array_map('trim', explode(',', $envIps));
}
```

**Avantages :**
- Aucune modification du code source lors de l'installation
- Documentable dans l'INSTALL.md
- Modifiable sans redéploiement (via `config:cache`)

---

## Procédure actuelle pour les installateurs

Lors de l'installation, ajouter votre IP publique dans `CheckSuperAdmin.php` :

```bash
nano /var/www/pladigit/app/Http/Middleware/CheckSuperAdmin.php
```

```php
private array $allowedIps = [
    '127.0.0.1',
    '::1',
    'VOTRE_IP_PUBLIQUE',  // récupérable via : curl ifconfig.me
];
```

Pour connaître votre IP publique :
```bash
curl ifconfig.me
```

---

## Alternatives rejetées

**Authentification HTTP Basic devant le Super Admin (Nginx)** — Double couche de protection mais complexifie la configuration Nginx et n'apporte pas de valeur significative si la restriction IP est en place.

**VPN obligatoire** — Plus sécurisé mais ajoute une dépendance infrastructure (WireGuard, OpenVPN) disproportionnée pour une petite collectivité.

**2FA uniquement** — Le 2FA est une bonne protection mais ne remplace pas la restriction IP — il protège l'authentification, pas l'accès à l'endpoint.

---

## Conséquences

- L'INSTALL.md documente la modification de `CheckSuperAdmin.php` comme étape obligatoire
- L'évolution vers `SUPER_ADMIN_ALLOWED_IPS` est planifiée pour le niveau 2
- Le piège "403 après login réussi" (session Super Admin perdue à cause de la restriction IP) est documenté dans l'INSTALL.md

---

*Pladigit — ADR-027 — Avril 2026*
