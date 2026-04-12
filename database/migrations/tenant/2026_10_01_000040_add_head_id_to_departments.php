<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('departments', function (Blueprint $table) {
            $table->unsignedBigInteger('head_id')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('departments', function (Blueprint $table) {
            $table->dropColumn('head_id');
        });
    }
};
