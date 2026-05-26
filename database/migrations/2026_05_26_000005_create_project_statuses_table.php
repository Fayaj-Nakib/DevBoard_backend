<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#9CA3AF');
            $table->integer('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_done')->default(false);
            $table->string('slug')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'position']);
        });

        $defaults = [
            ['slug' => 'todo',        'name' => 'To Do',       'color' => '#9CA3AF', 'is_done' => false, 'is_default' => true,  'position' => 1],
            ['slug' => 'in_progress', 'name' => 'In Progress', 'color' => '#3B82F6', 'is_done' => false, 'is_default' => false, 'position' => 2],
            ['slug' => 'in_review',   'name' => 'In Review',   'color' => '#8B5CF6', 'is_done' => false, 'is_default' => false, 'position' => 3],
            ['slug' => 'done',        'name' => 'Done',        'color' => '#10B981', 'is_done' => true,  'is_default' => false, 'position' => 4],
        ];

        $projects = DB::table('projects')->get(['id']);
        foreach ($projects as $project) {
            foreach ($defaults as $d) {
                DB::table('project_statuses')->insert([
                    'id' => (string) Str::uuid(),
                    'project_id' => $project->id,
                    'name' => $d['name'],
                    'color' => $d['color'],
                    'position' => $d['position'],
                    'is_default' => $d['is_default'],
                    'is_done' => $d['is_done'],
                    'slug' => $d['slug'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_statuses');
    }
};
