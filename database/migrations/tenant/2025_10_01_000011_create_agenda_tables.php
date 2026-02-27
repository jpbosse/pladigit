<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function connection() { return 'tenant'; }
    public function up(): void {
        Schema::connection('tenant')->create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('location', 500)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('all_day')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_rule')->nullable();
            $table->string('color', 7)->default('#1E3A5F');
            $table->enum('visibility', ['private','restricted','public'])->default('restricted');
            $table->timestamps();
            $table->softDeletes();
            $table->index('starts_at');
            $table->index('created_by');
        });

        Schema::connection('tenant')->create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['invited','accepted','declined','tentative'])->default('invited');
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
    }
    public function down(): void {
        Schema::connection('tenant')->dropIfExists('event_participants');
        Schema::connection('tenant')->dropIfExists('events');
    }
};
