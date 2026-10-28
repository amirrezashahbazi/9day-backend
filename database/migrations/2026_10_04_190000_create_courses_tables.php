<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value');
            $table->timestamps();
        });

        DB::table('app_settings')->insert([
            'key' => 'course_banner_max_kb',
            'value' => '1024',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['course_types', 'course_fields', 'course_levels'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['name', 'deleted_at']);
            });
            DB::statement("CREATE UNIQUE INDEX {$tableName}_name_active_unique ON {$tableName} (name) WHERE deleted_at IS NULL");
        }

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_type_id')->constrained('course_types');
            $table->foreignId('course_field_id')->constrained('course_fields');
            $table->foreignId('course_level_id')->constrained('course_levels');
            $table->string('title', 200);
            $table->date('registration_from');
            $table->date('registration_to');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedTinyInteger('min_age');
            $table->unsignedTinyInteger('max_age');
            $table->unsignedInteger('fee');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('enrollments_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE courses ADD COLUMN gender gender NOT NULL');
        DB::statement('CREATE INDEX courses_admin_report_index ON courses (deleted_at, created_at, gender, course_type_id, course_field_id, course_level_id)');
        DB::statement('CREATE INDEX courses_enrollments_count_index ON courses (deleted_at, enrollments_count)');
        DB::statement('CREATE INDEX courses_title_index ON courses (title)');
        DB::statement('CREATE INDEX courses_type_field_level_index ON courses (course_type_id, course_field_id, course_level_id, deleted_at)');

        Schema::create('course_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('path', 255);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['course_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_banners');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('course_levels');
        Schema::dropIfExists('course_fields');
        Schema::dropIfExists('course_types');
        Schema::dropIfExists('app_settings');
    }
};
