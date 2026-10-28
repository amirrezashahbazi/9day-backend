<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Support\AccessCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createTypes();
        $this->convertUserColumns();
        $this->createAccessTables();
        $this->seedAccess();
        $this->copyExistingRoles();

        DB::statement('DROP INDEX IF EXISTS users_status_role_deleted_at_index');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        DB::statement('CREATE INDEX users_status_deleted_at_index ON users (status, deleted_at)');
        DB::statement('CREATE INDEX users_gender_status_deleted_at_index ON users (gender, status, deleted_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_gender_status_deleted_at_index');
        DB::statement('DROP INDEX IF EXISTS users_status_deleted_at_index');

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user');
        });

        DB::statement("
            UPDATE users
            SET role = roles.name::text
            FROM role_user
            JOIN roles ON roles.id = role_user.role_id
            WHERE role_user.user_id = users.id
        ");

        Schema::table('users', function (Blueprint $table) {
            $table->index(['status', 'role', 'deleted_at']);
        });

        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');

        $this->revertColumn('gender', 'varchar(10)');
        $this->revertColumn('education_level', 'varchar(30)');
        $this->revertColumn('referral_source', 'varchar(30)');
        DB::statement('ALTER TABLE users ALTER COLUMN status DROP DEFAULT');
        $this->revertColumn('status', 'varchar(20)');
        DB::statement("ALTER TABLE users ALTER COLUMN status SET DEFAULT 'draft'");

        $this->dropTypes();
    }

    private function createTypes(): void
    {
        $types = [
            'gender' => "'male', 'female'",
            'user_status' => "'draft', 'pending', 'active', 'blocked'",
            'education_level' => "'primary', 'lower_secondary', 'high_school', 'associate', 'bachelor', 'master', 'doctorate', 'other'",
            'referral_source' => "'website', 'instagram', 'ads', 'friends', 'other'",
            'user_role' => "'super_admin', 'admin', 'user', 'athlete'",
            'permission_name' => "'panel.access', 'users.view', 'users.create', 'users.update', 'reports.view', 'registration.manage', 'courses.view', 'courses.manage'",
        ];

        foreach ($types as $name => $values) {
            DB::statement("DO \$\$ BEGIN CREATE TYPE {$name} AS ENUM ({$values}); EXCEPTION WHEN duplicate_object THEN NULL; END \$\$;");
        }
    }

    private function convertUserColumns(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN gender TYPE gender USING gender::gender');
        DB::statement('ALTER TABLE users ALTER COLUMN education_level TYPE education_level USING education_level::education_level');
        DB::statement('ALTER TABLE users ALTER COLUMN referral_source TYPE referral_source USING referral_source::referral_source');
        DB::statement('ALTER TABLE users ALTER COLUMN status DROP DEFAULT');
        DB::statement('ALTER TABLE users ALTER COLUMN status TYPE user_status USING status::user_status');
        DB::statement("ALTER TABLE users ALTER COLUMN status SET DEFAULT 'draft'");
    }

    private function createAccessTables(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('ALTER TABLE roles ADD COLUMN name user_role NOT NULL');
        DB::statement('CREATE UNIQUE INDEX roles_name_active_unique ON roles (name) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX roles_name_deleted_at_index ON roles (name, deleted_at)');

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('ALTER TABLE permissions ADD COLUMN name permission_name NOT NULL');
        DB::statement('CREATE UNIQUE INDEX permissions_name_active_unique ON permissions (name) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX permissions_name_deleted_at_index ON permissions (name, deleted_at)');

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['role_id', 'permission_id']);
            $table->index(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'role_id']);
            $table->index(['role_id', 'user_id']);
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'permission_id']);
            $table->index(['permission_id', 'user_id']);
        });
    }

    private function seedAccess(): void
    {
        $now = now();

        foreach (UserRole::cases() as $role) {
            DB::statement(
                'INSERT INTO roles (name, created_at, updated_at) VALUES (?::user_role, ?, ?)',
                [$role->value, $now, $now],
            );
        }

        foreach (Permission::cases() as $permission) {
            DB::statement(
                'INSERT INTO permissions (name, created_at, updated_at) VALUES (?::permission_name, ?, ?)',
                [$permission->value, $now, $now],
            );
        }

        $roleIds = DB::table('roles')->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->pluck('id', 'name');

        foreach (UserRole::cases() as $role) {
            foreach (AccessCatalog::permissionsFor($role) as $permission) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleIds[$role->value],
                    'permission_id' => $permissionIds[$permission->value],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function copyExistingRoles(): void
    {
        DB::statement("
            INSERT INTO role_user (user_id, role_id, created_at, updated_at)
            SELECT users.id, roles.id, NOW(), NOW()
            FROM users
            JOIN roles ON roles.name = users.role::user_role
        ");
    }

    private function revertColumn(string $column, string $type): void
    {
        DB::statement("ALTER TABLE users ALTER COLUMN {$column} TYPE {$type} USING {$column}::text");
    }

    private function dropTypes(): void
    {
        foreach (['gender', 'user_status', 'education_level', 'referral_source', 'user_role', 'permission_name'] as $type) {
            DB::statement("DROP TYPE IF EXISTS {$type}");
        }
    }
};
