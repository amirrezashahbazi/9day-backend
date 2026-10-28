<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['courses.view', 'courses.manage'] as $value) {
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
        // PostgreSQL cannot easily drop enum values.
    }
};
