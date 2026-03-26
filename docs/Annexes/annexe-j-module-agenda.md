PLADIGIT
Plateforme de Digitalisation Interne
Alternative souveraine open source AGPL-3.0

Annexe J — Module Agenda
Gestion des événements par projet, export iCal, intégration visioconférence

# 1. Présentation du module
Le module Agenda de Pladigit permet aux membres d’un projet de gérer des événements liés à ce projet : réunions, auditions, ateliers, formations, échéances clés. Il complète les jalons (qui sont des éléments de planification) en offrant une dimension calendaire avec horaires, lieux, visibilité différenciée et export vers les agendas externes.
Le module est intégré comme onglet dans la page de détail d’un projet (/projects/{id}?section=planif, vue Agenda). Il n’existe pas d’agenda global inter-projets dans cette phase — chaque événement est rattaché à un projet spécifique via la colonne project_id.

# 2. Architecture des données
## 2.1 — Table events
La table events stocke tous les événements de l’agenda. Elle est créée par la migration 2025_10_01_000011_create_agenda_tables.php et étendue par 2026_10_01_000004_add_project_id_to_events_table.php.

## 2.2 — Table event_participants
Table pivot entre les événements et les utilisateurs participants. Contrainte UNIQUE sur (event_id, user_id) pour éviter les doublons.

## 2.3 — Modèle Eloquent
Le modèle App\Models\Tenant\Event expose les relations suivantes :
- creator() → BelongsTo<User> — utilisateur créateur
- project() → BelongsTo<Project> — projet associé (nullable)
- participants() → HasMany<EventParticipant> — liste des participants

Le cast recurrence_rule => 'array' permet de stocker des règles de récurrence JSON pour une implémentation future.

# 3. Niveaux de visibilité
Chaque événement dispose d’un champ visibility à trois valeurs :

La visibilité est appliquée à deux niveaux : dans l’affichage de l’onglet Agenda (les événements privés d’autrui ne sont pas listés) et dans l’export iCal (les événements privés sont exclus sauf pour leur créateur).

# 4. Interface utilisateur
## 4.1 — Vue liste (onglet Agenda)
L’onglet Agenda dans la section Planification affiche les événements du projet sous forme de liste chronologique. Décision technique (session 20 mars 2026) : la vue calendrier a été abandonnée en raison de conflits Alpine.js x-data imbriqués et de problèmes CSS. La vue liste est plus robuste et couvre les besoins immédiats.
Chaque événement affiche :
- Bande colorée gauche (couleur de l’événement)
- Bloc date (jour / mois / année)
- Titre, heure, lieu
- Description tronquée
- Badge de visibilité avec tooltip (Privé / Restreint / Public)
- Bouton Modifier (créateur uniquement)

## 4.2 — Slideover de consultation
Un clic sur un événement ouvre un slideover depuis la droite (_event_slideover.blade.php). Ce partial est inclus directement dans show.blade.php hors des x-show d’onglets — même pattern que _task_slideover.blade.php. Il affiche l’intégralité des informations de l’événement et propose les actions Modifier / Supprimer pour les utilisateurs habilités.
## 4.3 — Formulaire de création / modification
La modal de création permet de saisir :
- Titre (obligatoire, max 255 caractères)
- Description (optionnel, max 2000 caractères)
- Lieu (optionnel, max 500 caractères)
- Date et heure de début / fin (fin >= début)
- Journée entière (toggle boolean)
- Visibilité (private / restricted / public)
- Couleur (sélecteur pastilles hexadécimal)
- URL de visioconférence (optionnel)

## 4.4 — Visioconférence Jitsi Meet
Le module Agenda intègre un service de visioconférence via JitsiService. L’instance par défaut est meet.numerique.gouv.fr (service de l’État français, RGPD, gratuit). Chaque organisation peut configurer sa propre instance Jitsi depuis Admin → Paramètres → Visioconférence.
JitsiService génère une URL unique par demande : https://{instance}/pladigit-{slug}-{token6}. Un bouton « Rejoindre » avec une modal affichant l’URL copiable est présent dans la sidebar du projet et dans l’onglet Agenda.

# 5. Routes et API
Toutes les routes sont protégées par le middleware module:projects et nécessitent une authentification.

Les méthodes store() et update() retournent du JSON (wantsJson()) ou font une redirection back() selon le type de requête. Le contrôleur est App\Http\Controllers\Projects\ProjectEventController.

# 6. Droits et sécurité

La vérification authorizeEventAction() contrôle que seul le créateur, un owner du projet ou un DGS/Admin peut modifier ou supprimer un événement. Cette logique est implémentée directement dans le contrôleur (pas dans une Policy dédiée).

# 7. Export iCalendar (RFC 5545)
## 7.1 — Export agenda complet
La route GET /{project}/export/ical génère un fichier .ics contenant les événements du projet (filtrés selon la visibilité) et les jalons. Le nom du fichier : projet-{slug}-agenda.ics.
## 7.2 — Export jalons seuls
La route GET /{project}/export/milestones.ics génère un fichier .ics ne contenant que les jalons et phases du projet, destiné aux élus pour import dans Outlook / Google Agenda. Le nom : {slug}-milestones.ics.
## 7.3 — Format des VEVENT selon le type


Les deux exports incluent : X-WR-CALNAME (nom du calendrier pour Outlook/Google), X-WR-TIMEZONE (Europe/Paris), PRODID (-//Pladigit//...).

# 8. Notifications liées aux événements
Le module Agenda déclenche des notifications in-app via NotificationService pour trois actions :
- eventCreated(Event, User) : notifie tous les membres du projet sauf le créateur. Type : agenda.event_created.
- eventUpdated(Event, User) : notifie les membres sauf le modificateur. Type : agenda.event_updated.
- eventDeleted(string, Project, User) : notifie les membres sauf le suppresseur. Type : agenda.event_deleted.

Les notifications privées ne déclenchent pas de notification (la visibilité privée implique que l’événement n’est pas partagé). Cette vérification est à implémenter dans NotificationService lors d’une évolution future.

# 9. Couverture de tests
Le module Agenda est couvert par ProjectIcalTest (8 tests) et ProjectMilestonesIcalTest (10 tests), tous intégrés au pipeline CI/CD GitHub Actions.


# 10. Évolutions prévues
- Agenda global inter-projets — vue calendrère agrégeant tous les événements visibles du tenant
- Vue calendrier mensuelle / hebdomadaire — composant Livewire (Phase 3+ réservé au CDC)
- Synchronisation CalDAV — push vers Nextcloud / Zimbra via protocole standard
- Participants explicites — utilisation de event_participants pour inviter des membres spécifiques
- Récurrence — règles RRULE via le champ recurrence_rule (infrastructure en place, logique à implémenter)
- Notifications pour événements privés — exclure les notifications si visibility = private


Les Bézots — Soullans (85) — github.com/jpbosse/pladigit — AGPL-3.0