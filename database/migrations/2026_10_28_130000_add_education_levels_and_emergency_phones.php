<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TYPE education_level ADD VALUE IF NOT EXISTS 'primary'");
        DB::statement("ALTER TYPE education_level ADD VALUE IF NOT EXISTS 'lower_secondary'");

        Schema::table('users', function (Blueprint $table) {
            $table->string('emergency_phone_1', 11)->nullable()->after('landline');
            $table->string('emergency_phone_2', 11)->nullable()->after('emergency_phone_1');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['emergency_phone_1', 'emergency_phone_2']);
        });
    }
};
