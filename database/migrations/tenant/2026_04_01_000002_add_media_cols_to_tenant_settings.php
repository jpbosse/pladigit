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
            $table->unsignedTinyInteger('media_default_cols')->default(3)->after('nas_last_sync_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('media_default_cols');
        });
    }
};
