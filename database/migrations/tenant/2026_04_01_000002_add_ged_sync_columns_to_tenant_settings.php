<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        // nas_ged_root_path est ajouté par 2026_05_01_000003 qui s'exécute APRÈS cette migration.
        // Si la colonne n'existe pas encore (migrate:fresh), on saute : 2026_05_01_000003
        // inclut déjà les colonnes sync ci-dessous.
        if (! Schema::connection('tenant')->hasColumn('tenant_settings', 'nas_ged_root_path')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('tenant_settings', 'nas_ged_last_sync_at')) {
            return;
        }

        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->timestamp('nas_ged_last_sync_at')->nullable()->after('nas_ged_root_path');
            $table->unsignedSmallInteger('nas_ged_sync_interval_minutes')->default(60)->after('nas_ged_last_sync_at');
            // Erreurs de la dernière sync (fichiers ignorés : MIME interdit, taille dépassée…)
            $table->json('nas_ged_last_sync_errors')->nullable()->after('nas_ged_sync_interval_minutes');
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasColumn('tenant_settings', 'nas_ged_last_sync_at')) {
            return;
        }

        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn([
                'nas_ged_last_sync_at',
                'nas_ged_sync_interval_minutes',
                'nas_ged_last_sync_errors',
            ]);
        });
    }
};
