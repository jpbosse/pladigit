<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        // Migrer assistance et enterprise vers partenaire
        DB::connection($this->connection)
            ->table('organizations')
            ->whereIn('plan', ['assistance', 'enterprise'])
            ->update(['plan' => 'partenaire']);

        Schema::connection($this->connection)
            ->table('organizations', function (Blueprint $table) {
                $table->enum('plan', ['communautaire', 'partenaire'])
                    ->default('communautaire')
                    ->change();
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)
            ->table('organizations', function (Blueprint $table) {
                $table->enum('plan', ['communautaire', 'assistance', 'enterprise'])
                    ->default('communautaire')
                    ->change();
            });

        // Rollback impossible à distinguer (assistance vs enterprise fusionnés)
        // On remet tout en assistance par convention
        DB::connection($this->connection)
            ->table('organizations')
            ->where('plan', 'partenaire')
            ->update(['plan' => 'assistance']);
    }
};
