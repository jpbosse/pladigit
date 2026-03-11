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
        Schema::connection('tenant')->create('shares', function (Blueprint $table) {
            $table->id();
            $table->string('shareable_type', 150);
            $table->unsignedBigInteger('shareable_id');
            $table->string('shared_with_type', 20);
            $table->unsignedBigInteger('shared_with_id')->nullable();
            $table->string('shared_with_role', 50)->nullable();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_download')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->unsignedBigInteger('shared_by')->nullable();
            $table->timestamps();

            $table->index(['shareable_type', 'shareable_id'], 'shares_shareable_idx');
            $table->index(['shared_with_type', 'shared_with_id'], 'shares_with_idx');

            // Contrainte unique sans dépasser 3072 bytes
            $table->unique(
                ['shareable_type', 'shareable_id', 'shared_with_type', 'shared_with_id', 'shared_with_role'],
                'shares_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('shares');
    }
};
