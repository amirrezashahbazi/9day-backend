<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('access_card_number', 32)->nullable()->after('mobile');
            $table->timestamp('access_card_enrolled_at')->nullable()->after('access_card_number');
        });

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'access_control_device_serial'],
            [
                'value' => 'AYSC01015467',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['access_card_number', 'access_card_enrolled_at']);
        });

        DB::table('app_settings')->where('key', 'access_control_device_serial')->delete();
    }
};
