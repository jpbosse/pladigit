<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->boolean('wm_enabled')->default(false)->after('media_default_cols');
            $table->enum('wm_type', ['text', 'logo'])->default('text')->after('wm_enabled');
            $table->string('wm_text', 100)->nullable()->after('wm_type');
            $table->enum('wm_position', ['bottom-right', 'bottom-left', 'center', 'bottom-center'])
                ->default('bottom-right')->after('wm_text');
            $table->unsignedTinyInteger('wm_opacity')->default(60)->after('wm_position')
                ->comment('Opacité 10–100');
            $table->enum('wm_size', ['small', 'medium', 'large'])->default('medium')->after('wm_opacity');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['wm_enabled', 'wm_type', 'wm_text', 'wm_position', 'wm_opacity', 'wm_size']);
        });
    }
};
