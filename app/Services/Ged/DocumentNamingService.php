<?php

namespace App\Services\Ged;

use App\Enums\GedDocumentType;
use App\Models\Tenant\Department;
use App\Models\Tenant\GedDocumentSequence;
use App\Models\Tenant\GedDocumentTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service de nommage automatique des documents officiels (ADR-038).
 *
 * Génère la référence normalisée et le nom de fichier selon :
 *   - le type documentaire
 *   - le modèle choisi (name_pattern)
 *   - le compteur séquentiel annuel
 *   - l'objet et le service émetteur
 *
 * Format de référence : {PREFIX}-{AAAA}-{NNN}
 * Exemples :
 *   DEL-2026-042
 *   ARR-2026-015
 *   CR-2026-007
 *
 * Le compteur est incrémenté de façon atomique (transaction + SELECT FOR UPDATE)
 * pour éviter les doublons en cas d'uploads simultanés.
 */
class DocumentNamingService
{
    /**
     * Génère la référence officielle du document (DEL-2026-042).
     *
     * @param  GedDocumentType  $type    Type documentaire
     * @param  int|null         $year    Année (null = année courante)
     * @param  bool             $dryRun  Si true, n'incrémente pas le compteur
     */
    public function generateReference(
        GedDocumentType $type,
        ?int $year = null,
        bool $dryRun = false,
    ): string {
        $year ??= (int) now()->format('Y');
        $seq = $dryRun
            ? $this->peekNextSequence($type, $year)
            : $this->nextSequence($type, $year);

        return sprintf('%s-%d-%03d', $type->prefix(), $year, $seq);
    }

    /**
     * Génère le nom de fichier complet selon le modèle ou la convention par défaut.
     *
     * @param  GedDocumentType           $type        Type documentaire
     * @param  string                    $reference   Référence déjà générée (DEL-2026-042)
     * @param  string|null               $object      Objet du document (pour le slug)
     * @param  Department|null           $department  Service émetteur
     * @param  GedDocumentTemplate|null  $template    Modèle choisi (optionnel)
     * @param  string                    $extension   Extension du fichier (.odt, .docx, .pdf…)
     */
    public function generateFileName(
        GedDocumentType $type,
        string $reference,
        ?string $object = null,
        ?Department $department = null,
        ?GedDocumentTemplate $template = null,
        string $extension = 'odt',
    ): string {
        $extension = ltrim(strtolower($extension), '.');

        // Si un modèle avec patron est défini, on l'applique
        if ($template && ! empty($template->name_pattern)) {
            $vars = [
                'PREFIX' => $type->prefix(),
                'YEAR'   => now()->format('Y'),
                'SEQ'    => substr($reference, strrpos($reference, '-') + 1),
                'DEPT'   => $department ? Str::slug($department->name) : '',
                'SLUG'   => $object ? Str::slug($object, '-', 'fr') : '',
            ];

            $name = $template->applyPattern($vars);

            if (! empty($name)) {
                return $name.'.'.$extension;
            }
        }

        // Convention par défaut : {REFERENCE}[-{slug-objet}].{ext}
        $parts = [$reference];

        if (! empty($object)) {
            $slug = Str::slug($object, '-', 'fr');
            // Tronquer à 50 caractères pour éviter les noms trop longs
            if (strlen($slug) > 50) {
                $slug = substr($slug, 0, 50);
                // Couper au dernier tiret pour ne pas couper un mot
                $lastDash = strrpos($slug, '-');
                if ($lastDash > 20) {
                    $slug = substr($slug, 0, $lastDash);
                }
            }
            $parts[] = $slug;
        }

        return implode('-', $parts).'.'.$extension;
    }

    /**
     * Génère référence + nom de fichier en une seule opération atomique.
     *
     * @param  array{
     *   type: GedDocumentType,
     *   object?: string|null,
     *   department?: Department|null,
     *   template?: GedDocumentTemplate|null,
     *   extension?: string,
     *   year?: int|null,
     * }  $params
     *
     * @return array{reference: string, filename: string}
     */
    public function generate(array $params): array
    {
        $type       = $params['type'];
        $object     = $params['object'] ?? null;
        $department = $params['department'] ?? null;
        $template   = $params['template'] ?? null;
        $extension  = $params['extension'] ?? 'odt';
        $year       = $params['year'] ?? null;

        $reference = $this->generateReference($type, $year);
        $filename  = $this->generateFileName($type, $reference, $object, $department, $template, $extension);

        return compact('reference', 'filename');
    }

    // ── Gestion du compteur séquentiel ───────────────────────

    /**
     * Incrémente et retourne le prochain numéro de séquence (atomique).
     */
    private function nextSequence(GedDocumentType $type, int $year): int
    {
        return DB::connection('tenant')->transaction(function () use ($type, $year) {
            /** @var object|null $row */
            $row = DB::connection('tenant')
                ->table('ged_document_sequences')
                ->where('document_type', $type->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::connection('tenant')
                    ->table('ged_document_sequences')
                    ->insert([
                        'document_type' => $type->value,
                        'year'          => $year,
                        'last_sequence' => 1,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);

                return 1;
            }

            $next = $row->last_sequence + 1;

            DB::connection('tenant')
                ->table('ged_document_sequences')
                ->where('document_type', $type->value)
                ->where('year', $year)
                ->update([
                    'last_sequence' => $next,
                    'updated_at'    => now(),
                ]);

            return $next;
        });
    }

    /**
     * Consulte le prochain numéro sans l'incrémenter (dry run / preview).
     */
    private function peekNextSequence(GedDocumentType $type, int $year): int
    {
        $row = DB::connection('tenant')
            ->table('ged_document_sequences')
            ->where('document_type', $type->value)
            ->where('year', $year)
            ->first();

        return $row ? $row->last_sequence + 1 : 1;
    }

    /**
     * Retourne la prochaine référence en preview (sans consommer le compteur).
     * Utilisé dans les formulaires de création pour afficher la référence à venir.
     */
    public function previewReference(GedDocumentType $type, ?int $year = null): string
    {
        return $this->generateReference($type, $year, dryRun: true);
    }
}
