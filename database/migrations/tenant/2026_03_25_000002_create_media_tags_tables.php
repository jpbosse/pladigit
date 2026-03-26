<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->create('media_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->timestamps();
            $table->unique('name');
        });

        Schema::connection('tenant')->create('media_item_tag', function (Blueprint $table) {
            $table->foreignId('media_item_id')
                ->constrained('media_items')
                ->cascadeOnDelete();
            $table->foreignId('tag_id')
                ->constrained('media_tags')
                ->cascadeOnDelete();
            $table->primary(['media_item_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('media_item_tag');
        Schema::connection('tenant')->dropIfExists('media_tags');
    }
};
