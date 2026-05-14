<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service de recherche floue pour les colonnes DataGrid de type NOM_PERSONNE.
 *
 * Algorithme :
 *   1. Normalisation de la valeur recherchée (trim, minuscules, accents supprimés)
 *   2. Récupération des valeurs distinctes de la colonne en base
 *   3. Filtre PHP par distance de Levenshtein ≤ 2
 *   4. Fallback SOUNDS LIKE MySQL pour les homophones non couverts par Levenshtein
 *
 * Usage :
 *   $ids = DatagridFuzzySearch::matchingIds($mysqlTable, $columnName, $searchValue);
 *   $query->whereIn('id', $ids);
 *
 * Limites :
 *   - Adapté aux grilles < 50 000 lignes (récupération des valeurs distinctes en mémoire).
 *   - Pour les volumes importants, préférer un index full-text dédié (bloc 6.8).
 */
class DatagridFuzzySearch
{
    /**
     * Retourne les IDs des lignes dont la colonne $columnName
     * correspond à $searchValue avec une tolérance floue.
     *
     * @return array<int, int>
     */
    public static function matchingIds(
        string $mysqlTable,
        string $columnName,
        string $searchValue,
        int $maxDistance = 2
    ): array {
        if (trim($searchValue) === '') {
            return [];
        }

        $needle = self::normalize($searchValue);

        // ── 1. Levenshtein sur les valeurs distinctes ─────────────────────────
        $distinctRows = DB::connection('tenant')
            ->table($mysqlTable)
            ->select('id', $columnName)
            ->whereNotNull($columnName)
            ->where($columnName, '!=', '')
            ->get();

        $matchedIds = [];

        foreach ($distinctRows as $row) {
            $hay = self::normalize((string) ($row->{$columnName} ?? ''));
            if ($hay === '') {
                continue;
            }

            // Correspondance exacte (après normalisation)
            if ($hay === $needle) {
                $matchedIds[] = (int) $row->id;

                continue;
            }

            // LIKE large pour limiter le Levenshtein aux candidats proches
            // (éviter de calculer Levenshtein sur toutes les valeurs très différentes)
            if (abs(strlen($hay) - strlen($needle)) > $maxDistance + 1) {
                continue;
            }

            if (levenshtein($needle, $hay) <= $maxDistance) {
                $matchedIds[] = (int) $row->id;
            }
        }

        // ── 2. Fallback SOUNDS LIKE MySQL ─────────────────────────────────────
        // Complète Levenshtein pour les homophones (Dupond/Dupont déjà couverts,
        // mais Beauchamp/Beaucham pas forcément).
        $soundsLikeIds = DB::connection('tenant')
            ->table($mysqlTable)
            ->whereRaw("`{$columnName}` SOUNDS LIKE ?", [$searchValue])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        return array_values(array_unique(array_merge($matchedIds, $soundsLikeIds)));
    }

    /**
     * Analyse un tableau de valeurs (ex: lignes d'un fichier d'import)
     * et détecte celles qui ressemblent à des valeurs existantes en base.
     *
     * Retourne un tableau de correspondances probables :
     * [
     *   [
     *     'import_value'   => 'Jean Dupond',
     *     'import_index'   => 3,          // index de la ligne dans le fichier
     *     'existing_id'    => 42,
     *     'existing_value' => 'Jean Dupont',
     *     'distance'       => 1,
     *   ],
     *   ...
     * ]
     *
     * @param  array<int, string>  $importValues  Valeurs du fichier à analyser
     * @param  array<int, array{id:int, value:string}>  $existingValues  Valeurs existantes en base
     * @return array<int, array{import_value:string, import_index:int, existing_id:int, existing_value:string, distance:int}>
     */
    public static function detectDuplicates(
        array $importValues,
        array $existingValues,
        int $maxDistance = 2
    ): array {
        $duplicates = [];

        foreach ($importValues as $importIndex => $importValue) {
            $needleRaw = (string) $importValue;
            if (trim($needleRaw) === '') {
                continue;
            }
            $needle = self::normalize($needleRaw);

            $bestMatch = null;
            $bestDist = PHP_INT_MAX;

            foreach ($existingValues as $existing) {
                $hay = self::normalize((string) $existing['value']);
                if ($hay === '') {
                    continue;
                }

                // Correspondance exacte → pas un doublon suspect, c'est une mise à jour
                if ($hay === $needle) {
                    $bestMatch = null; // on ne signale pas les exacts
                    break;
                }

                if (abs(strlen($hay) - strlen($needle)) > $maxDistance + 1) {
                    continue;
                }

                $dist = levenshtein($needle, $hay);
                if ($dist <= $maxDistance && $dist < $bestDist) {
                    $bestDist = $dist;
                    $bestMatch = $existing;
                }
            }

            if ($bestMatch !== null) {
                $duplicates[] = [
                    'import_value' => $needleRaw,
                    'import_index' => $importIndex,
                    'existing_id' => $bestMatch['id'],
                    'existing_value' => $bestMatch['value'],
                    'distance' => $bestDist,
                ];
            }
        }

        return $duplicates;
    }

    /**
     * Normalise une chaîne pour la comparaison floue :
     * minuscules, trim, accents supprimés, espaces multiples réduits.
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = Str::ascii($value);            // supprime les accents
        $value = preg_replace('/\s+/', ' ', $value) ?? $value; // espaces multiples

        return $value;
    }
}
