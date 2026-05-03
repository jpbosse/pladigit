<?php

namespace App\Enums;

enum DatagridAuditAction: string
{
    case READ = 'read';
    case WRITE = 'write';
    case DELETE = 'delete';
    case EXPORT = 'export';
    case IMPORT = 'import';

    public function label(): string
    {
        return match ($this) {
            self::READ => 'Lecture',
            self::WRITE => 'Modification',
            self::DELETE => 'Suppression',
            self::EXPORT => 'Export',
            self::IMPORT => 'Import',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
