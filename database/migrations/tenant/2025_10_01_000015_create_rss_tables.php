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
        Schema::connection('tenant')->create('rss_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('title', 255);
            $table->string('url', 2000);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('fetch_interval_minutes')->default(60);
            $table->timestamp('last_fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('rss_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained('rss_feeds')->onDelete('cascade');
            $table->string('title', 500);
            $table->string('link', 2000);
            $table->text('summary')->nullable();
            $table->string('image_url', 2000)->nullable();
            $table->string('guid', 500);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['feed_id', 'guid']);
            $table->index('feed_id');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('rss_items');
        Schema::connection('tenant')->dropIfExists('rss_feeds');
    }
};
