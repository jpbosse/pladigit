<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents GED — fichiers rattachés à un dossier.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->dropIfExists('ged_documents');
        Schema::connection('tenant')->create('ged_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('ged_folders')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('disk_path', 1000);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('current_version')->default(1);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('folder_id');
            $table->index('created_by');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('ged_documents');
    }
};
