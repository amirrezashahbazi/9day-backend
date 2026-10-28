<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS file_number_seq');
        DB::statement('CREATE SEQUENCE file_number_seq START WITH 1 INCREMENT BY 1');

        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('divar_id', 20);
            $table->string('name', 80);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['name', 'deleted_at']);
        });
        DB::statement('CREATE UNIQUE INDEX provinces_divar_id_active_unique ON provinces (divar_id) WHERE deleted_at IS NULL');

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('provinces');
            $table->string('divar_id', 20);
            $table->string('name', 80);
            $table->boolean('has_districts')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['province_id', 'divar_id']);
            $table->index(['province_id', 'name', 'deleted_at']);
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained('cities');
            $table->string('divar_id', 20);
            $table->string('name', 120);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['city_id', 'divar_id']);
            $table->index(['city_id', 'name', 'deleted_at']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('file_number', 20);
            $table->string('mobile', 11);
            $table->string('password')->nullable();
            $table->string('role', 20)->default('user');
            $table->string('status', 20)->default('draft');
            $table->unsignedTinyInteger('registration_step')->default(0);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('registration_completed_at')->nullable();
            $table->timestamp('access_granted_at')->nullable();
            $table->foreignId('introduced_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('issued_from')->nullable();
            $table->string('national_code', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('education_level', 30)->nullable();
            $table->string('landline', 11)->nullable();
            $table->foreignId('province_id')->nullable()->constrained('provinces');
            $table->foreignId('city_id')->nullable()->constrained('cities');
            $table->foreignId('district_id')->nullable()->constrained('districts');

            $table->boolean('course_swimming')->default(false);
            $table->boolean('course_water_polo')->default(false);
            $table->boolean('course_diving')->default(false);
            $table->boolean('course_private')->default(false);
            $table->boolean('course_semi_private')->default(false);

            $table->string('referral_source', 30)->nullable();
            $table->string('referral_other')->nullable();

            $table->boolean('has_illness')->nullable();
            $table->boolean('illness_skin')->default(false);
            $table->string('illness_skin_description', 500)->nullable();
            $table->boolean('illness_respiratory')->default(false);
            $table->string('illness_respiratory_description', 500)->nullable();
            $table->boolean('illness_cardiac')->default(false);
            $table->string('illness_cardiac_description', 500)->nullable();
            $table->boolean('illness_other')->default(false);
            $table->string('illness_other_description', 500)->nullable();
            $table->boolean('takes_medication')->nullable();
            $table->text('medication')->nullable();

            $table->boolean('has_sport_history')->nullable();
            $table->string('previous_coach')->nullable();
            $table->string('history_duration')->nullable();
            $table->string('previous_club')->nullable();

            $table->timestamp('signed_at')->nullable();
            $table->string('signature_name')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['last_name', 'first_name', 'deleted_at']);
            $table->index(['province_id', 'city_id', 'district_id']);
            $table->index(['status', 'role', 'deleted_at']);
            $table->index(['birth_date', 'deleted_at']);
        });

        DB::statement('CREATE UNIQUE INDEX users_mobile_active_unique ON users (mobile) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX users_national_code_active_unique ON users (national_code) WHERE deleted_at IS NULL AND national_code IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX users_file_number_active_unique ON users (file_number) WHERE deleted_at IS NULL');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
        DB::statement('DROP SEQUENCE IF EXISTS file_number_seq');
    }
};
