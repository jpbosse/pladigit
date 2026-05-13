<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithFormatData;
use Maatwebsite\Excel\Concerns\WithLimit;

/**
 * Import léger — lit les 6 premières lignes (1 header + 5 données).
 * Utilisé par ImportWizard à l'étape 1 pour détecter les colonnes
 * et afficher un aperçu des valeurs distinctes par colonne.
 * L'import réel des données est délégué à ImportDatagridJob.
 */
class DatagridImport implements ToCollection, WithCalculatedFormulas, WithFormatData, WithLimit
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

    /** Lire header + 5 lignes de données — suffisant pour l'aperçu. */
    public function limit(): int
    {
        return 6;
    }

    /** Retourne la première ligne brute (en-têtes). */
    public function getHeaders(): array
    {
        return array_values($this->rows->first()?->toArray() ?? []);
    }

    /**
     * Retourne les valeurs distinctes par index de colonne, issues des 5 lignes d'aperçu.
     * Format : [0 => ['M', 'F'], 1 => ['Actif', 'Inactif'], ...]
     *
     * @return array<int, string[]>
     */
    public function getSampleValues(): array
    {
        $dataRows = $this->rows->skip(1)->values();
        $result = [];

        foreach ($dataRows as $row) {
            foreach ($row->toArray() as $idx => $value) {
                $str = trim((string) $value);
                if ($str === '') {
                    continue;
                }
                $result[$idx][] = $str;
            }
        }

        // Dédoublonner et limiter à 5 valeurs distinctes par colonne
        foreach ($result as $idx => $values) {
            $result[$idx] = array_values(array_slice(array_unique($values), 0, 5));
        }

        return $result;
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
