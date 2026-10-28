<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public const MOBILE = '09194949534';

    public const PASSWORD = '6582525678g510';

    public function run(): void
    {
        $admin = User::query()->firstOrNew(['mobile' => self::MOBILE]);

        $admin->fill([
            'password' => self::PASSWORD,
            'status' => UserStatus::Active,
            'registration_step' => 7,
            'phone_verified_at' => now(),
            'registration_completed_at' => now(),
            'access_granted_at' => now(),
            'first_name' => 'سوپر',
            'last_name' => 'ادمین',
            'signed_at' => now(),
            'signature_name' => 'سوپر ادمین',
        ]);

        $admin->save();
        $admin->syncRoles([UserRole::SuperAdmin]);
    }
}
