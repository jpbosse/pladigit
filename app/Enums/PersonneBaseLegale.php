<?php

namespace App\Enums;

/**
 * Base légale RGPD pour le traitement des données d'une personne (art. 6 RGPD).
 */
enum PersonneBaseLegale: string
{
    case CONSENTEMENT = 'consentement';
    case INTERET_LEGITIME = 'interet_legitime';
    case MISSION_SERVICE_PUBLIC = 'mission_service_public';
    case OBLIGATION_LEGALE = 'obligation_legale';

    public function label(): string
    {
        return match ($this) {
            self::CONSENTEMENT => 'Consentement (art. 6.1.a)',
            self::INTERET_LEGITIME => 'Intérêt légitime (art. 6.1.f)',
            self::MISSION_SERVICE_PUBLIC => 'Mission de service public (art. 6.1.e)',
            self::OBLIGATION_LEGALE => 'Obligation légale (art. 6.1.c)',
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
