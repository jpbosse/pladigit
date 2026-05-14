<?php

namespace App\Jobs;

use App\Enums\DatagridColumnType;
use App\Models\Platform\Organization;
use App\Models\Tenant\DatagridPermission;
use App\Services\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ImportDatagridJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800; // 30 min — fichiers volumineux

    private const CHUNK_SIZE = 500;

    private const REDIS_TTL = 7200; // 2h

    public function __construct(
        private readonly string $orgSlug,
        private readonly string $importId,        // UUID unique pour suivre la progression
        private readonly string $tempStoragePath, // chemin dans storage/app/private/
        private readonly string $mode,            // 'new' | 'update'
        private readonly string $mysqlTable,
        private readonly int $datagridTableId,
        private readonly array $columnDefs,       // [{name, type}, ...]
        private readonly string $updateMode,      // 'append' | 'replace' (mode update)
        private readonly bool $fileHasHeader,
        /** @phpstan-ignore property.onlyWritten */
        private readonly array $columnMapping,    // réservé pour usage futur (mode update étendu)
        private readonly string $defaultVisibility, // 'public'|'restricted'|'private' (mode new)
    ) {}

    public function handle(TenantManager $tenantManager): void
    {
        $this->setProgress('running', 0, 0);

        try {
            $org = Organization::where('slug', $this->orgSlug)->firstOrFail();
            $tenantManager->connectTo($org);

            $filePath = Storage::disk('local')->path($this->tempStoragePath);

            if (! file_exists($filePath)) {
                $this->fail('Fichier temporaire introuvable : '.$filePath);

                return;
            }

            // Compter les lignes totales pour la barre de progression
            $total = $this->countLines($filePath);
            $this->setProgress('running', 0, $total);

            if ($this->mode === 'update' && $this->updateMode === 'replace') {
                DB::connection('tenant')->table($this->mysqlTable)->truncate();
            }

            $processed = 0;
            $buffer = [];

            foreach ($this->readRows($filePath) as $lineNumber => $line) {
                // Ignorer la ligne d'en-tête
                if ($lineNumber === 0 && $this->fileHasHeader) {
                    continue;
                }

                $row = $this->buildRow($line);

                if (empty(array_filter($row, fn ($v) => $v !== null))) {
                    continue; // ligne vide
                }

                $row['created_at'] = now();
                $row['updated_at'] = now();
                $buffer[] = $row;

                if (count($buffer) >= self::CHUNK_SIZE) {
                    DB::connection('tenant')->table($this->mysqlTable)->insert($buffer);
                    $processed += count($buffer);
                    $buffer = [];
                    $this->setProgress('running', $processed, $total);
                }
            }

            // Vider le buffer restant
            if (! empty($buffer)) {
                DB::connection('tenant')->table($this->mysqlTable)->insert($buffer);
                $processed += count($buffer);
            }

            // Visibilité initiale (mode new uniquement)
            if ($this->mode === 'new') {
                $this->applyVisibility();
            }

            // Nettoyage fichier temp
            Storage::disk('local')->delete($this->tempStoragePath);

            $this->setProgress('done', $processed, $total);

            Log::info('ImportDatagridJob terminé', [
                'import_id' => $this->importId,
                'table' => $this->mysqlTable,
                'rows' => $processed,
            ]);

        } catch (\Throwable $e) {
            Log::error('ImportDatagridJob échoué', [
                'import_id' => $this->importId,
                'error' => $e->getMessage(),
            ]);
            $this->setProgress('error', 0, 0, $e->getMessage());
            throw $e;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildRow(array $line): array
    {
        $data = [];

        foreach ($this->columnDefs as $idx => $colDef) {
            $raw = isset($line[$idx]) && $line[$idx] !== '' ? (string) $line[$idx] : null;
            $data[$colDef['name']] = match ($colDef['type']) {
                DatagridColumnType::DATE->value => $raw !== null ? $this->normalizeDate($raw) : null,
                DatagridColumnType::BOOLEAN->value => $raw !== null ? $this->normalizeBoolean($raw) : null,
                DatagridColumnType::POSTAL_CODE->value => $raw !== null ? $this->normalizePostalCode($raw) : null,
                DatagridColumnType::SIRET->value => $raw !== null ? $this->normalizeSiret($raw) : null,
                DatagridColumnType::PHONE->value => $raw !== null ? $this->normalizePhone($raw) : null,
                default => $raw,
            };
        }

        return $data;
    }

    private function applyVisibility(): void
    {
        if ($this->defaultVisibility === 'public') {
            DatagridPermission::create([
                'datagrid_table_id' => $this->datagridTableId,
                'column_name' => null,
                'subject_type' => 'role',
                'subject_id' => null,
                'subject_role' => 'user',
                'can_read' => true,
                'can_write' => false,
                'can_delete' => false,
                'can_export' => true,
                'denied' => false,
            ]);
        } elseif ($this->defaultVisibility === 'private') {
            DatagridPermission::create([
                'datagrid_table_id' => $this->datagridTableId,
                'column_name' => null,
                'subject_type' => 'role',
                'subject_id' => null,
                'subject_role' => 'user',
                'can_read' => false,
                'can_write' => false,
                'can_delete' => false,
                'can_export' => false,
                'denied' => true,
            ]);
        }
        // 'restricted' → aucune règle
    }

    // ── Lecture fichier (CSV ou tableur) ──────────────────────────────────────

    /**
     * Générateur de lignes — détecte le format selon l'extension.
     * Yield [lineIndex => array<int, string|null>]
     *
     * @return \Generator<int, array<int, string|null>>
     */
    private function readRows(string $filePath): \Generator
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            yield from $this->readCsvRows($filePath);
        } else {
            yield from $this->readSpreadsheetRows($filePath);
        }
    }

    /**
     * Lecture CSV ligne par ligne avec détection du séparateur.
     *
     * @return \Generator<int, array<int, string|null>>
     */
    private function readCsvRows(string $filePath): \Generator
    {
        $sep = $this->detectSeparator($filePath);
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Impossible d\'ouvrir le fichier CSV.');
        }

        $i = 0;
        while (($line = fgetcsv($handle, 0, $sep)) !== false) {
            yield $i => array_map(fn ($v) => $v === '' ? null : $v, $line);
            $i++;
        }
        fclose($handle);
    }

    /**
     * Lecture XLSX/XLS/ODS via PhpSpreadsheet (chargement complet en mémoire).
     * Utilisé uniquement pour les fichiers non-CSV.
     *
     * @return \Generator<int, array<int, string|null>>
     */
    private function readSpreadsheetRows(string $filePath): \Generator
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $i = 0;
        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $val = $cell->getCalculatedValue();
                $cells[] = ($val === null || $val === '') ? null : (string) $val;
            }
            yield $i => $cells;
            $i++;
        }

        $spreadsheet->disconnectWorksheets();
    }

    private function countLines(string $filePath): int
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $count = 0;
            $handle = fopen($filePath, 'r');
            if ($handle === false) {
                return 0;
            }
            while (fgets($handle) !== false) {
                $count++;
            }
            fclose($handle);

            return $this->fileHasHeader ? max(0, $count - 1) : $count;
        }

        // Pour tableurs : on charge pour compter
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $count = $spreadsheet->getActiveSheet()->getHighestDataRow();
            $spreadsheet->disconnectWorksheets();

            return $this->fileHasHeader ? max(0, $count - 1) : $count;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function detectSeparator(string $filePath): string
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return ',';
        }
        $firstLine = fgets($handle);
        fclose($handle);

        $separators = [';', ',', "\t", '|'];
        $counts = array_map(fn ($s) => substr_count((string) $firstLine, $s), $separators);
        $max = array_keys($counts, max($counts));

        return $separators[$max[0]] ?? ',';
    }

    private function setProgress(string $status, int $processed, int $total, string $error = ''): void
    {
        Cache::put('datagrid_import:'.$this->importId, [
            'status' => $status,  // 'pending'|'running'|'done'|'error'
            'processed' => $processed,
            'total' => $total,
            'error' => $error,
        ], self::REDIS_TTL);
    }

    // ── Normalisations (identiques à ImportWizard) ────────────────────────────

    private function normalizeDate(string $value): ?string
    {
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (is_numeric($value) && (int) $value > 1000) {
            try {
                $dt = Date::excelToDateTimeObject((int) $value);

                return $dt->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return $value ?: null;
    }

    private function normalizeBoolean(string $value): int
    {
        return in_array(strtolower(trim($value)), [
            '1', '-1', 'true', 'oui', 'yes', 'vrai', 'o', 'y', 'v',
        ], true) ? 1 : 0;
    }

    private function normalizePostalCode(string $value): string
    {
        $value = preg_replace('/\.0+$/', '', trim($value));

        return str_pad($value, 5, '0', STR_PAD_LEFT);
    }

    private function normalizeSiret(string $value): string
    {
        $value = preg_replace('/\D/', '', trim($value));

        return str_pad($value, 14, '0', STR_PAD_LEFT);
    }

    private function normalizePhone(string $value): string
    {
        $value = trim($value);
        $prefix = str_starts_with($value, '+') ? '+' : '';
        $digits = preg_replace('/\D/', '', $value);

        return $prefix.$digits;
    }
}
