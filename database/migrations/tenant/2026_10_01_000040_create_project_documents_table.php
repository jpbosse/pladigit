<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pièces jointes et liens sur les projets, tâches et jalons.
 *
 * documentable_type : App\Models\Tenant\Project | Task | ProjectMilestone
 * type              : file | link
 * path              : chemin storage (file) ou URL (link)
 * driver            : local | nas (extensible Phase 5 / GED)
 */
return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');                          // documentable_type + documentable_id
            $table->enum('type', ['file', 'link'])->default('file');
            $table->enum('driver', ['local', 'nas'])->default('local');
            $table->string('name', 255);                             // nom affiché
            $table->string('path', 1000);                            // chemin ou URL
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('description', 500)->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['documentable_type', 'documentable_id'], 'proj_docs_morphs');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('project_documents');
    }
};
