# Annexe A — Personas et parcours utilisateurs

Cette annexe présente les quatre profils types d'utilisateurs de Pladigit. Chaque persona illustre un cas d'usage concret et guide les choix de conception des interfaces.

## A.1 — Marie, Secrétaire de Mairie

### Parcours type
- Crée un nouveau document depuis un modèle (Template) dans la GED.
- L'édite via Collabora Online (interface Word-like familière).
- Change le statut en "En révision" et notifie le Maire via le système de notification.
- Le Maire valide — le document passe en "Archivé" automatiquement.

## A.2 — Jean-Pierre, Maire (Élu)

### Parcours type
- Consulte le Tableau de Bord "Direction" sur son téléphone.
- Voit les documents en attente de validation et les alertes de retard sur les projets.
- Clique sur "Valider" directement depuis la notification email reçue sur son téléphone.

## A.3 — Julie, Responsable Technique (Admin Organisation)

### Parcours type
- Reçoit une notification "Nouvel utilisateur à intégrer".
- Crée le compte depuis /admin/users, l'affecte à sa direction et son service, envoie l'invitation par email.
- Configure les directions et services depuis l'interface /admin/departments.
- Vérifie l'espace disque consommé dans le panneau d'administration.

## A.4 — Amadou, Responsable de Service Technique

### Parcours type
- Se connecte — voit uniquement les membres de son service dans /admin/users (restriction par rôle).
- Assigne une tâche à un agent depuis le module Gestion de projet.
- Consulte l'agenda partagé du service pour coordonner les absences et les réunions.