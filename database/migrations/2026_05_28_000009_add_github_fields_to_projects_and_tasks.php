<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('github_repo')->nullable()->after('status'); // format: owner/repo
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('github_issue_number')->nullable()->after('backlog_position');
            $table->unsignedInteger('github_pr_number')->nullable()->after('github_issue_number');
            $table->string('github_pr_state', 20)->nullable()->after('github_pr_number'); // open/closed/merged
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('github_repo');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['github_issue_number', 'github_pr_number', 'github_pr_state']);
        });
    }
};
