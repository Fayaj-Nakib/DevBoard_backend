<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'user_id']);
        });

        // Migrate existing single-assignee data into the pivot
        DB::table('tasks')
            ->whereNotNull('assignee_id')
            ->select('id', 'assignee_id')
            ->orderBy('id')
            ->each(function ($task) {
                DB::table('task_assignees')->insertOrIgnore([
                    'task_id' => $task->id,
                    'user_id' => $task->assignee_id,
                ]);
            });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['assignee_id']);
            $table->dropColumn('assignee_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignUuid('assignee_id')->nullable()
                ->constrained('users')->nullOnDelete();
        });

        Schema::dropIfExists('task_assignees');
    }
};
