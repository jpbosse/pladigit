<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithFormatData;

class DatagridImport implements ToCollection, WithCalculatedFormulas, WithFormatData
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

    /** Retourne toutes les lignes sans sauter la première (quand le fichier n'a pas d'en-tête). */
    public function getAllRows(): Collection
    {
        return $this->rows->values();
    }
}
