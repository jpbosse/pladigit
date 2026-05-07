<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les libellés métier pour les colonnes booléennes.
 *
 * label_true  : libellé affiché quand la valeur est vraie  (ex: "Occupé", "M", "Actif")
 * label_false : libellé affiché quand la valeur est fausse (ex: "Libre",  "F", "Inactif")
 *
 * Si NULL → affichage par défaut "Oui" / "Non".
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('datagrid_columns', function (Blueprint $table) {
            $table->string('label_true', 50)->nullable()->after('options')
                ->comment('Libellé affiché quand booléen = vrai (défaut : Oui)');
            $table->string('label_false', 50)->nullable()->after('label_true')
                ->comment('Libellé affiché quand booléen = faux (défaut : Non)');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('datagrid_columns', function (Blueprint $table) {
            $table->dropColumn(['label_true', 'label_false']);
        });
    }
};
