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
        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('media_cols')->default(0)->after('avatar_path');
            // 0 = utiliser la valeur par défaut du tenant (TenantSettings::media_default_cols)
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            $table->dropColumn('media_cols');
        });
    }
};
