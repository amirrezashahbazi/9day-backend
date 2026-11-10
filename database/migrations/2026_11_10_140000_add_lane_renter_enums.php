<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TYPE user_role ADD VALUE IF NOT EXISTS 'lane_renter'");
        foreach (['lanes.manage', 'lanes.members'] as $value) {
            DB::statement("
                DO \$\$ BEGIN
                    ALTER TYPE permission_name ADD VALUE '{$value}';
                EXCEPTION WHEN duplicate_object THEN NULL;
                END \$\$;
            ");
        }
    }

    public function down(): void
    {
        // PostgreSQL cannot drop enum values safely.
    }
};
