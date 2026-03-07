<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function connection(): string { return 'tenant'; }

    public function up(): void
    {
        Schema::connection('tenant')->create('media_album_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('album_id')->constrained('media_albums')->cascadeOnDelete();
            $table->string('role', 50);
            $table->boolean('can_view')->default(false);
            $table->boolean('can_download')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->timestamps();
            $table->unique(['album_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('media_album_permissions');
    }
};
