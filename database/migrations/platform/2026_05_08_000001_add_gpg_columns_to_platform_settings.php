<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout des colonnes de chiffrement GPG des sauvegardes (ADR-041 §2).
 *
 * backup_gpg_enabled        : active le chiffrement GPG des archives
 * backup_gpg_passphrase_enc : passphrase GPG chiffrée via APP_KEY (Crypt::encryptString)
 *
 * Migration strictement additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->boolean('backup_gpg_enabled')->default(false)->after('backup_last_size_bytes');
            $table->text('backup_gpg_passphrase_enc')->nullable()->after('backup_gpg_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['backup_gpg_enabled', 'backup_gpg_passphrase_enc']);
        });
    }
};
