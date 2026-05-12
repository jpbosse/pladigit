<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('datagrid_columns', function (Blueprint $table) {
            $table->string('tab', 50)->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('datagrid_columns', function (Blueprint $table) {
            $table->dropColumn('tab');
        });
    }
};
