<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('recurrence_rule')->nullable()->after('estimate')
                ->comment('daily | weekly | weekday | monthly');
            $table->timestamp('recurrence_ends_at')->nullable()->after('recurrence_rule');
            $table->foreignUuid('recurrence_parent_id')->nullable()->after('recurrence_ends_at')
                ->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['recurrence_parent_id']);
            $table->dropColumn(['recurrence_rule', 'recurrence_ends_at', 'recurrence_parent_id']);
        });
    }
};
