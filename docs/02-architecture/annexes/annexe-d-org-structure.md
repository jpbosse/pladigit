# Annexe D — Structure organisationnelle

Cette annexe documente la fonctionnalité de gestion de la hiérarchie organisationnelle implémentée en Phase 2 (Mars 2026).

## D.1 — Modèle de données

## D.2 — Règles métier
- Une Direction (type="direction") n'a pas de parent_id.
- Un Service (type="service") a obligatoirement un parent_id pointant vers une Direction.
- Un utilisateur peut appartenir à plusieurs services (relation N:N via pivot).
- Un responsable peut gérer plusieurs services (is_manager=true dans le pivot).
- Un service rattaché directement au DGS est placé sous une Direction fictive "Direction Générale des Services".

## D.3 — API des modèles Eloquent

## D.4 — Interface d'administration
- Page /admin/departments : arborescence visuelle Direction > Services, avec création inline.
- Formulaire utilisateur : sélecteur hiérarchique avec cases à cocher.
- Création d'un nouveau département directement depuis le formulaire utilisateur.
- Label dynamique selon le rôle sélectionné ("Services gérés", "Direction sous responsabilité", etc.).

## D.5 — Visibilité des utilisateurs selon le rôle