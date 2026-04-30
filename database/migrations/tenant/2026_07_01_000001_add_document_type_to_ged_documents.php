<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichissement sémantique de la table ged_documents.
 *
 * Ajoute la couche "source de vérité documentaire" (Niveau 2, ADR-038) :
 *   - document_type  : classification officielle (délibération, arrêté, …)
 *   - reference      : numéro de référence normalisé (DEL-2026-042)
 *   - document_date  : date officielle de l'acte (≠ created_at)
 *   - department_id  : service émetteur (FK departments)
 *   - template_id    : modèle utilisé pour la création (nullable)
 *   - object         : objet court du document (255 chars max)
 *   - tags           : mots-clés JSON pour la recherche facettée
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('ged_documents', function (Blueprint $table) {
            // Type documentaire officiel — nullable pour compatibilité ascendante
            // (les documents existants sans type gardent null = type "non classifié")
            $table->string('document_type', 50)->nullable()->after('name');

            // Référence normalisée générée automatiquement ou saisie manuellement
            // Format : {PREFIX}-{AAAA}-{NNN}  ex: DEL-2026-042
            $table->string('reference', 30)->nullable()->after('document_type');

            // Date officielle de l'acte — peut différer de la date d'upload
            $table->date('document_date')->nullable()->after('reference');

            // Objet / intitulé court du document (utile pour les délibérations)
            $table->string('object', 255)->nullable()->after('document_date');

            // Service / direction émetteur
            $table->foreignId('department_id')
                ->nullable()
                ->after('object')
                ->constrained('departments')
                ->nullOnDelete();

            // Modèle de document utilisé pour la création (nullable = upload libre)
            // ⚠ La contrainte FK est ajoutée par 000003 (ged_document_templates n'existe pas encore)
            $table->unsignedBigInteger('template_id')->nullable()->after('department_id');

            // Mots-clés JSON pour la recherche facettée
            // Ex: ["budget", "investissement", "voirie"]
            $table->json('tags')->nullable()->after('template_id');

            // Index sur les colonnes de filtrage fréquent
            $table->index('document_type');
            $table->index('reference');
            $table->index('document_date');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('ged_documents', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['document_type']);
            $table->dropIndex(['reference']);
            $table->dropIndex(['document_date']);
            $table->dropIndex(['department_id']);
            $table->dropColumn([
                'document_type',
                'reference',
                'document_date',
                'object',
                'department_id',
                'template_id',
                'tags',
            ]);
        });
    }
};
