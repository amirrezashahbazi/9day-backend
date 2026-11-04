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
            $table->unsignedSmallInteger('sessions_count')->default(12)->after('capacity');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('access_card_number', 'users_access_card_number_index');
        });

        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sessions_total');
            $table->unsignedSmallInteger('sessions_remaining');
            $table->string('status', 20);
            $table->foreignId('renewed_from_id')->nullable()->constrained('course_enrollments')->nullOnDelete();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('access_card_number', 32)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'course_enrollments_user_status_index');
            $table->index(['course_id', 'status'], 'course_enrollments_course_status_index');
            $table->index(['access_card_number', 'status'], 'course_enrollments_card_status_index');
            $table->index(['user_id', 'course_id', 'status'], 'course_enrollments_user_course_status_index');
            $table->index('sessions_remaining', 'course_enrollments_remaining_index');
        });

        DB::statement("
            CREATE UNIQUE INDEX course_enrollments_one_active_per_user
            ON course_enrollments (user_id)
            WHERE status = 'active'
        ");

        Schema::create('gate_transits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('course_enrollments')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('access_control_log_id')->nullable()->constrained('access_control_logs')->nullOnDelete();
            $table->string('card_number', 32)->nullable();
            $table->boolean('allowed');
            $table->string('reason', 40);
            $table->unsignedSmallInteger('sessions_remaining_after')->nullable();
            $table->string('course_title', 200)->nullable();
            $table->string('user_name', 200)->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->timestamps();

            $table->index('created_at', 'gate_transits_created_at_index');
            $table->index(['card_number', 'created_at'], 'gate_transits_card_created_index');
            $table->index(['user_id', 'created_at'], 'gate_transits_user_created_index');
            $table->index(['enrollment_id', 'created_at'], 'gate_transits_enrollment_created_index');
            $table->index(['allowed', 'created_at'], 'gate_transits_allowed_created_index');
        });

        $seconds = DB::table('app_settings')->where('key', 'access_control_display_seconds')->value('value');
        $ms = $seconds !== null ? max(200, min(10000, ((int) $seconds) * 1000)) : 1000;

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'access_control_display_ms'],
            [
                'value' => (string) $ms,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        DB::table('app_settings')->where('key', 'access_control_display_seconds')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_transits');
        DB::statement('DROP INDEX IF EXISTS course_enrollments_one_active_per_user');
        Schema::dropIfExists('course_enrollments');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_access_card_number_index');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('sessions_count');
        });

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'access_control_display_seconds'],
            [
                'value' => '1',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        DB::table('app_settings')->where('key', 'access_control_display_ms')->delete();
    }
};
