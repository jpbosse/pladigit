<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class DatagridImport implements ToCollection
{
    private Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }

    /** Retourne la première ligne brute (en-têtes). */
    public function getHeaders(): array
    {
        return array_values($this->rows->first()?->toArray() ?? []);
    }

    /** Retourne les lignes de données en sautant la ligne d'en-têtes. */
    public function getDataRows(): Collection
    {
        return $this->rows->skip(1)->values();
    }
}
