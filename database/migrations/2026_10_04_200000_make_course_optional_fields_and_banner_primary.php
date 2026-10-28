<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->date('registration_from')->nullable()->change();
            $table->date('registration_to')->nullable()->change();
            $table->date('starts_on')->nullable()->change();
            $table->date('ends_on')->nullable()->change();
            $table->unsignedTinyInteger('min_age')->nullable()->change();
            $table->unsignedTinyInteger('max_age')->nullable()->change();
        });

        Schema::table('course_banners', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('sort_order');
        });

        DB::statement('
            UPDATE course_banners b
            SET is_primary = true
            FROM (
                SELECT DISTINCT ON (course_id) id
                FROM course_banners
                ORDER BY course_id, sort_order ASC, id ASC
            ) firsts
            WHERE b.id = firsts.id
        ');
    }

    public function down(): void
    {
        Schema::table('course_banners', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->date('registration_from')->nullable(false)->change();
            $table->date('registration_to')->nullable(false)->change();
            $table->date('starts_on')->nullable(false)->change();
            $table->date('ends_on')->nullable(false)->change();
            $table->unsignedTinyInteger('min_age')->nullable(false)->change();
            $table->unsignedTinyInteger('max_age')->nullable(false)->change();
        });
    }
};
