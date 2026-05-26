<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->enum('trigger_type', [
                'task_created', 'status_changed', 'due_date_reached',
                'assignee_added', 'comment_added',
            ]);
            $table->json('trigger_config')->nullable();
            $table->enum('action_type', [
                'change_status', 'assign_user', 'add_label',
                'post_comment', 'send_notification',
            ]);
            $table->json('action_config')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'is_active']);
            $table->index(['project_id', 'trigger_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
