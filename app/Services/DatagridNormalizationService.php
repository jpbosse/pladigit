<?php

namespace App\Services;

use App\Services\DatagridNormalization\MigrationPlan;
use App\Services\DatagridNormalization\MigrationTable;

/**
 * Service de normalisation des fichiers DataGrid (ADR-040 §3.5).
 *
 * Analyse les fichiers importés pour détecter les colonnes répétées
 * (candidates à la normalisation en table séparée) et les colonnes
 * inutiles (vides ou nommées avec des mots-clés de fichiers de travail).
 *
 * Réutilisé par :
 * - L'assistant de normalisation (wizard Phase 1-3)
 * - Le DataGrid Assistant IA (futur — ADR-039)
 */
class DatagridNormalizationService
{
    /**
     * Mots-clés indiquant une colonne probablement inutile.
     * Noms courants dans les fichiers Excel de travail.
     */
    private const USELESS_KEYWORDS = [
        'temp', 'tmp', 'copie', 'copy', 'old', 'vieux', 'ancien',
        'backup', 'bak', 'test', 'archive', 'a_traiter', 'todo',
        'brouillon', 'draft', 'inutile', 'delete', 'suppr',
    ];

    /**
     * Seuil de remplissage en dessous duquel une colonne est signalée
     * comme potentiellement inutile (80% de valeurs vides).
     */
    private const EMPTY_THRESHOLD = 0.80;

    /**
     * Nombre minimal de colonnes dans un groupe répété pour déclencher
     * une recommandation de normalisation.
     */
    private const MIN_REPEATING_GROUP_SIZE = 3;

    /**
     * Détecte les groupes de colonnes répétées dans les en-têtes.
     *
     * Exemples détectés :
     *   Fonction 1, Fonction 2, Fonction 3  → groupe 'fonction'
     *   date_debut_1, date_debut_2           → groupe 'date_debut' (ignoré — < 3)
     *   Commission1, Commission2, Commission3 → groupe 'commission'
     *
     * @param  array<int, string>  $headers  Liste des en-têtes de colonnes
     * @return array<string, array<int, string>> Groupes détectés [prefixe => [col1, col2, ...]]
     */
    public function detectRepeatingGroups(array $headers): array
    {
        $groups = [];

        foreach ($headers as $header) {
            // Normaliser : minuscules, supprimer accents
            $normalized = $this->normalizeHeader($header);

            // Patterns : suffixe numérique séparé par espace, underscore ou rien
            // ex: "Fonction 1", "fonction_1", "Fonction1"
            if (preg_match('/^(.+?)[\s_-]?(\d+)$/', $normalized, $matches)) {
                $prefix = rtrim($matches[1], ' _-');
                if (! empty($prefix)) {
                    $groups[$prefix][] = $header;
                }
            }
        }

        // Ne garder que les groupes avec au moins MIN_REPEATING_GROUP_SIZE colonnes
        return array_filter(
            $groups,
            fn ($cols) => count($cols) >= self::MIN_REPEATING_GROUP_SIZE
        );
    }

    /**
     * Détecte les colonnes probablement inutiles.
     *
     * Une colonne est signalée si :
     * - Son nom contient un mot-clé de fichier de travail, OU
     * - Plus de EMPTY_THRESHOLD % de ses valeurs sont vides/null
     *
     * @param  array<int, string>  $headers  En-têtes
     * @param  array<int, array<mixed>>  $rows  Lignes de données
     * @return array<int, string> Liste des en-têtes signalés
     */
    public function detectUselessColumns(array $headers, array $rows): array
    {
        $useless = [];
        $totalRows = count($rows);

        foreach ($headers as $index => $header) {
            // Détection par mot-clé dans le nom
            $normalized = $this->normalizeHeader($header);
            foreach (self::USELESS_KEYWORDS as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    $useless[] = $header;

                    continue 2;
                }
            }

            // Détection par taux de remplissage
            if ($totalRows > 0) {
                $emptyCount = 0;
                foreach ($rows as $row) {
                    $value = $row[$index] ?? null;
                    if ($value === null || $value === '' || $value === '0') {
                        $emptyCount++;
                    }
                }

                $emptyRatio = $emptyCount / $totalRows;
                if ($emptyRatio >= self::EMPTY_THRESHOLD) {
                    $useless[] = $header;
                }
            }
        }

        return array_unique($useless);
    }

    /**
     * Construit le plan de migration depuis les groupes définis par l'utilisateur.
     *
     * @param  array<string, array<int, string>>  $groups  [nomTable => [colonne1, colonne2, ...]]
     * @param  array<int, string>  $mainColumns  Colonnes de la table principale
     * @param  array<int, array<mixed>>  $rows  Données pour estimation des lignes
     */
    public function buildMigrationPlan(
        array $groups,
        array $mainColumns,
        array $rows
    ): MigrationPlan {
        $tables = [];

        // Table principale
        $tables[] = new MigrationTable(
            name: 'principale',
            columns: $mainColumns,
            estimatedRows: count($rows),
            relationType: null,
        );

        // Tables dérivées des groupes répétés
        foreach ($groups as $tableName => $columns) {
            $tables[] = new MigrationTable(
                name: $tableName,
                columns: $columns,
                estimatedRows: $this->estimateExpandedRows($rows, $columns),
                relationType: '1n',
            );
        }

        return new MigrationPlan(tables: $tables);
    }

    /**
     * Pivote les colonnes répétées en lignes.
     *
     * Exemple :
     * Entrée : ['nom' => 'Dupont', 'Fonction 1' => 'Maire', 'Fonction 2' => 'Adjoint']
     * Sortie : [['nom' => 'Dupont', 'fonction' => 'Maire'], ['nom' => 'Dupont', 'fonction' => 'Adjoint']]
     *
     * @param  array<int, array<mixed>>  $rows  Lignes source
     * @param  array<int, string>  $columnGroup  Colonnes du groupe répété (ex: ['Fonction 1', 'Fonction 2'])
     * @param  string  $targetColumn  Nom de la colonne cible (ex: 'fonction')
     * @param  string  $fkColumn  Nom de la colonne FK (ex: 'principale_id')
     * @return array<int, array<mixed>> Lignes pivotées
     */
    public function pivotColumnsToRows(
        array $rows,
        array $columnGroup,
        string $targetColumn,
        string $fkColumn
    ): array {
        $result = [];

        foreach ($rows as $rowIndex => $row) {
            foreach ($columnGroup as $colName) {
                $value = $row[$colName] ?? null;

                // Ignorer les cellules vides — pas de ligne pivot vide
                if ($value === null || $value === '') {
                    continue;
                }

                $result[] = [
                    $fkColumn => $rowIndex + 1, // FK vers la ligne principale
                    $targetColumn => $value,
                ];
            }
        }

        return $result;
    }

    /**
     * Déduplique les valeurs d'une colonne liste pour créer une table de référence.
     *
     * @param  array<int, array<mixed>>  $rows  Lignes source
     * @param  array<int, string>  $columns  Colonnes à agréger (ex: ['Commission 1', 'Commission 2'])
     * @return array<int, string> Valeurs uniques triées
     */
    public function deduplicateListValues(array $rows, array $columns): array
    {
        $values = [];

        foreach ($rows as $row) {
            foreach ($columns as $col) {
                $value = trim((string) ($row[$col] ?? ''));
                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        $unique = array_unique($values);
        sort($unique);

        return $unique;
    }

    /**
     * Génère un résumé lisible du diagnostic pour l'interface wizard Phase 1.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<mixed>>  $rows
     * @param  array<string, array<int, string>>  $repeatingGroups
     * @param  array<int, string>  $uselessColumns
     * @return array<string, mixed>
     */
    public function buildDiagnostic(
        array $headers,
        array $rows,
        array $repeatingGroups,
        array $uselessColumns
    ): array {
        $allRepeating = array_merge(...array_values($repeatingGroups));
        $identified = array_merge($allRepeating, $uselessColumns);
        $unidentified = array_diff($headers, $identified);

        return [
            'total_columns' => count($headers),
            'total_rows' => count($rows),
            'repeating_groups' => $repeatingGroups,
            'useless_columns' => $uselessColumns,
            'unidentified' => array_values($unidentified),
            'recommendation' => count($repeatingGroups) > 0
                ? 'decompose'
                : 'single_table',
            'estimated_tables' => 1 + count($repeatingGroups),
        ];
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function normalizeHeader(string $header): string
    {
        $lower = mb_strtolower($header);

        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $lower);

        return $transliterated !== false ? $transliterated : $lower;
    }

    /**
     * Estime le nombre de lignes après pivot d'un groupe répété.
     *
     * @param  array<int, array<mixed>>  $rows
     * @param  array<int, string>  $columns
     */
    private function estimateExpandedRows(array $rows, array $columns): int
    {
        $count = 0;
        foreach ($rows as $row) {
            foreach ($columns as $col) {
                $value = $row[$col] ?? null;
                if ($value !== null && $value !== '') {
                    $count++;
                }
            }
        }

        return $count;
    }
}
