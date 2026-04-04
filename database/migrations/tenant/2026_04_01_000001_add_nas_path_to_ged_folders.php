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
        // La table peut ne pas encore exister si migrate:fresh tourne avant
        // 2026_05_01_000001_create_ged_folders_table (qui l'inclut déjà).
        // Dans ce cas on saute : la colonne sera présente à la création.
        if (! Schema::connection('tenant')->hasTable('ged_folders')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('ged_folders', 'nas_path')) {
            return;
        }

        Schema::connection('tenant')->table('ged_folders', function (Blueprint $table) {
            // Chemin exact sur le NAS/filesystem d'origine.
            // Null pour les dossiers créés manuellement dans l'interface.
            // Sert de clé lors de la resynchronisation pour ne pas recréer les dossiers existants.
            $table->string('nas_path', 500)->nullable()->after('path');
            $table->index('nas_path', 'ged_folders_nas_path_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::connection('tenant')->hasTable('ged_folders')) {
            return;
        }

        if (! Schema::connection('tenant')->hasColumn('ged_folders', 'nas_path')) {
            return;
        }

        Schema::connection('tenant')->table('ged_folders', function (Blueprint $table) {
            $table->dropIndex('ged_folders_nas_path_idx');
            $table->dropColumn('nas_path');
        });
    }
};
