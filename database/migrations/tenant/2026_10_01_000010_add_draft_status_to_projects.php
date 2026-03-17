<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function connection()
    {
        return 'tenant';
    }

    /**
     * Ajoute le statut 'draft' (brouillon) aux projets.
     * Utilise ALTER TABLE direct car ->change() sur ENUM nécessite doctrine/dbal.
     */
    public function up(): void
    {
        // Modifier l'enum status pour ajouter 'draft' en première position
        DB::connection('tenant')->statement(
            "ALTER TABLE `projects` MODIFY COLUMN `status` ENUM('draft', 'active', 'on_hold', 'completed', 'archived') NOT NULL DEFAULT 'active'"
        );
    }

    public function down(): void
    {
        // Remettre l'enum sans 'draft'
        DB::connection('tenant')->statement(
            "ALTER TABLE `projects` MODIFY COLUMN `status` ENUM('active', 'on_hold', 'completed', 'archived') NOT NULL DEFAULT 'active'"
        );
    }
};
