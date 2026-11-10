<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS users_mobile_active_lookup ON users (mobile) WHERE deleted_at IS NULL');

        Schema::create('user_kinships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('relative_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relation', 20);
            $table->timestamps();
            $table->unique(['user_id', 'relative_user_id'], 'user_kinships_pair_unique');
            $table->index(['relative_user_id', 'relation'], 'user_kinships_relative_relation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_kinships');
        DB::statement('DROP INDEX IF EXISTS users_mobile_active_lookup');
    }
};
