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
        Schema::connection('tenant')->create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('to_address', 255);
            $table->string('subject', 500);
            $table->string('type', 100);
            $table->enum('status', ['queued', 'sent', 'failed', 'bounced'])->default('queued');
            $table->string('mailer_id', 255)->nullable();
            $table->text('error_msg')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('email_logs');
    }
};
