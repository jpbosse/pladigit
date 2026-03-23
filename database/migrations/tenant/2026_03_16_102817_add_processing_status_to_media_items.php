<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function connection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->table('media_items', function (Blueprint $table) {
            $table->enum('processing_status', ['pending', 'done', 'failed'])
                ->default('done')
                ->after('sha256_hash');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('media_items', function (Blueprint $table) {
            $table->dropColumn('processing_status');
        });
    }
};
