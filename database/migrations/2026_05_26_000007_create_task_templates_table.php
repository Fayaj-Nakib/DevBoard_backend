<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users');
            $table->string('name');
            $table->string('default_title')->nullable();
            $table->text('description')->nullable();
            $table->json('label_ids')->nullable();
            $table->integer('estimate')->nullable();
            $table->json('checklist')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->timestamps();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};
