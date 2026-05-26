<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('digest_enabled')->default(true)->after('remember_token');
            $table->unsignedTinyInteger('digest_hour')->default(7)->after('digest_enabled');
            $table->date('last_digest_sent')->nullable()->after('digest_hour');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['digest_enabled', 'digest_hour', 'last_digest_sent']);
        });
    }
};
