<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            // Dernier test de restauration
            $table->timestamp('security_restore_tested_at')->nullable()->after('backup_gpg_passphrase_enc');
            $table->string('security_restore_test_status', 20)->nullable()->after('security_restore_tested_at'); // success | failed
            $table->text('security_restore_test_note')->nullable()->after('security_restore_test_status');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn([
                'security_restore_tested_at',
                'security_restore_test_status',
                'security_restore_test_note',
            ]);
        });
    }
};
