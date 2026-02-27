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
        Schema::connection('tenant')->create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'closed', 'archived'])->default('draft');
            $table->boolean('anonymous')->default(false);
            $table->boolean('allow_multiple_responses')->default(false);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->onDelete('cascade');
            $table->string('label', 500);
            $table->enum('type', ['text', 'textarea', 'radio', 'checkbox', 'select', 'rating', 'date', 'file'])->default('text');
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('survey_id');
        });

        Schema::connection('tenant')->create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('anonymous_token', 64)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index('survey_id');
        });

        Schema::connection('tenant')->create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('survey_responses')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('survey_questions')->onDelete('restrict');
            $table->text('answer_text')->nullable();
            $table->json('answer_data')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->timestamps();
            $table->index('response_id');
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('survey_answers');
        Schema::connection('tenant')->dropIfExists('survey_responses');
        Schema::connection('tenant')->dropIfExists('survey_questions');
        Schema::connection('tenant')->dropIfExists('surveys');
    }
};
