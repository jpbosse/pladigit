<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql'; // Base pladigit_platform

    public function up(): void
    {
        Schema::connection($this->connection)
            ->create('platform_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('admin_email')->comment('Email du super-admin (depuis .env)');
                $table->string('action', 100)->comment('Ex: org.create, org.suspend');
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->json('data')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('action');
                $table->index('organization_id');
                $table->index('created_at');
            });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('platform_audit_logs');
    }
};
