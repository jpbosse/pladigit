<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verrous WOPI pour Collabora Online.
 *
 * Un seul verrou par document à la fois.
 * Créé par LOCK, supprimé par UNLOCK, TTL rafraîchi par REFRESH_LOCK.
 * Les verrous expirés sont purgés à la prochaine opération sur le document.
 */
return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('ged_wopi_locks')) { return; }
        Schema::connection('tenant')->create('ged_wopi_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->unique()
                ->constrained('ged_documents')
                ->cascadeOnDelete();
            $table->string('lock_id', 1024);
            $table->timestamp('expires_at');
            $table->foreignId('locked_by')->constrained('users');
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('ged_wopi_locks');
    }
};
