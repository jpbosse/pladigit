<?php

namespace App\Services\DatagridNormalization;

/**
 * Table planifiée dans une migration de normalisation.
 */
final class MigrationTable
{
    /**
     * @param  array<int, string>  $columns
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
        public readonly int $estimatedRows,
        public readonly ?string $relationType, // null | '1n' | 'nn'
    ) {}
}
