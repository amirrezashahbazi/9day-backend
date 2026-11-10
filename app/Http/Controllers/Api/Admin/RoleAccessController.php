<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\AccessCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleAccessController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->assertSuperAdmin($request);
        $role = $this->adminRole();
        $role->load('permissions');

        $labels = AccessCatalog::staffPermissionLabels();

        return response()->json([
            'role' => [
                'name' => UserRole::Admin->value,
                'label' => 'ادمین',
                'permissions' => $role->permissions
                    ->map(fn ($permission) => $permission->name instanceof Permission ? $permission->name->value : (string) $permission->name)
                    ->values(),
            ],
            'permissions' => collect($labels)->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->assertSuperAdmin($request);
        $allowed = array_keys(AccessCatalog::staffPermissionLabels());
        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct'],
        ]);

        $chosen = array_values(array_intersect($data['permissions'], $allowed));
        $role = $this->adminRole();
        $role->load('permissions');
        $kept = $role->permissions
            ->map(fn ($permission) => $permission->name instanceof Permission ? $permission->name->value : (string) $permission->name)
            ->reject(fn (string $name) => in_array($name, $allowed, true))
            ->values()
            ->all();

        $ids = \App\Models\Permission::query()
            ->whereIn('name', array_values(array_unique([...$chosen, ...$kept])))
            ->pluck('id');
        $role->permissions()->sync($ids);

        return response()->json([
            'message' => 'دسترسی‌های نقش ادمین ذخیره شد.',
            'permissions' => $chosen,
        ]);
    }

    private function assertSuperAdmin(Request $request): void
    {
        if (! $request->user()?->hasRole(UserRole::SuperAdmin)) {
            abort(403, 'فقط سوپر ادمین می‌تواند دسترسی نقش‌ها را تغییر دهد.');
        }
    }

    private function adminRole(): Role
    {
        return Role::query()->where('name', UserRole::Admin->value)->firstOrFail();
    }
}
