<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lane_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('CREATE UNIQUE INDEX lane_types_name_active_unique ON lane_types (name) WHERE deleted_at IS NULL');

        $now = now();
        foreach (['عرضی', 'طولی', 'وارم‌آپ'] as $name) {
            DB::table('lane_types')->insert([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('lanes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lane_type_id')->constrained('lane_types')->restrictOnDelete();
            $table->string('title', 200);
            $table->unsignedInteger('fee');
            $table->unsignedSmallInteger('min_people');
            $table->unsignedSmallInteger('max_people');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['lane_type_id', 'created_at'], 'lanes_type_created_index');
        });

        Schema::create('lane_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lane_id')->constrained('lanes')->restrictOnDelete();
            $table->foreignId('renter_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('students_count');
            $table->unsignedSmallInteger('sessions_total');
            $table->unsignedSmallInteger('sessions_remaining');
            $table->string('access_card_number', 32);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['renter_user_id', 'created_at'], 'lane_rentals_renter_created_index');
            $table->index(['lane_id', 'created_at'], 'lane_rentals_lane_created_index');
            $table->index('access_card_number', 'lane_rentals_card_index');
        });

        Schema::create('lane_rental_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lane_rental_id')->constrained('lane_rentals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['lane_rental_id', 'user_id'], 'lane_rental_members_unique');
            $table->index(['user_id', 'lane_rental_id'], 'lane_rental_members_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lane_rental_members');
        Schema::dropIfExists('lane_rentals');
        Schema::dropIfExists('lanes');
        Schema::dropIfExists('lane_types');
    }
};
