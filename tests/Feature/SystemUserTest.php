<?php

namespace Tests\Feature;

use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_defines_a_system_user_and_can_grant_user_creation(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $token = $this->postJson('/api/auth/staff/login', [
            'mobile' => SuperAdminSeeder::MOBILE,
            'password' => SuperAdminSeeder::PASSWORD,
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/admin/role-access')
            ->assertOk();

        $permissions = $this->withToken($token)->getJson('/api/admin/role-access')->json('role.permissions');
        $this->assertNotContains('users.create', $permissions);

        $this->withToken($token)->postJson('/api/admin/system-users', [
            'first_name' => 'نگار',
            'last_name' => 'نوری',
            'mobile' => '09121234567',
            'password' => 'system-pass',
            'role' => 'admin',
        ])->assertCreated()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.first_name', 'نگار');

        $this->withToken($token)->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $login = $this->postJson('/api/auth/staff/login', [
            'mobile' => '09121234567',
            'password' => 'system-pass',
        ])->assertOk()->assertJsonPath('user.role', 'admin');

        $this->assertNotContains('users.create', $login->json('user.permissions'));

        $this->withToken($token)->putJson('/api/admin/role-access', [
            'permissions' => array_merge($permissions, ['users.create']),
        ])->assertOk();

        $again = $this->postJson('/api/auth/staff/login', [
            'mobile' => '09121234567',
            'password' => 'system-pass',
        ])->assertOk();
        $this->assertContains('users.create', $again->json('user.permissions'));
    }
}
