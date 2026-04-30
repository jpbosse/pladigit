<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles de documents par type (ADR-038 — Niveau 2).
 *
 * Un modèle définit, pour un type documentaire donné :
 *   - Le patron de nommage automatique (avec variables)
 *   - Le dossier GED cible par défaut
 *   - Les métadonnées obligatoires à saisir lors de la création
 *   - Le fichier gabarit (.odt/.docx) optionnel pour Collabora
 *
 * Patron de nommage — variables disponibles :
 *   {PREFIX}   → préfixe du type (DEL, ARR, CR…)
 *   {YEAR}     → année sur 4 chiffres
 *   {SEQ}      → numéro séquentiel sur 3 chiffres (réinitialisé par année)
 *   {DEPT}     → code ou slug du service émetteur
 *   {SLUG}     → slug de l'objet du document
 *
 * Exemple de name_pattern :
 *   "{PREFIX}-{YEAR}-{SEQ}"           → DEL-2026-042
 *   "{PREFIX}-{YEAR}-{SEQ}-{DEPT}"    → ARR-2026-015-rh
 *   "{PREFIX}-{YEAR}-{SEQ}-{SLUG}"    → CR-2026-007-conseil-juin
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('ged_document_templates', function (Blueprint $table) {
            $table->id();

            // Type documentaire couvert par ce modèle
            $table->string('document_type', 50);

            // Nom affiché du modèle (ex: "Délibération budget", "Arrêté RH")
            $table->string('name', 255);

            // Description facultative visible dans l'interface
            $table->text('description')->nullable();

            // Patron de nommage — ex: "{PREFIX}-{YEAR}-{SEQ}-{SLUG}"
            // NULL = nommage manuel libre
            $table->string('name_pattern', 100)->nullable();

            // Dossier GED cible par défaut (facultatif — l'agent peut surcharger)
            $table->foreignId('default_folder_id')
                ->nullable()
                ->constrained('ged_folders')
                ->nullOnDelete();

            // Service émetteur par défaut (facultatif)
            $table->foreignId('default_department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            // Fichier gabarit Collabora (.odt/.docx) pré-rempli
            // Chemin disk — NULL si aucun gabarit
            $table->string('template_file_path', 1000)->nullable();

            // Métadonnées obligatoires JSON — liste des champs requis à la création
            // Ex: ["object", "document_date", "department_id"]
            $table->json('required_fields')->nullable();

            // Actif / inactif (soft-disable sans supprimer)
            $table->boolean('is_active')->default(true);

            // Ordre d'affichage dans les selects
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Créateur (admin tenant qui a défini ce modèle)
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
            $table->softDeletes();

            $table->index('document_type');
            $table->index('is_active');
            $table->index('sort_order');
        });

        // Compteur séquentiel par type et par année — utilisé par DocumentNamingService
        // pour générer DEL-2026-001, DEL-2026-002, etc.
        Schema::connection('tenant')->create('ged_document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();

            // Une seule entrée par type + année
            $table->unique(['document_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('ged_document_sequences');
        Schema::connection('tenant')->dropIfExists('ged_document_templates');
    }
};
