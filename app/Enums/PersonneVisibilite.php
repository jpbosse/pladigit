<?php

namespace App\Enums;

enum PersonneVisibilite: string
{
    case PUBLIC = 'public';
    case INTERNE = 'interne';
    case CONFIDENTIEL = 'confidentiel';
    case ARCHIVE = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Public',
            self::INTERNE => 'Interne',
            self::CONFIDENTIEL => 'Confidentiel',
            self::ARCHIVE => 'Archivé',
        };
    }

    /** Niveau numérique — utile pour comparaisons de visibilité. */
    public function level(): int
    {
        return match ($this) {
            self::PUBLIC => 1,
            self::INTERNE => 2,
            self::CONFIDENTIEL => 3,
            self::ARCHIVE => 4,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
