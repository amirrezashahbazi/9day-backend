<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('weekday', 3);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->index(['course_id', 'weekday', 'starts_at'], 'course_schedules_slot_index');
        });

        DB::statement('ALTER TABLE courses ALTER COLUMN gender DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('course_schedules');
        DB::statement("UPDATE courses SET gender = 'female' WHERE gender IS NULL");
        DB::statement('ALTER TABLE courses ALTER COLUMN gender SET NOT NULL');
    }
};
