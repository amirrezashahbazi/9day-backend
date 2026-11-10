<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            DO \$\$ BEGIN
                ALTER TYPE permission_name ADD VALUE 'time_slots.manage';
            EXCEPTION WHEN duplicate_object THEN NULL;
            END \$\$;
        ");
    }

    public function down(): void
    {
        // PostgreSQL cannot easily drop enum values.
    }
};
