<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personnes physiques de l'annuaire — entité partagée inter-DataGrids.
 *
 * Une même personne n'est jamais dupliquée, même si elle apparaît
 * dans plusieurs grilles (élus, commissions, associations…).
 *
 * coordonnees_priv : accès restreint, données RGPD sensibles.
 * base_legale      : fondement juridique du traitement (art. 6 RGPD).
 * opposition       : droit d'opposition exercé (art. 21 RGPD).
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('personnes', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('photo', 500)->nullable();
            $table->json('coordonnees_pro')->nullable();
            $table->json('coordonnees_priv')->nullable();
            $table->string('base_legale', 50); // PersonneBaseLegale enum
            $table->boolean('opposition')->default(false);
            $table->date('date_opposition')->nullable();
            $table->string('visibilite', 20)->default('interne'); // PersonneVisibilite enum
            $table->date('date_revision')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('nom');
            $table->index('visibilite');
            $table->index('opposition');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('personnes');
    }
};
