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
        // ── 1. Permissions par sujet (rôle / direction / service) ────────────
        Schema::connection('tenant')->create('ged_folder_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')
                ->constrained('ged_folders')
                ->onDelete('cascade');

            // Type de sujet : 'role' | 'direction' | 'service'
            $table->enum('subject_type', ['role', 'direction', 'service']);

            // Pour role  : null (utilise subject_role)
            // Pour direction/service : ID du département
            $table->unsignedBigInteger('subject_id')->nullable();

            // Pour role : valeur du rôle (ex: 'resp_service')
            $table->string('subject_role', 50)->nullable();

            // Niveau d'accès
            $table->enum('level', ['none', 'view', 'download', 'upload', 'admin'])
                ->default('view');

            $table->timestamps();

            // Un sujet ne peut avoir qu'une seule permission par dossier
            $table->unique(
                ['folder_id', 'subject_type', 'subject_id', 'subject_role'],
                'ged_folder_permissions_unique'
            );

            $table->index('folder_id');
        });

        // ── 2. Permissions par utilisateur individuel ────────────────────────
        Schema::connection('tenant')->create('ged_folder_user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')
                ->constrained('ged_folders')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->enum('level', ['none', 'view', 'download', 'upload', 'admin'])
                ->default('view');

            $table->timestamps();

            $table->unique(['folder_id', 'user_id']);
            $table->index('folder_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('ged_folder_user_permissions');
        Schema::connection('tenant')->dropIfExists('ged_folder_permissions');
    }
};
