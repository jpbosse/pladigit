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
        Schema::connection('tenant')->create('ged_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('ged_folders')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('name', 255);
            $table->string('slug_path', 1000)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_template_folder')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index('parent_id');
        });

        Schema::connection('tenant')->create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('ged_folders')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('locked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('title', 255);
            $table->string('file_name', 500);
            $table->string('file_path', 1000);
            $table->string('mime_type', 127);
            $table->bigInteger('file_size_bytes')->unsigned();
            $table->string('sha256_hash', 64)->nullable();
            $table->enum('status', ['draft', 'in_review', 'approved', 'archived'])->default('draft');
            $table->enum('visibility', ['private', 'restricted', 'public'])->default('restricted');
            $table->boolean('is_template')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('folder_id');
            $table->index('status');
            $table->index('created_by');
        });

        Schema::connection('tenant')->create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->unsignedSmallInteger('version_number');
            $table->string('file_path', 1000);
            $table->bigInteger('file_size_bytes')->unsigned();
            $table->string('sha256_hash', 64)->nullable();
            $table->text('change_summary')->nullable();
            $table->timestamps();
            $table->index('document_id');
        });

        Schema::connection('tenant')->create('document_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('role', ['admin', 'president', 'dgs', 'resp_direction', 'resp_service', 'user'])->nullable();
            $table->enum('permission', ['read', 'comment', 'edit', 'manage'])->default('read');
            $table->timestamps();
            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('document_permissions');
        Schema::connection('tenant')->dropIfExists('document_versions');
        Schema::connection('tenant')->dropIfExists('documents');
        Schema::connection('tenant')->dropIfExists('ged_folders');
    }
};
