<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class GateDisplaySeeder extends Seeder
{
    public const MOBILE = '09111111111';

    public const PASSWORD = '09111111111';

    public function run(): void
    {
        $user = User::query()->firstOrNew(['mobile' => self::MOBILE]);

        $user->fill([
            'password' => self::PASSWORD,
            'status' => UserStatus::Active,
            'registration_step' => 7,
            'phone_verified_at' => now(),
            'registration_completed_at' => now(),
            'access_granted_at' => now(),
            'first_name' => 'مانیتور',
            'last_name' => 'تردد',
            'signed_at' => now(),
            'signature_name' => 'مانیتور تردد',
        ]);

        $user->save();
        $user->syncRoles([UserRole::Admin]);
    }
}
