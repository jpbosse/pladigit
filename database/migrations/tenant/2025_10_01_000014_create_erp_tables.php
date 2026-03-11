<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function connection()
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->create('erp_table_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('table_name', 127)->unique();
            $table->string('display_name', 255);
            $table->json('columns_config');
            $table->json('filters_config')->nullable();
            $table->string('sort_column', 63)->nullable();
            $table->enum('sort_direction', ['asc', 'desc'])->default('asc');
            $table->unsignedTinyInteger('items_per_page')->default(25);
            $table->boolean('allow_create')->default(false);
            $table->boolean('allow_edit')->default(true);
            $table->boolean('allow_delete')->default(false);
            $table->boolean('allow_export')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('erp_access_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('erp_table_configs')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('role', ['admin', 'president', 'dgs', 'resp_direction', 'resp_service', 'user'])->nullable();
            $table->enum('permission_level', ['read', 'write', 'admin'])->default('read');
            $table->timestamps();
            $table->index('config_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('erp_access_permissions');
        Schema::connection('tenant')->dropIfExists('erp_table_configs');
    }
};
