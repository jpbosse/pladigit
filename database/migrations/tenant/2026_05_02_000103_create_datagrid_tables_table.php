<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Définition des grilles DataGrid — méta-table qui décrit chaque vue no-code.
 *
 * mysql_table      : nom de la table MySQL tenant sous-jacente (ex: "personnes").
 * is_persons_view  : indique que cette grille est une vue sur personnes+roles_titres.
 *                    L'import transposera automatiquement les colonnes "rôles" en lignes roles_titres.
 * role_categories  : filtre JSON sur les catégories RoleTitreCategorie visibles dans cette vue.
 * has_rgpd         : active l'audit trail complet et le registre des traitements.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('datagrid_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique(); // identifiant technique
            $table->string('label', 255); // libellé affiché
            $table->text('description')->nullable();
            $table->string('mysql_table', 100);
            $table->boolean('has_rgpd')->default(false);
            $table->boolean('is_persons_view')->default(false);
            $table->json('role_categories')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('mysql_table');
            $table->index('created_by');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('datagrid_tables');
    }
};
