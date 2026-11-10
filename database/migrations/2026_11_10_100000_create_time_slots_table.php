<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->string('gender', 10);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('period', 10);
            $table->unsignedInteger('fee');
            $table->timestamps();

            $table->index('period');
            $table->unique(['gender', 'starts_at', 'ends_at']);
        });

        if (Schema::hasColumn('courses', 'slot_starts_at')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn(['slot_starts_at', 'slot_ends_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');

        if (! Schema::hasColumn('courses', 'slot_starts_at')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->time('slot_starts_at')->nullable();
                $table->time('slot_ends_at')->nullable();
            });
        }
    }
};
