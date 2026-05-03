<?php

namespace App\Enums;

enum DatagridColumnType: string
{
    case TEXT = 'text';
    case NUMBER = 'number';
    case DATE = 'date';
    case BOOLEAN = 'boolean';
    case SELECT = 'select';
    case EMAIL = 'email';
    case PHONE = 'phone';
    case SIRET = 'siret';
    case POSTAL_CODE = 'postal_code';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Texte',
            self::NUMBER => 'Nombre',
            self::DATE => 'Date',
            self::BOOLEAN => 'Booléen',
            self::SELECT => 'Liste de valeurs',
            self::EMAIL => 'Adresse e-mail',
            self::PHONE => 'Téléphone',
            self::SIRET => 'SIRET',
            self::POSTAL_CODE => 'Code postal',
        };
    }

    /** Indique si le type accepte un champ `options` (valeurs possibles). */
    public function hasOptions(): bool
    {
        return $this === self::SELECT;
    }

    /** Indique si le type accepte une contrainte `length`. */
    public function hasLength(): bool
    {
        return in_array($this, [self::TEXT, self::EMAIL, self::PHONE], true);
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
