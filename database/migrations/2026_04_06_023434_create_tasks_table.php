<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('assignee_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['todo', 'in_progress', 'in_review', 'done'])
                ->default('todo');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->integer('position')->default(0);
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
