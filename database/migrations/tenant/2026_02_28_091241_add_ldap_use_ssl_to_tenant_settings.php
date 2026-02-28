<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection($this->connection)
            ->table('tenant_settings', function (Blueprint $table) {
                $table->boolean('ldap_use_ssl')->default(true)->after('ldap_use_tls');
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)
            ->table('tenant_settings', function (Blueprint $table) {
                $table->dropColumn('ldap_use_ssl');
            });
    }
};
