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
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->string('jitsi_base_url', 255)->default('https://meet.numerique.gouv.fr');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('jitsi_base_url');
        });
    }
};
