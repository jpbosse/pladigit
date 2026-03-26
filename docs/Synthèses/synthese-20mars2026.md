PLADIGIT
Synthèse Technique — Session du 20 Mars 2026
Phase 8 — Gestion de projet | CI/CD : 471 tests / 997 assertions ✓

# 1. Résumé exécutif
Cette session a couvert la finalisation de l'onglet Agenda (vue liste épurée, modal consultation/édition/suppression), la mise en place des notifications in-app, la visioconférence Jitsi Meet, la vue d'ensemble multi-projets, les commentaires sur jalons, et la mise en conformité CI/CD complète.


# 2. Notifications in-app
## 2.1 — NotificationService
Service centralisé créant les notifications pour 5 déclencheurs : eventCreated, eventUpdated, eventDeleted (membres du projet), taskAssigned (assigné si différent du créateur), milestoneReached (membres du projet).


## 2.2 — Cloche AJAX
Chargement à la demande (pas au chargement de page), badge numérique rouge (9+ si plus de 9), marquer lu au clic, supprimer, tout lire. $notifCount partagé globalement via ResolveTenant sur toutes les pages.

# 3. Visioconférence Jitsi Meet
JitsiService génère une URL unique par demande : https://meet.numerique.gouv.fr/pladigit-{slug}-{token6}. Bouton dans la sidebar projet et l'onglet Agenda. Modal avec URL copiable + bouton Rejoindre. Instance configurable par tenant via Admin > Paramètres > Visioconférence. Instance par défaut : meet.numerique.gouv.fr (État français, RGPD, gratuit).


# 4. Agenda — vue liste finale
Après plusieurs itérations sur les vues calendrier (problèmes Alpine x-data imbriqués, conflits CSS/JS), décision de revenir à une vue liste propre pour l'onglet Agenda du projet. Un module Agenda dédié avec vrai composant calendrier sera développé ultérieurement.

Chaque événement affiche : bande colorée gauche, bloc date (jour/mois/année), titre, heure, lieu, description tronquée, badge visibilité avec tooltip. Modal consultation centrée remplace le slideover pleine hauteur.


# 5. Vue d'ensemble multi-projets
La page /projects est enrichie en tableau de bord de supervision pour Admin/DGS. L'icône sidebar passe de grille 2x2 à barres de progression avec badge rouge si projets en alerte.


# 6. Commentaires sur jalons
Zone de commentaire inline sur les jalons atteints (✓) ou en retard. Les managers voient un bouton + Ajouter un commentaire (pointillé discret) ou le commentaire existant avec ✏️. Clic → textarea inline avec ✓ / ✕. Visible en lecture seule pour les non-managers. Fonctionne sur jalons enfants et jalons autonomes.


# 7. Corrections CI/CD

# 8. Bonnes pratiques consolidées

# 9. Prochaines étapes

Pladigit — Les Bézots, Soullans (85) — github.com/jpbosse/pladigit — AGPL-3.0