<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function connection() { return 'tenant'; }
    public function up(): void {
        Schema::connection('tenant')->create('chat_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('type', ['public','private','direct'])->default('public');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('chat_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('chat_channels')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('is_admin')->default(false);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();
            $table->unique(['channel_id', 'user_id']);
        });

        Schema::connection('tenant')->create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('chat_channels')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('reply_to_id')->nullable()->constrained('chat_messages')->onDelete('set null');
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('channel_id');
        });
    }
    public function down(): void {
        Schema::connection('tenant')->dropIfExists('chat_messages');
        Schema::connection('tenant')->dropIfExists('chat_members');
        Schema::connection('tenant')->dropIfExists('chat_channels');
    }
};
