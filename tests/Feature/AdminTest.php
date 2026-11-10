<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.role', 'admin');

        $this->withToken($token)->patchJson('/api/admin/users/'.$created->json('user.id').'/access', [
            'status' => 'blocked',
            'role' => 'user',
        ])->assertOk()->assertJsonPath('user.status', 'blocked')->assertJsonPath('user.role', 'user');
    }

    public function test_admin_can_introduce_a_user_but_not_another_admin(): void
    {
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
        ])->assertCreated()->assertJsonPath('user.status', 'active')->assertJsonPath('user.introduced_by', $admin->id);

        $this->withToken($token)->postJson('/api/admin/users', [
            'mobile' => '09129999999',
            'role' => 'admin',
            'password' => 'another-admin',
            'grant_access' => true,
            'skip_phone_verification' => true,
        ])->assertStatus(403);

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
