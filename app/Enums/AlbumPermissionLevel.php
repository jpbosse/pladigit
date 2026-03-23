<?php

namespace App\Enums;

enum AlbumPermissionLevel: string
{
    case None = 'none';
    case View = 'view';
    case Download = 'download';
    case Upload = 'upload';
    case Admin = 'admin';

    /** Label affiché dans l'interface */
    public function label(): string
    {
        return match ($this) {
            self::None => 'Aucun droit',
            self::View => 'Visualisation',
            self::Download => 'Téléchargement',
            self::Upload => 'Téléverser',
            self::Admin => 'Administration',
        };
    }

    /** Niveau numérique — plus grand = plus de droits */
    public function level(): int
    {
        return match ($this) {
            self::None => 0,
            self::View => 1,
            self::Download => 2,
            self::Upload => 3,
            self::Admin => 4,
        };
    }

    /** Vérifie si ce niveau est au moins égal à $min */
    public function atLeast(self $min): bool
    {
        return $this->level() >= $min->level();
    }

    /** Retourne le niveau le plus élevé entre deux */
    public static function max(self $a, self $b): self
    {
        return $a->level() >= $b->level() ? $a : $b;
    }

    /** Toutes les valeurs pour les selects */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
