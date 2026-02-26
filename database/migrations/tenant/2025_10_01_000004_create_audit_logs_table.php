<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration
{
    protected $connection = 'tenant';
 
    public function up(): void
    {
        Schema::connection($this->connection)
              ->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('user_name')->nullable()
                  ->comment('Dénormalisé — conservation après suppression');
            $table->string('action', 100)
                  ->comment('Ex: user.login, document.create');
            $table->string('model_type', 100)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
 
            $table->index('user_id');
            $table->index('action');
            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });
    }
 
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('audit_logs');
    }
};
