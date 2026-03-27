<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dossiers GED — hiérarchie illimitée via parent_id auto-référentiel.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        // Drop the proto-GED version created by 2025_10_01_000008 before recreating with the real schema.
        Schema::connection('tenant')->disableForeignKeyConstraints();
        Schema::connection('tenant')->dropIfExists('ged_folders');
        Schema::connection('tenant')->enableForeignKeyConstraints();

        Schema::connection('tenant')->create('ged_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->string('path', 1000);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_private')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('created_by');
            $table->index('deleted_at');
        });

        // FK auto-référentielle ajoutée après création (pattern Laravel safe)
        Schema::connection('tenant')->table('ged_folders', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('ged_folders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('ged_folders', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
        Schema::connection('tenant')->dropIfExists('ged_folders');
    }
};
