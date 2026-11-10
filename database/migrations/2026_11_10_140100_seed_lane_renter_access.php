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

        if (! DB::table('roles')->where('name', UserRole::LaneRenter->value)->exists()) {
            DB::statement(
                'INSERT INTO roles (name, created_at, updated_at) VALUES (?::user_role, ?, ?)',
                [UserRole::LaneRenter->value, $now, $now],
            );
        }

        foreach ([Permission::LanesManage, Permission::LanesMembers] as $permission) {
            if (! DB::table('permissions')->where('name', $permission->value)->exists()) {
                DB::statement(
                    'INSERT INTO permissions (name, created_at, updated_at) VALUES (?::permission_name, ?, ?)',
                    [$permission->value, $now, $now],
                );
            }
        }

        $links = [
            UserRole::SuperAdmin->value => [Permission::LanesManage->value, Permission::LanesMembers->value],
            UserRole::Admin->value => [Permission::LanesManage->value],
            UserRole::LaneRenter->value => [Permission::LanesMembers->value],
        ];

        foreach ($links as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }
            foreach ($permissionNames as $permissionName) {
                $permissionId = DB::table('permissions')->where('name', $permissionName)->value('id');
                if (! $permissionId) {
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
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', ['lanes.manage', 'lanes.members'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        $roleId = DB::table('roles')->where('name', 'lane_renter')->value('id');
        if ($roleId) {
            DB::table('role_user')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
