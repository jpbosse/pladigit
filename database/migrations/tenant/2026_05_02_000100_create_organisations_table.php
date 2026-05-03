<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organisations référencées dans l'annuaire des personnalités.
 * Partagée entre toutes les vues DataGrid du tenant.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 255);
            $table->string('type', 50); // OrganisationType enum
            $table->string('siret', 14)->nullable();
            $table->text('adresse')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('organisations');
    }
};
