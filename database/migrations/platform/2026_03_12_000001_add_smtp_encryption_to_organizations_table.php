<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('smtp_encryption', 10)->nullable()->default('tls')
                ->after('smtp_port')
                ->comment('tls = STARTTLS (587), smtps = SSL (465), none = pas de chiffrement');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('smtp_encryption');
        });
    }
};
