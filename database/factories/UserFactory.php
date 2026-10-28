<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'mobile' => '09'.$this->faker->unique()->numerify('#########'),
            'password' => null,
            'status' => UserStatus::Draft,
            'registration_step' => 0,
            'first_name' => 'تست',
            'last_name' => 'کاربر',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->roles()->doesntExist()) {
                $user->syncRoles([UserRole::User]);
            }
        });
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'status' => UserStatus::Active,
            'password' => 'password-test',
            'access_granted_at' => now(),
            'phone_verified_at' => now(),
            'registration_step' => 7,
            'registration_completed_at' => now(),
        ])->afterCreating(function (User $user) {
            $user->syncRoles([UserRole::Admin]);
        });
    }
}
