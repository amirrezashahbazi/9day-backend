<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_delete_a_user_and_the_mobile_can_be_used_again(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $admin = User::factory()->admin()->create(['mobile' => '09127770000']);
        $member = User::factory()->create(['mobile' => '09127770001', 'first_name' => 'کیان', 'last_name' => 'تست']);

        $adminToken = $this->postJson('/api/auth/staff/login', [
            'mobile' => $admin->mobile,
            'password' => 'password-test',
        ])->assertOk()->json('token');

        $this->withToken($adminToken)->deleteJson('/api/admin/users/'.$member->id)
            ->assertForbidden();
        $this->assertNull(User::query()->find($member->id)->deleted_at);

        Auth::forgetGuards();
        $superToken = $this->postJson('/api/auth/staff/login', [
            'mobile' => SuperAdminSeeder::MOBILE,
            'password' => SuperAdminSeeder::PASSWORD,
        ])->assertOk()->json('token');

        Auth::forgetGuards();
        $this->withToken($superToken)->deleteJson('/api/admin/users/'.$member->id)
            ->assertOk();
        $this->assertNotNull(User::withTrashed()->find($member->id)->deleted_at);

        $this->withToken($superToken)->postJson('/api/admin/users', [
            'mobile' => '09127770001',
            'first_name' => 'کیان',
            'last_name' => 'جدید',
            'role' => 'athlete',
            'grant_access' => false,
            'skip_phone_verification' => true,
        ])->assertCreated()->assertJsonPath('user.mobile', '09127770001');
    }
}
