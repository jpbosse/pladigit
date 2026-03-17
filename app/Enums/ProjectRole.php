<?php

namespace App\Enums;

/**
 * Rôle d'un utilisateur au sein d'un projet.
 *
 * Distinct de UserRole (rôle global du tenant) — deux couches cumulatives
 * selon ADR-010 :
 *   1. UserRole global  : Admin/Président/DGS → accès total à tous les projets
 *   2. ProjectRole local : détermine ce qu'un membre peut faire dans UN projet
 *
 * Convention :
 *   - owner  : créateur ou chef de projet — CRUD complet, gestion membres/jalons
 *   - member : contributeur — crée/édite ses tâches, commente
 *   - viewer : lecture seule — consulte mais ne modifie rien
 *
 * Usage :
 *   ProjectRole::OWNER->canEdit()    // true
 *   ProjectRole::VIEWER->canManage() // false
 *   ProjectRole::from('member')->label() // 'Contributeur'
 *   ProjectRole::options() // ['owner' => 'Chef de projet', ...]
 */
enum ProjectRole: string
{
    case OWNER = 'owner';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    /**
     * Libellé français pour l'affichage dans les vues.
     */
    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Chef de projet',
            self::MEMBER => 'Contributeur',
            self::VIEWER => 'Observateur',
        };
    }

    /**
     * Description courte affichée dans l'interface de gestion des membres.
     */
    public function description(): string
    {
        return match ($this) {
            self::OWNER => 'Accès complet — gestion des membres, jalons et paramètres du projet',
            self::MEMBER => 'Peut créer et modifier des tâches, commenter, déplacer dans le Kanban',
            self::VIEWER => 'Lecture seule — peut consulter et exporter mais pas modifier',
        };
    }

    /**
     * Peut modifier des tâches (créer, éditer, déplacer, commenter).
     */
    public function canEdit(): bool
    {
        return match ($this) {
            self::OWNER, self::MEMBER => true,
            self::VIEWER => false,
        };
    }

    /**
     * Peut gérer le projet (membres, jalons, paramètres, suppression).
     */
    public function canManage(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Toutes les valeurs string — pour la validation Laravel.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Règle de validation prête à l'emploi.
     * Ex : $request->validate(['role' => ['required', ProjectRole::rule()]])
     */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /**
     * Options pour les selects HTML — [value => label].
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
