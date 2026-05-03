<?php

namespace App\Enums;

enum OrganisationType: string
{
    case COMMUNE = 'commune';
    case EPCI = 'epci';
    case ASSOCIATION = 'association';
    case ENTREPRISE = 'entreprise';
    case INSTITUTION = 'institution';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::COMMUNE => 'Commune',
            self::EPCI => 'EPCI',
            self::ASSOCIATION => 'Association',
            self::ENTREPRISE => 'Entreprise',
            self::INSTITUTION => 'Institution',
            self::AUTRE => 'Autre',
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
