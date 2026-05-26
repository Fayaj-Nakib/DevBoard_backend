<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('from_status_id')->nullable()
                ->constrained('project_statuses')->nullOnDelete();
            $table->foreignUuid('to_status_id')
                ->constrained('project_statuses')->cascadeOnDelete();
            $table->foreignUuid('changed_by')->constrained('users');
            $table->dateTime('changed_at');
            $table->timestamps();
            $table->index(['task_id', 'changed_at']);
            $table->index(['to_status_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_history');
    }
};
