<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->string('collabora_url', 500)->nullable()->after('jitsi_base_url');
            $table->string('wopi_url', 500)->nullable()->after('collabora_url');
            $table->unsignedInteger('collabora_token_ttl_minutes')->nullable()->after('wopi_url');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['collabora_url', 'wopi_url', 'collabora_token_ttl_minutes']);
        });
    }
};
