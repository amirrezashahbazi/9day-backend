<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('federation_inquiry')->default(false)->after('national_code');
            $table->timestamp('federation_inquired_at')->nullable()->after('federation_inquiry');
            $table->jsonb('federation_snapshot')->nullable()->after('federation_inquired_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['federation_inquiry', 'federation_inquired_at', 'federation_snapshot']);
        });
    }
};
