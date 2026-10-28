<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Support\AccessCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach ([Permission::CoursesView, Permission::CoursesManage] as $permission) {
            $exists = DB::table('permissions')->where('name', $permission->value)->exists();
            if (! $exists) {
                DB::statement(
                    'INSERT INTO permissions (name, created_at, updated_at) VALUES (?::permission_name, ?, ?)',
                    [$permission->value, $now, $now],
                );
            }
        }

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->pluck('id', 'name');

        foreach ([UserRole::SuperAdmin, UserRole::Admin] as $role) {
            foreach (AccessCatalog::permissionsFor($role) as $permission) {
                $roleId = $roleIds[$role->value] ?? null;
                $permissionId = $permissionIds[$permission->value] ?? null;
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
        }
    }

    public function down(): void
    {
        $names = ['courses.view', 'courses.manage'];
        $ids = DB::table('permissions')->whereIn('name', $names)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permission_user')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
