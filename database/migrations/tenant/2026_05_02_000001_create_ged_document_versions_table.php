<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('ged_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained('ged_documents')
                ->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk_path', 1000);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type', 100)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index('document_id');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('ged_document_versions');
    }
};
