<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement('CREATE INDEX IF NOT EXISTS users_status_created_active_index ON users (status, created_at DESC) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS users_place_filter_active_index ON users (province_id, city_id, district_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS users_education_status_active_index ON users (education_level, status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS users_signed_created_active_index ON users (signed_at, created_at) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS users_birth_status_active_index ON users (birth_date, status) WHERE deleted_at IS NULL');

        DB::statement('CREATE INDEX IF NOT EXISTS users_first_name_trgm_index ON users USING gin (first_name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_last_name_trgm_index ON users USING gin (last_name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_mobile_trgm_index ON users USING gin (mobile gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_national_code_trgm_index ON users USING gin (national_code gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_file_number_trgm_index ON users USING gin (file_number gin_trgm_ops)');

        DB::statement('CREATE INDEX IF NOT EXISTS courses_filter_active_index ON courses (gender, course_type_id, course_field_id, course_level_id, created_at DESC) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS courses_enrollments_gender_active_index ON courses (enrollments_count, gender) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS courses_title_trgm_index ON courses USING gin (title gin_trgm_ops)');

        DB::statement('CREATE INDEX IF NOT EXISTS time_slots_gender_period_start_index ON time_slots (gender, period, starts_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS time_slot_charges_filter_index ON time_slot_charges (period, created_at, user_id)');

        DB::statement('CREATE INDEX IF NOT EXISTS course_types_name_trgm_index ON course_types USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS course_fields_name_trgm_index ON course_fields USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS course_levels_name_trgm_index ON course_levels USING gin (name gin_trgm_ops)');
    }

    public function down(): void
    {
        foreach ([
            'users_status_created_active_index',
            'users_place_filter_active_index',
            'users_education_status_active_index',
            'users_signed_created_active_index',
            'users_birth_status_active_index',
            'users_first_name_trgm_index',
            'users_last_name_trgm_index',
            'users_mobile_trgm_index',
            'users_national_code_trgm_index',
            'users_file_number_trgm_index',
            'courses_filter_active_index',
            'courses_enrollments_gender_active_index',
            'courses_title_trgm_index',
            'time_slots_gender_period_start_index',
            'time_slot_charges_filter_index',
            'course_types_name_trgm_index',
            'course_fields_name_trgm_index',
            'course_levels_name_trgm_index',
        ] as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};
