# ADR-009 — Streaming vidéo par Range HTTP (RFC 7233)

**Date :** Mars 2026
**Statut :** Accepté

## Contexte

Les vidéos (MP4, MOV, AVI, MKV) peuvent atteindre 200 Mo. Les servir via `response()->file()` ou `readfile()` chargerait l'intégralité du fichier en mémoire PHP et empêcherait le scrubbing dans le lecteur `<video>` HTML5 (qui envoie des requêtes `Range` pour sauter à une position arbitraire dans la vidéo).

## Décision

La méthode `MediaItemController::stream()` implémente le protocole Range HTTP (RFC 7233) :

- Parse l'en-tête `Range: bytes=X-Y` de la requête.
- Envoie une réponse `206 Partial Content` avec `Content-Range` si une plage est demandée, sinon `200 OK`.
- Lit le fichier par chunks de 1 Mo via `NasConnectorInterface::readChunk()` — jamais entièrement en mémoire.
- Détecte `connection_aborted()` pour arrêter le streaming si le client ferme la connexion.

Les images dépassant le seuil configurable `media_stream_threshold_mb` (défaut 10 Mo) passent également par cette route pour éviter les pics mémoire.

## Conséquences

- **Avantage :** scrubbing fluide dans le navigateur, empreinte mémoire PHP constante quelle que soit la taille du fichier.
- **Contrainte :** les trois drivers NAS (`local`, `sftp`, `smb`) doivent implémenter `openReadStream()`, `readChunk()` et `closeReadStream()`.
- Les réponses streamées ne peuvent pas avoir de `Cache-Control: public` — `no-store` est utilisé pour les vidéos afin d'éviter que des proxies ne mettent en cache des plages partielles incomplètes.
