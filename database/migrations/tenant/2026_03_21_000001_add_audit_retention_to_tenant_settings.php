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
            $table->unsignedTinyInteger('audit_retention_months')
                ->default(12)
                ->comment('Rétention des audit_logs en mois — valeurs autorisées : 3, 6, 12, 24, 36');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn('audit_retention_months');
        });
    }
};
