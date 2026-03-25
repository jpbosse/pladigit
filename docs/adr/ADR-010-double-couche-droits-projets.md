# ADR-010 — Double couche de droits sur les projets

**Date :** Décembre 2025
**Statut :** Accepté

## Contexte

Un modèle de droits à une seule dimension (rôle global) ne suffit pas : un Agent peut être Manager d'un projet tout en n'ayant aucun droit d'administration global. Inversement, un Resp. Service doit pouvoir consulter tous les projets de son service sans en être membre explicite.

## Décision

Deux couches de droits coexistent et se combinent via les policies Laravel :

**1. `UserRole` (global, par tenant) :** `Admin`, `President`, `DGS`, `RespDirection`, `RespService`, `Agent`. Attribué à l'invitation. Détermine l'accès aux fonctions d'administration et la visibilité par défaut des projets.

**2. `ProjectRole` (par projet) :** `Manager`, `Member`, `Viewer`. Attribué via la table `project_members`. Détermine ce qu'un utilisateur peut faire dans un projet précis (créer des tâches, commenter, simplement lire).

La règle générale : un `Admin` peut tout faire ; pour les autres rôles, `ProjectRole` prime sur `UserRole` à l'intérieur d'un projet.

## Conséquences

- Flexibilité métier maximale : un Agent peut piloter un projet sans élévation de privilèges globaux.
- Les policies (`ProjectPolicy`, `TaskPolicy`) doivent vérifier les deux couches — légèrement plus complexes mais explicites.
- Voir ADR-011 pour le cas particulier de la lecture seule des Responsables.
