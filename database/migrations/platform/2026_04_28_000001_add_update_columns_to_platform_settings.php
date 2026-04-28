<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->table('platform_settings', function (Blueprint $table) {
            $table->timestamp('update_last_run_at')->nullable();
            $table->string('update_last_status', 20)->nullable();
            $table->text('update_last_message')->nullable();
            $table->string('update_current_version', 30)->nullable();
            $table->string('update_available_version', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'update_last_run_at',
                'update_last_status',
                'update_last_message',
                'update_current_version',
                'update_available_version',
            ]);
        });
    }
};
