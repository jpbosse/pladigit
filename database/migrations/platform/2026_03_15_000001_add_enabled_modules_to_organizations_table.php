<?php

use App\Enums\ModuleKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql'; // pladigit_platform

    public function up(): void
    {
        Schema::connection($this->connection)
            ->table('organizations', function (Blueprint $table) {
                $table->json('enabled_modules')
                    ->nullable()
                    ->after('plan')
                    ->comment('Liste des modules actifs — voir App\\Enums\\ModuleKey');
            });

        // Activer la photothèque sur toutes les orgs existantes (Phase 3 en cours)
        \DB::connection($this->connection)
            ->table('organizations')
            ->update([
                'enabled_modules' => json_encode([ModuleKey::MEDIA->value]),
            ]);
    }

    public function down(): void
    {
        Schema::connection($this->connection)
            ->table('organizations', function (Blueprint $table) {
                $table->dropColumn('enabled_modules');
            });
    }
};
