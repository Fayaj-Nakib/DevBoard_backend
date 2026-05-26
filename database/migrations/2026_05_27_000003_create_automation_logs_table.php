<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->dateTime('triggered_at');
            $table->enum('result', ['success', 'failed'])->default('success');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['rule_id', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
    }
};
