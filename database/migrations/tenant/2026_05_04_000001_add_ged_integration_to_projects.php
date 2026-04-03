<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('ged_folder_id')->nullable()->after('is_private');
            $table->foreign('ged_folder_id')->references('id')->on('ged_folders')->nullOnDelete();
        });

        Schema::connection('tenant')->create('project_ged_links', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable'); // documentable_type, documentable_id
            $table->unsignedBigInteger('ged_document_id');
            $table->unsignedBigInteger('linked_by');
            $table->timestamps();

            $table->foreign('ged_document_id')->references('id')->on('ged_documents')->cascadeOnDelete();
            $table->foreign('linked_by')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['documentable_type', 'documentable_id', 'ged_document_id'], 'project_ged_links_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('project_ged_links');

        Schema::connection('tenant')->table('projects', function (Blueprint $table) {
            $table->dropForeign(['ged_folder_id']);
            $table->dropColumn('ged_folder_id');
        });
    }
};
