<?php

namespace App\Enums;

/**
 * Hiérarchie des rôles utilisateur Pladigit.
 *
 * Source unique de vérité pour les 6 rôles — remplace les tableaux
 * dupliqués dans CheckRole et User::hasRoleAtLeast() (§17.7 CDC v1.2).
 *
 * Convention : plus le level() est bas, plus le rôle est privilégié.
 * Admin (1) > President (2) > DGS (3) > ... > User (6)
 *
 * Usage :
 *   UserRole::ADMIN->level()           // 1
 *   UserRole::from('dgs')->level()     // 3
 *   UserRole::values()                 // ['admin','president',...]
 *   UserRole::ADMIN->label()           // 'Administrateur'
 */
enum UserRole: string
{
    case ADMIN           = 'admin';
    case PRESIDENT       = 'president';
    case DGS             = 'dgs';
    case RESP_DIRECTION  = 'resp_direction';
    case RESP_SERVICE    = 'resp_service';
    case USER            = 'user';

    /**
     * Niveau hiérarchique — plus bas = plus de droits.
     */
    public function level(): int
    {
        return match($this) {
            self::ADMIN          => 1,
            self::PRESIDENT      => 2,
            self::DGS            => 3,
            self::RESP_DIRECTION => 4,
            self::RESP_SERVICE   => 5,
            self::USER           => 6,
        };
    }

    /**
     * Libellé français pour l'affichage dans les vues.
     */
    public function label(): string
    {
        return match($this) {
            self::ADMIN          => 'Administrateur',
            self::PRESIDENT      => 'Président',
            self::DGS            => 'Directeur Général des Services',
            self::RESP_DIRECTION => 'Responsable de Direction',
            self::RESP_SERVICE   => 'Responsable de Service',
            self::USER           => 'Utilisateur',
        };
    }

    /**
     * Retourne true si ce rôle a au moins autant de droits que $minRole.
     * Ex : UserRole::DGS->atLeast(UserRole::RESP_SERVICE) → true
     */
    public function atLeast(self $minRole): bool
    {
        return $this->level() <= $minRole->level();
    }

    /**
     * Liste de toutes les valeurs string — utile pour les règles de validation.
     * Ex : ['required', 'in:' . implode(',', UserRole::values())]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Règle de validation Laravel prête à l'emploi.
     * Ex : $request->validate(['role' => ['required', UserRole::rule()]])
     */
    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * Options pour les selects HTML — [value => label].
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

