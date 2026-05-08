<?php

namespace App\Services\DatagridNormalization;

/**
 * Plan de migration complet issu de l'assistant de normalisation.
 */
final class MigrationPlan
{
    /**
     * @param  array<int, MigrationTable>  $tables
     */
    public function __construct(
        public readonly array $tables,
    ) {}

    public function mainTable(): MigrationTable
    {
        return $this->tables[0];
    }

    /**
     * @return array<int, MigrationTable>
     */
    public function derivedTables(): array
    {
        return array_slice($this->tables, 1);
    }

    public function totalEstimatedRows(): int
    {
        return array_sum(array_map(
            fn (MigrationTable $t) => $t->estimatedRows,
            $this->tables
        ));
    }
}
