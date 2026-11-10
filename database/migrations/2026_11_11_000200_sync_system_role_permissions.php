<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permission = Permission::TimeSlotsManage->value;

        if (! DB::table('permissions')->where('name', $permission)->exists()) {
            DB::statement(
                'INSERT INTO permissions (name, created_at, updated_at) VALUES (?::permission_name, ?, ?)',
                [$permission, $now, $now],
            );
        }

        $permissionId = DB::table('permissions')->where('name', $permission)->value('id');
        foreach ([UserRole::SuperAdmin->value, UserRole::Admin->value] as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId || ! $permissionId) {
                continue;
            }
            $exists = DB::table('permission_role')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('permission_role')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $createId = DB::table('permissions')->where('name', Permission::UsersCreate->value)->value('id');
        $adminId = DB::table('roles')->where('name', UserRole::Admin->value)->value('id');
        if ($createId && $adminId) {
            DB::table('permission_role')
                ->where('role_id', $adminId)
                ->where('permission_id', $createId)
                ->delete();
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', Permission::TimeSlotsManage->value)->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
