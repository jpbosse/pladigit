# ADR-013 — Filigrane par GD natif (pas ImageMagick)

**Date :** Mars 2026
**Statut :** Accepté

## Contexte

Le module photothèque permet aux organisations d'apposer un filigrane (texte ou logo) sur les images téléchargées via les liens de partage public. Deux bibliothèques PHP de traitement d'image sont courantes : l'extension **GD** (intégrée à PHP) et **ImageMagick** (extension optionnelle).

## Décision

Utiliser uniquement l'extension **GD** via les fonctions natives PHP (`imagecreatefromstring()`, `imagerotate()`, `imagecrop()`, `imagettftext()`). GD est disponible dans toute installation PHP standard sans configuration supplémentaire.

Le watermarking (texte ou image PNG en filigrane), la rotation et le recadrage sont tous implémentés avec GD dans `WatermarkService` et `MediaItemController`.

## Alternatives écartées

- **ImageMagick / Imagick :** performances supérieures pour les formats complexes (TIFF, CMYK, très haute résolution), mais extension non installée par défaut. Ajouter une dépendance système sur les serveurs des collectivités clientes est une friction opérationnelle non acceptable.
- **Intervention Image (librairie) :** abstraction sur GD/Imagick, mais dépendance Composer supplémentaire sans bénéfice réel pour les formats supportés (JPEG, PNG, WebP).

## Conséquences

- Déploiement simplifié — aucune extension PHP supplémentaire requise.
- Limitation : les images TIFF très haute résolution (>100 MP) peuvent consommer beaucoup de mémoire avec GD. La limite de 200 Mo par fichier (`config/nas.php`) atténue ce risque.
- L'édition (rotation, recadrage) s'effectue directement sur le NAS via les drivers — le fichier original est remplacé et la miniature régénérée.
