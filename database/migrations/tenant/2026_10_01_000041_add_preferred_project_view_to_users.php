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
        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            $table->enum('preferred_project_view', ['liste', 'kanban', 'gantt', 'agenda', 'workload'])
                ->default('liste')
                ->after('force_pwd_change');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('users', function (Blueprint $table) {
            $table->dropColumn('preferred_project_view');
        });
    }
};
