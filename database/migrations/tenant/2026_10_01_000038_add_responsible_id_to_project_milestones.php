<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('project_milestones', function (Blueprint $table) {
            $table->unsignedBigInteger('responsible_id')->nullable()->after('manual_progress');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('project_milestones', function (Blueprint $table) {
            $table->dropColumn('responsible_id');
        });
    }
};
