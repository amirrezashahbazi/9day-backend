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
        $exists = DB::table('roles')->where('name', UserRole::Athlete->value)->exists();
        if (! $exists) {
            DB::statement(
                'INSERT INTO roles (name, created_at, updated_at) VALUES (?::user_role, ?, ?)',
                [UserRole::Athlete->value, $now, $now],
            );
        }

        $roleId = DB::table('roles')->where('name', UserRole::Athlete->value)->value('id');
        $permissionId = DB::table('permissions')->where('name', Permission::RegistrationManage->value)->value('id');

        if ($roleId && $permissionId) {
            $linked = DB::table('permission_role')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();
            if (! $linked) {
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
        $roleId = DB::table('roles')->where('name', 'athlete')->value('id');
        if ($roleId) {
            DB::table('permission_role')->where('role_id', $roleId)->delete();
            DB::table('role_user')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
