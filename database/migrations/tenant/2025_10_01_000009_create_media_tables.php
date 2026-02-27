<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function connection() { return 'tenant'; }
    public function up(): void {
        Schema::connection('tenant')->create('media_albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('cover_path', 500)->nullable();
            $table->enum('visibility', ['private','restricted','public'])->default('restricted');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('media_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('media_albums')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->string('file_name', 500);
            $table->string('file_path', 1000);
            $table->string('thumb_path', 1000)->nullable();
            $table->string('mime_type', 127);
            $table->bigInteger('file_size_bytes')->unsigned();
            $table->unsignedSmallInteger('width_px')->nullable();
            $table->unsignedSmallInteger('height_px')->nullable();
            $table->json('exif_data')->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('sha256_hash', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('album_id');
        });

        Schema::connection('tenant')->create('media_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('media_albums')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('allow_download')->default(true);
            $table->string('password_hash', 255)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::connection('tenant')->dropIfExists('media_share_links');
        Schema::connection('tenant')->dropIfExists('media_items');
        Schema::connection('tenant')->dropIfExists('media_albums');
    }
};
