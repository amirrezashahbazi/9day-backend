<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $this->assertContains('panel.access', $again->json('user.permissions'));
    }

    public function test_panel_access_stays_on_and_a_bad_mobile_is_rejected(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $token = $this->postJson('/api/auth/staff/login', [
            'mobile' => SuperAdminSeeder::MOBILE,
            'password' => SuperAdminSeeder::PASSWORD,
        ])->assertOk()->json('token');

        $saved = $this->withToken($token)->putJson('/api/admin/role-access', [
            'permissions' => ['users.view', 'reports.view'],
        ])->assertOk();
        $this->assertNotContains('panel.access', $saved->json('permissions'));

        $stored = $this->withToken($token)->getJson('/api/admin/role-access')->json('role.permissions');
        $this->assertContains('panel.access', $stored);
        $this->assertNotContains('users.create', $stored);

        $this->withToken($token)->postJson('/api/admin/system-users', [
            'first_name' => 'نگار',
            'last_name' => 'نوری',
            'mobile' => '0912ویشبی',
            'password' => 'system-pass',
            'role' => 'admin',
        ])->assertStatus(422);

        $created = $this->withToken($token)->postJson('/api/admin/system-users', [
            'first_name' => 'نگار',
            'last_name' => 'نوری',
            'mobile' => '۰۹۱۲۱۲۳۴۵۶۸',
            'password' => 'system-pass',
            'role' => 'admin',
        ])->assertCreated()->assertJsonPath('user.mobile', '09121234568');

        $this->withToken($token)->patchJson('/api/admin/system-users/'.$created->json('user.id').'/status', [
            'status' => 'blocked',
        ])->assertOk()->assertJsonPath('user.status', 'blocked');

        $this->postJson('/api/auth/staff/login', [
            'mobile' => '09121234568',
            'password' => 'system-pass',
        ])->assertForbidden();

        $admin = User::factory()->admin()->create(['mobile' => '09127777777']);
        $athlete = User::factory()->create(['mobile' => '09120001111']);
        Auth::forgetGuards();
        $adminToken = $this->postJson('/api/auth/staff/login', [
            'mobile' => '09127777777',
            'password' => 'password-test',
        ])->assertOk()->json('token');

        $updateId = Permission::query()->where('name', 'users.update')->value('id');
        $adminRoleId = Role::query()->where('name', 'admin')->value('id');
        DB::table('permission_role')->where('role_id', $adminRoleId)->where('permission_id', $updateId)->delete();

        Auth::forgetGuards();
        $this->withToken($adminToken)->patchJson('/api/admin/users/'.$athlete->id.'/access', [
            'status' => 'blocked',
        ])->assertForbidden();

        $this->assertNotNull($admin->id);
    }
}
