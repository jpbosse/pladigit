<?php

namespace App\Enums;

enum RoleTitreStatut: string
{
    case ACTIF = 'actif';
    case EN_ATTENTE = 'en_attente';
    case SUSPENDU = 'suspendu';
    case TERMINE = 'termine';

    public function label(): string
    {
        return match ($this) {
            self::ACTIF => 'Actif',
            self::EN_ATTENTE => 'En attente',
            self::SUSPENDU => 'Suspendu',
            self::TERMINE => 'Terminé',
        };
    }

    /** Indique si le mandat est considéré comme actuellement en exercice. */
    public function isActive(): bool
    {
        return $this === self::ACTIF;
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
