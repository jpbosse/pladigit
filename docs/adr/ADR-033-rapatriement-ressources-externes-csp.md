# ADR-033 — Rapatriement des ressources externes et mise en place de la CSP

**Date :** Mai 2026  
**Statut :** Accepté  
**Auteur :** Jean-Pierre Bossé

---

## Contexte

L'audit du code front-end révèle trois dépendances externes chargées depuis des CDN tiers :

| Ressource | Domaine | Vues concernées |
|-----------|---------|-----------------|
| Google Fonts (Sora, DM Sans, Libre Baskerville, Source Sans 3, JetBrains Mono) | `fonts.googleapis.com` / `fonts.gstatic.com` | Tous les layouts, welcome, health |
| Trix 2.0.8 (éditeur rich text) | `cdn.jsdelivr.net` | `projects/create`, `projects/edit`, `projects/show` |
| Cropper.js 1.6.2 (recadrage photos) | `cdnjs.cloudflare.com` | `media/albums/show` |

Ces dépendances bloquent la mise en place d'une Content Security Policy (CSP) stricte et créent trois problèmes :

### Problème 1 — RGPD et souveraineté

Chaque chargement de page provoque une requête vers `fonts.googleapis.com`. Google enregistre l'adresse IP du navigateur, l'horodatage et le user-agent de l'agent de la collectivité. C'est une fuite de données incompatible avec le positionnement souverain de Pladigit et contraire aux recommandations de la CNIL sur les polices Google (délibération 2022).

### Problème 2 — Disponibilité

Une indisponibilité ou lenteur de jsDelivr, Cloudflare ou Google dégrade l'interface utilisateur. Pour une collectivité en zone rurale avec une connexion limitée, l'impact est visible.

### Problème 3 — CSP impossible sans domaines externes

Une CSP efficace contre le XSS ne peut pas autoriser `*.googleapis.com`, `*.jsdelivr.net` et `*.cloudflare.com` sans ouvrir des vecteurs d'injection larges. Rapatrier les ressources est un prérequis à une CSP défendable.

---

## Décision

**Rapatrier l'intégralité des ressources externes en local**, sans exception.

### Polices Google

Téléchargement via `google-webfonts-helper` (outil open source), stockage dans `public/fonts/`, déclaration `@font-face` dans le CSS Vite. Les balises `<link rel="preconnect">` et `<link href="fonts.googleapis.com">` sont supprimées de tous les layouts.

### Trix

Installation via npm (`npm install trix`), import dans le bundle Vite. Les balises `<link>` et `<script>` CDN sont supprimées des vues projets.

### Cropper.js

Installation via npm (`npm install cropperjs`), import dans le bundle Vite. La balise CDN est supprimée de la vue photothèque.

---

## CSP résultante

Une fois les ressources rapatriées, la CSP Nginx peut être stricte :

```nginx
add_header Content-Security-Policy "
  default-src 'self';
  script-src  'self' 'nonce-{NONCE}';
  style-src   'self' 'unsafe-inline';
  img-src     'self' data: blob:;
  font-src    'self';
  connect-src 'self';
  frame-src   'self' {COLLABORA_URL};
  object-src  'none';
  base-uri    'self';
" always;
```

Notes :
- `'unsafe-inline'` sur `style-src` est conservé temporairement — Livewire et Alpine.js injectent des styles inline. Une migration vers des nonces est possible ultérieurement mais non prioritaire.
- `frame-src` autorise l'URL Collabora Online configurée dans `.env`.
- `object-src 'none'` bloque les plugins Flash et équivalents.
- `base-uri 'self'` empêche l'injection de balise `<base>`.

---

## Headers HTTP complémentaires

Ajoutés dans le même bloc Nginx :

| Header | Valeur | Protection |
|--------|--------|------------|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Force HTTPS, anti-downgrade |
| `X-Frame-Options` | `SAMEORIGIN` | Anti-clickjacking |
| `X-Content-Type-Options` | `nosniff` | Anti-MIME sniffing |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Limite la fuite d'URL |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Désactive les API navigateur inutiles |

---

## Conséquences

- Aucune requête externe au chargement des pages Pladigit.
- Interface fonctionnelle hors connexion (hors Collabora Online).
- CSP défendable face à un audit ANSSI ou une question préfectorale.
- La checklist de mise en production (section 5 — Sécurité) est mise à jour en conséquence.
- L'`install.sh` est modifié pour inclure les headers dans le vhost Nginx généré.

---

## Alternatives écartées

| Alternative | Raison du rejet |
|-------------|-----------------|
| CSP permissive autorisant les CDN | Ouvre des vecteurs XSS, contraire à l'objectif souveraineté |
| Sous-ressource Integrity (SRI) uniquement | Protège l'intégrité mais pas la vie privée RGPD |
| Proxy local des CDN | Complexité inutile — npm et webfonts-helper suffisent |
