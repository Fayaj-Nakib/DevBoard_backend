<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignUuid('sprint_id')->nullable()->after('milestone_id')
                ->constrained('sprints')->nullOnDelete();
            $table->unsignedSmallInteger('estimate')->nullable()->after('sprint_id')
                ->comment('Story points / time estimate');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['sprint_id']);
            $table->dropColumn(['sprint_id', 'estimate']);
        });
    }
};
