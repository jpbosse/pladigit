PLADIGIT — Synthèse Session du 14 Mars 2026
Photothèque NAS — Phase 3

# 1. Corrections appliquées

## Synchronisation NAS

## Suppression physique

## NasManager — correction critique
- Colonnes en base : nas_photo_driver, nas_photo_local_path, nas_photo_host, etc.
- NasManager réaligné sur ces noms — l'upload fonctionnait mais écrivait au mauvais endroit
- Le chemin dupliqué (nas_simulation/var/www/...) venait de resolve() mal géré sur chemins absolus

## Interface

# 2. Décisions architecturales

## Modèle de stockage unifié
- Tous les fichiers restent sur le NAS — pas de duplication
- Pas de distinction libre/protégé au niveau stockage
- La 'protection' = permissions d'album dans Pladigit (can_view, can_download...)
- L'accès physique au NAS = responsabilité de l'admin réseau, hors périmètre Pladigit

## Création d'album
- Nom saisi → slug généré → dossier NAS créé automatiquement
- Sous-album → dossier imbriqué dans le parent (evenements/fete-2025)
- Upload via interface → fichier écrit dans nas_path de l'album

## Permissions par rôle — logique corrigée
- Convention : level() plus bas = rôle plus privilégié (admin=1, user=6)
- Pivot resp_service (5) → accessible à resp_service et SUPÉRIEURS (resp_direction, DGS...)
- Formule : s'applique si userLevel <= pivotLevel
- Admin/Président/DGS ont toujours accès total — sauf albums privés (créateur uniquement)

# 3. Prochaines étapes


# 4. Intégration IA — décisions

- Solution retenue : Ollama (opensource, auto-hébergé) + LLaVA 7B (vision) + Mistral 7B (texte)
- Modèles sous licence Apache 2.0 — 100% gratuits, zéro donnée externe
- Config dev : AMD 3800X / 32 Go RAM / GTX 1070 (8 Go VRAM) — suffisant pour LLaVA 7B
- Config prod recommandée : VPS dédié Ollama 16 vCPU / 32 Go RAM (séparé du VPS Pladigit)
- Usage prévu : tagging automatique à la sync NAS, résumé documents GED (Phase 5)

# 5. Fichiers modifiés cette session
