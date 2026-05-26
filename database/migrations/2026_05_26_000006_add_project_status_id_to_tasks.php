<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignUuid('project_status_id')->nullable()->after('sprint_id')
                ->constrained('project_statuses')->nullOnDelete();
        });

        // Populate from existing status enum values
        $tasks = DB::table('tasks')->get(['id', 'project_id', 'status']);
        foreach ($tasks as $task) {
            $ps = DB::table('project_statuses')
                ->where('project_id', $task->project_id)
                ->where('slug', $task->status)
                ->first(['id']);
            if ($ps) {
                DB::table('tasks')->where('id', $task->id)
                    ->update(['project_status_id' => $ps->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_status_id']);
            $table->dropColumn('project_status_id');
        });
    }
};
