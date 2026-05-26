<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL stores enum as VARCHAR + CHECK constraint.
        // Drop the old constraint and recreate it with 'guest' added.
        DB::statement('ALTER TABLE workspace_members DROP CONSTRAINT IF EXISTS workspace_members_role_check');
        DB::statement("ALTER TABLE workspace_members ADD CONSTRAINT workspace_members_role_check CHECK (role::text = ANY (ARRAY['owner'::text, 'admin'::text, 'member'::text, 'guest'::text]))");
    }

    public function down(): void
    {
        DB::table('workspace_members')->where('role', 'guest')->delete();
        DB::statement('ALTER TABLE workspace_members DROP CONSTRAINT IF EXISTS workspace_members_role_check');
        DB::statement("ALTER TABLE workspace_members ADD CONSTRAINT workspace_members_role_check CHECK (role::text = ANY (ARRAY['owner'::text, 'admin'::text, 'member'::text]))");
    }
};
