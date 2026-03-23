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
        Schema::connection('tenant')->table('project_milestones', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('reached_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('project_milestones', function (Blueprint $table) {
            $table->dropColumn('comment');
        });
    }
};
