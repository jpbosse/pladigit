<?php

namespace Database\Seeders;

use App\Enums\GedDocumentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Modèles de documents par défaut pour les collectivités territoriales.
 *
 * Ce seeder est idempotent : il n'écrase pas les modèles déjà personnalisés
 * par l'admin tenant. Il insère uniquement si la table est vide.
 *
 * Exécution :
 *   php artisan db:seed --class=GedDocumentTemplateSeeder --database=tenant
 *
 * Ou via TenantManager dans un Command :
 *   $tenantManager->connectTo($org);
 *   (new GedDocumentTemplateSeeder)->run();
 */
class GedDocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent — ne pas re-seeder si des modèles existent déjà
        $existing = DB::connection('tenant')
            ->table('ged_document_templates')
            ->whereNull('deleted_at')
            ->count();

        if ($existing > 0) {
            $this->command?->info('  ⏭  Modèles de documents déjà présents — seeder ignoré.');

            return;
        }

        // L'admin tenant (id=1) est utilisé comme créateur par convention
        // (sera l'admin de l'organisation lors de l'installation)
        $adminId = DB::connection('tenant')
            ->table('users')
            ->orderBy('id')
            ->value('id') ?? 1;

        $now = now();

        $templates = [
            // ── Actes réglementaires ──────────────────────────
            [
                'document_type'     => GedDocumentType::DELIBERATION->value,
                'name'              => 'Délibération du conseil',
                'description'       => 'Acte réglementaire adopté en séance du conseil municipal/communautaire.',
                'name_pattern'      => '{PREFIX}-{YEAR}-{SEQ}-{SLUG}',
                'required_fields'   => json_encode(['object', 'document_date', 'department_id']),
                'is_active'         => true,
                'sort_order'        => 10,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'document_type'     => GedDocumentType::ARRETE->value,
                'name'              => 'Arrêté municipal',
                'description'       => 'Acte individuel ou réglementaire pris par le Maire.',
                'name_pattern'      => '{PREFIX}-{YEAR}-{SEQ}-{SLUG}',
                'required_fields'   => json_encode(['object', 'document_date']),
                'is_active'         => true,
                'sort_order'        => 20,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'document_type'     => GedDocumentType::DECISION->value,
                'name'              => 'Décision du Maire',
                'description'       => 'Décision prise par délégation du conseil municipal (art. L.2122-22 CGCT).',
                'name_pattern'      => '{PREFIX}-{YEAR}-{SEQ}',
                'required_fields'   => json_encode(['object', 'document_date']),
                'is_active'         => true,
                'sort_order'        => 30,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],

            // ── Comptes rendus & PV ───────────────────────────
            [
                'document_type'     => GedDocumentType::COMPTE_RENDU->value,
                'name'              => 'Compte-rendu de réunion',
                'description'       => 'Compte-rendu synthétique d\'une réunion interne ou de commission.',
                'name_pattern'      => '{PREFIX}-{YEAR}-{SEQ}-{SLUG}',
                'required_fields'   => json_encode(['object', 'document_date', 'department_id']),
                'is_active'         => true,
                'sort_order'        => 40,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'document_type'     => GedDocumentType::PROCES_VERBAL->value,
                'name'              => 'Procès-verbal du conseil',
                'description'       => 'PV officiel de séance du conseil municipal, communautaire ou intercommunal.',
                'name_pattern'      => '{PREFIX}-{YEAR}-{SEQ}-{SLUG}',
                'required_fields'   => json_encode(['object', 'document_date']),
                'is_active'         => true,
                'sort_order'        => 50,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],

            // ── Correspondance ────────────────────────────────
            [
                'document_type'     => GedDocumentType::COURRIER->value,
                'name'              => 'Courrier officiel',
                'description'       => 'Courrier à en-tête de la collectivité (départ ou arrivée).',
                'name_pattern'      => '{PREFIX}-{YEAR}-{SEQ}',
                'required_fields'   => json_encode(['object', 'document_date']),
                'is_active'         => true,
                'sort_order'        => 60,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'document_type'     => GedDocumentType::NOTE_SERVICE->value,
                'name'              => 'Note de service',
                'description'       => 'Note interne à destination des agents.',
                'name_pattern'      => '{PREFIX}-{YEAR}-{SEQ}-{DEPT}',
                'required_fields'   => json_encode(['object', 'document_date', 'department_id']),
                'is_active'         => true,
                'sort_order'        => 70,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'document_type'     => GedDocumentType::RAPPORT->value,
                'name'              => 'Rapport au conseil',
                'description'       => 'Rapport préparatoire aux délibérations du conseil.',
                'name_pattern'      => '{PREFIX}-{YEAR}-{SEQ}-{SLUG}',
                'required_fields'   => json_encode(['object', 'document_date', 'department_id']),
                'is_active'         => true,
                'sort_order'        => 80,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],

            // ── Marchés & contrats ────────────────────────────
            [
                'document_type'     => GedDocumentType::MARCHE->value,
                'name'              => 'Marché public',
                'description'       => 'Contrat de commande publique (fournitures, services, travaux).',
                'name_pattern'      => '{PREFIX}-{YEAR}-{SEQ}',
                'required_fields'   => json_encode(['object', 'document_date', 'department_id']),
                'is_active'         => true,
                'sort_order'        => 90,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [
                'document_type'     => GedDocumentType::CONVENTION->value,
                'name'              => 'Convention',
                'description'       => 'Convention avec un partenaire institutionnel, associatif ou privé.',
                'name_pattern'      => '{PREFIX}-{YEAR}-{SEQ}-{SLUG}',
                'required_fields'   => json_encode(['object', 'document_date']),
                'is_active'         => true,
                'sort_order'        => 100,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],

            // ── Budget ────────────────────────────────────────
            [
                'document_type'     => GedDocumentType::BUDGET->value,
                'name'              => 'Document budgétaire',
                'description'       => 'Budget primitif, budget supplémentaire, compte administratif.',
                'name_pattern'      => '{PREFIX}-{YEAR}-{SLUG}',
                'required_fields'   => json_encode(['object', 'document_date']),
                'is_active'         => true,
                'sort_order'        => 110,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],

            // ── Divers ────────────────────────────────────────
            [
                'document_type'     => GedDocumentType::AUTRE->value,
                'name'              => 'Document divers',
                'description'       => 'Document ne correspondant pas aux types officiels.',
                'name_pattern'      => null,  // nommage libre
                'required_fields'   => json_encode([]),
                'is_active'         => true,
                'sort_order'        => 999,
                'created_by'        => $adminId,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
        ];

        DB::connection('tenant')
            ->table('ged_document_templates')
            ->insert($templates);

        $this->command?->info('  ✓ '.count($templates).' modèles de documents insérés.');
    }
}
