<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function connection() { return 'tenant'; }
    public function up(): void {
        Schema::connection('tenant')->create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 100);
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->string('link', 500)->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read']);
        });
    }
    public function down(): void { Schema::connection('tenant')->dropIfExists('notifications'); }
};
