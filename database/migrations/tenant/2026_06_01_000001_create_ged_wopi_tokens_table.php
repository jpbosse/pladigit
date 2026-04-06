<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens WOPI pour l'intégration Collabora Online (Phase 7 — Jalon 1).
 *
 * Chaque token est généré à la demande d'ouverture d'un document,
 * associé à un utilisateur et à un document GED, avec une expiration.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('ged_wopi_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('ged_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('token');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('ged_wopi_tokens');
    }
};
