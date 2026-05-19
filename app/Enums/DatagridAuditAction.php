<?php

namespace App\Enums;

enum DatagridAuditAction: string
{
    case READ = 'read';
    case WRITE = 'write';
    case DELETE = 'delete';
    case EXPORT = 'export';
    case CREATE = 'create';
    case IMPORT = 'import';
    case STRUCTURE_CREATE = 'structure_create';
    case STRUCTURE_DROP = 'structure_drop';
    case COLUMN_UPDATE = 'column_update';
    case COLUMN_DROP = 'column_drop';
    case COLUMN_REORDER = 'column_reorder';

    public function label(): string
    {
        return match ($this) {
            self::READ => 'Lecture',
            self::CREATE => 'Création',
            self::WRITE => 'Modification',
            self::DELETE => 'Suppression',
            self::EXPORT => 'Export',
            self::IMPORT => 'Import',
            self::STRUCTURE_CREATE => 'Création grille',
            self::STRUCTURE_DROP => 'Suppression grille',
            self::COLUMN_UPDATE => 'Modif. colonne',
            self::COLUMN_DROP => 'Suppression colonne',
            self::COLUMN_REORDER => 'Réordonnancement',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
