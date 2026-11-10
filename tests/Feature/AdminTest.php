<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use AuthenticatesUsers, RefreshDatabase;

    public function test_super_admin_can_login_and_manage_access(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $login = $this->postJson('/api/auth/staff/login', [
            'mobile' => SuperAdminSeeder::MOBILE,
            'password' => SuperAdminSeeder::PASSWORD,
        ])->assertOk()->assertJsonPath('user.role', 'super_admin');

        $token = $login->json('token');

        $created = $this->withToken($token)->postJson('/api/admin/users', [
            'mobile' => '09126666666',
            'first_name' => 'نگار',
            'last_name' => 'احمدی',
            'role' => 'admin',
            'password' => 'admin-pass-1',
            'grant_access' => true,
            'skip_phone_verification' => true,
        ])->assertCreated()->assertJsonPath('user.role', 'admin')->assertJsonPath('user.status', 'active');

        $this->withToken($token)->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($token)->getJson('/api/admin/system-users')
            ->assertOk()
            ->assertJsonPath('data.0.role', 'admin')
            ->assertJsonPath('data.0.mobile', '09126666666');

        $this->withToken($token)->patchJson('/api/admin/users/'.$created->json('user.id').'/access', [
            'status' => 'blocked',
            'role' => 'user',
        ])->assertOk()->assertJsonPath('user.status', 'blocked')->assertJsonPath('user.role', 'user');
    }

    public function test_admin_cannot_define_users_until_the_permission_is_granted(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $admin = User::factory()->admin()->create(['mobile' => '09127777777']);
        $login = $this->postJson('/api/auth/staff/login', [
            'mobile' => '09127777777',
            'password' => 'password-test',
        ])->assertOk();

        $token = $login->json('token');

        $this->withToken($token)->postJson('/api/admin/users', [
            'mobile' => '09128888888',
            'first_name' => 'کیان',
            'grant_access' => true,
            'skip_phone_verification' => true,
        ])->assertForbidden();

        $this->withToken($token)->postJson('/api/admin/system-users', [
            'first_name' => 'کیان',
            'last_name' => 'ادمین',
            'mobile' => '09129999999',
            'password' => 'another-admin',
            'role' => 'admin',
        ])->assertForbidden();

        Auth::forgetGuards();
        $super = $this->postJson('/api/auth/staff/login', [
            'mobile' => SuperAdminSeeder::MOBILE,
            'password' => SuperAdminSeeder::PASSWORD,
        ])->assertOk()->json('token');

        Auth::forgetGuards();
        $granted = $this->withToken($super)->getJson('/api/admin/role-access')
            ->assertOk()
            ->json('role.permissions');
        $granted[] = 'users.create';

        $this->withToken($super)->putJson('/api/admin/role-access', [
            'permissions' => $granted,
        ])->assertOk();

        Auth::forgetGuards();
        $this->withToken($token)->postJson('/api/admin/users', [
            'mobile' => '09128888888',
            'first_name' => 'کیان',
            'grant_access' => true,
            'skip_phone_verification' => true,
        ])->assertCreated()->assertJsonPath('user.status', 'active')->assertJsonPath('user.introduced_by', $admin->id);

        $this->withToken($token)->postJson('/api/admin/users', [
            'mobile' => '09129999991',
            'role' => 'admin',
            'password' => 'another-admin',
            'grant_access' => true,
            'skip_phone_verification' => true,
        ])->assertForbidden();

        $userToken = $this->loginUser('09120000000');
        $this->withToken($userToken)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_reports_accept_neutral_filters_and_paginate(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $token = $this->postJson('/api/auth/staff/login', [
            'mobile' => SuperAdminSeeder::MOBILE,
            'password' => SuperAdminSeeder::PASSWORD,
        ])->assertOk()->json('token');

        $this->withToken($token)->postJson('/api/admin/users', [
            'mobile' => '09123333333',
            'first_name' => 'آوا',
            'last_name' => 'رضایی',
            'grant_access' => true,
            'skip_phone_verification' => true,
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/admin/reports')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.current_page', 1);

        $this->withToken($token)->getJson('/api/admin/reports?role=user&education_level=&national_code=')
            ->assertOk();

        $this->withToken($token)->getJson('/api/admin/reports?national_code=1234567890')
            ->assertStatus(422);

        $this->withToken($token)->getJson('/api/admin/reports?national_code=0499370899')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->withToken($token)->getJson('/api/admin/reports?per_page=501')
            ->assertStatus(422);

        $this->withToken($token)->getJson('/api/admin/users?per_page=50')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }
}
