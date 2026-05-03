<?php

namespace App\Models\Tenant;

use App\Enums\GedDocumentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Compteur séquentiel annuel par type documentaire.
 *
 * Utilisé par DocumentNamingService pour générer les références
 * DEL-2026-001, DEL-2026-002, etc. de façon atomique.
 *
 * @property int $id
 * @property string $document_type
 * @property int $year
 * @property int $last_sequence
 */
class GedDocumentSequence extends Model
{
    protected $connection = 'tenant';

    protected $table = 'ged_document_sequences';

    protected $fillable = [
        'document_type',
        'year',
        'last_sequence',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_sequence' => 'integer',
    ];

    public function documentTypeEnum(): GedDocumentType
    {
        return GedDocumentType::from($this->document_type);
    }
}
