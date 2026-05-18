<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('audit_max_retention_months')
                ->default(60)
                ->after('security_restore_test_note')
                ->comment('Durée max absolue de rétention des audit_logs — plafond plateforme (RGPD). Valeur en mois.');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn('audit_max_retention_months');
        });
    }
};
