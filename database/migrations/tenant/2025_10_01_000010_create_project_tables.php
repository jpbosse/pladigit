<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function connection() { return 'tenant'; }
    public function up(): void {
        Schema::connection('tenant')->create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('status', ['active','on_hold','completed','archived'])->default('active');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('color', 7)->default('#1E3A5F');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['owner','member','viewer'])->default('member');
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::connection('tenant')->create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->onDelete('cascade');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('status', ['todo','in_progress','in_review','done'])->default('todo');
            $table->enum('priority', ['low','medium','high','urgent'])->default('medium');
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('estimated_hours')->nullable();
            $table->unsignedSmallInteger('actual_hours')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index('project_id');
            $table->index('assigned_to');
            $table->index('status');
        });

        Schema::connection('tenant')->create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index('task_id');
        });
    }
    public function down(): void {
        Schema::connection('tenant')->dropIfExists('task_comments');
        Schema::connection('tenant')->dropIfExists('tasks');
        Schema::connection('tenant')->dropIfExists('project_members');
        Schema::connection('tenant')->dropIfExists('projects');
    }
};
