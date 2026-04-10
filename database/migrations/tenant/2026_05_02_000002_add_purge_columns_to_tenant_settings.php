<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            // Nombre de jours après lesquels les documents soft-deleted sont définitivement supprimés.
            // null = jamais purger automatiquement.
            $table->unsignedSmallInteger('ged_deleted_retention_days')->nullable()->after('nas_ged_last_sync_errors');

            // Nombre maximum de versions archivées à conserver par document.
            // Les plus anciennes sont purgées lors du passage de la commande.
            // null = conserver toutes les versions.
            $table->unsignedSmallInteger('ged_versions_max_count')->nullable()->after('ged_deleted_retention_days');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['ged_deleted_retention_days', 'ged_versions_max_count']);
        });
    }
};
