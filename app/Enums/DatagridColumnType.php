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
    case NOM_PERSONNE = 'nom_personne';
    case CHEMIN_FICHIER = 'chemin_fichier';
    case RELATION = 'relation';

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
            self::NOM_PERSONNE => 'Nom de personne',
            self::CHEMIN_FICHIER => 'Chemin de fichier',
            self::RELATION => 'Relation',
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
        return in_array($this, [self::TEXT, self::EMAIL, self::PHONE, self::NOM_PERSONNE], true);
    }

    /** Indique si le type active la recherche floue par défaut. */
    public function hasFuzzySearch(): bool
    {
        return $this === self::NOM_PERSONNE;
    }

    /** Indique si le type est une relation vers une autre table. */
    public function isRelation(): bool
    {
        return $this === self::RELATION;
    }

    /** Indique si le type est en lecture seule (calculé ou relation). */
    public function isReadonly(): bool
    {
        return $this === self::RELATION;
    }

    /** @return array<int, self> */
    public static function importableTypes(): array
    {
        return array_filter(
            self::cases(),
            fn ($case) => ! in_array($case, [self::RELATION], true)
        );
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
