# Annexe Q — Plan de succession et continuité projet

Ce projet est porté par un développeur unique. Ce plan garantit qu'un tiers puisse reprendre le projet sans perte majeure d'information ni blocage opérationnel.

## Q.1 — Localisation de tous les actifs

## Q.2 — Guide de reprise en main (4 étapes)
### Étape 1 — Accéder au code
- Cloner le dépôt Git. Récupérer le fichier .env depuis le coffre-fort.
- Lire le README.md principal, puis les README de chaque module dans /docs/.
- Consulter le journal des décisions architecturales (/docs/adr/).

### Étape 2 — Comprendre l'architecture
- Lire les Annexes B (multi-tenant), K (CI/CD) et ce CDC.
- Exécuter les tests : php artisan test — tous doivent passer au vert (237/546).

### Étape 3 — Accéder à la production
- Connexion SSH au VPS avec la clé récupérée dans le coffre-fort.
- Vérifier l'état des services : systemctl status nginx mysql redis soketi
- Vérifier Collabora Online : docker ps | grep collabora

### Étape 4 — Effectuer une modification
- Créer une branche Git : git checkout -b fix/description
- Modifier le code. Écrire les tests correspondants (TDD).
- Pousser : git push → CI/CD GitHub Actions déclenche les 4 checks automatiquement.

## Q.3 — Options stratégiques en cas d'indisponibilité