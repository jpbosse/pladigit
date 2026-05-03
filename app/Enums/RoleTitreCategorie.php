<?php

namespace App\Enums;

enum RoleTitreCategorie: string
{
    case ELU = 'elu';
    case COMMISSION_INTERNE = 'commission_interne';
    case ASSOCIATIF = 'associatif';
    case INTERCOMMUNAL = 'intercommunal';
    case PROTOCOLE = 'protocole';
    case ECONOMIQUE = 'economique';
    case MEDIA = 'media';
    case ACADEMIQUE = 'academique';
    case AUTRE = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::ELU => 'Élu',
            self::COMMISSION_INTERNE => 'Commission interne',
            self::ASSOCIATIF => 'Associatif',
            self::INTERCOMMUNAL => 'Intercommunal',
            self::PROTOCOLE => 'Protocole',
            self::ECONOMIQUE => 'Économique',
            self::MEDIA => 'Média',
            self::ACADEMIQUE => 'Académique',
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
