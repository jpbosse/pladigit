<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mandats, fonctions et titres d'une personne au sein d'une organisation.
 *
 * Une personne peut avoir plusieurs rôles simultanés (élu + associatif + commission).
 * Les colonnes "rôles" importées depuis Excel sont transposées en lignes ici,
 * évitant la prolifération de colonnes dans la table personnes.
 *
 * rang_protocolaire : entier pour trier l'ordre de préséance (protocole).
 * civilite_contexte : formule d'adresse contextuelle ("Monsieur le Maire").
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('roles_titres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personne_id')->constrained('personnes')->cascadeOnDelete();
            $table->string('categorie', 30); // RoleTitreCategorie enum
            $table->string('fonction', 255);
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('civilite_contexte', 100)->nullable();
            $table->unsignedSmallInteger('rang_protocolaire')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->string('statut', 20)->default('actif'); // RoleTitreStatut enum
            $table->timestamps();
            $table->softDeletes();

            $table->index('personne_id');
            $table->index('categorie');
            $table->index('statut');
            $table->index('organisation_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('roles_titres');
    }
};
