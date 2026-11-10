<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Seeders\SuperAdminSeeder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRolesRequest;
use App\Http\Requests\Admin\BulkIdsRequest;
use App\Http\Requests\Admin\IndexUsersRequest;
use App\Http\Requests\Admin\ReportUsersRequest;
use App\Http\Controllers\Api\Admin\MobileVerificationController;
use App\Http\Requests\Admin\SaveUserRegistrationStepRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateAccessRequest;
use App\Http\Resources\UserResource;
use App\Models\GateTransit;
use App\Models\User;
use App\Services\RegistrationStepSaver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $query = User::query()->members();
        $todayStarts = now('Asia/Tehran')->startOfDay()->utc();
        $todayEnds = now('Asia/Tehran')->endOfDay()->utc();

        return response()->json([
            'total' => (clone $query)->count(),
            'draft' => (clone $query)->where('status', UserStatus::Draft)->count(),
            'pending' => (clone $query)->where('status', UserStatus::Pending)->count(),
            'active' => (clone $query)->where('status', UserStatus::Active)->count(),
            'blocked' => (clone $query)->where('status', UserStatus::Blocked)->count(),
            'today' => (clone $query)->whereBetween('created_at', [$todayStarts, $todayEnds])->count(),
        ]);
    }

    public function transitStats(Request $request): JsonResponse
    {
        $range = $request->query('range') === 'month' ? 'month' : 'week';
        $days = $range === 'month' ? 30 : 7;
        $start = now('Asia/Tehran')->startOfDay()->subDays($days - 1);
        $end = now('Asia/Tehran')->endOfDay();

        $rows = GateTransit::query()
            ->whereBetween('created_at', [$start->clone()->utc(), $end->clone()->utc()])
            ->get(['allowed', 'created_at']);

        $buckets = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $buckets[$day->toDateString()] = [
                'date' => $day->toDateString(),
                'total' => 0,
                'allowed' => 0,
                'denied' => 0,
            ];
        }

        foreach ($rows as $row) {
            $key = $row->created_at?->timezone('Asia/Tehran')->toDateString();
            if (! isset($buckets[$key])) {
                continue;
            }
            $buckets[$key]['total']++;
            if ($row->allowed) {
                $buckets[$key]['allowed']++;
            } else {
                $buckets[$key]['denied']++;
            }
        }

        $series = array_values($buckets);

        return response()->json([
            'range' => $range,
            'total' => array_sum(array_column($series, 'total')),
            'allowed' => array_sum(array_column($series, 'allowed')),
            'denied' => array_sum(array_column($series, 'denied')),
            'series' => $series,
        ]);
    }

    public function index(IndexUsersRequest $request): JsonResponse
    {
        $actor = $request->user();
        $filters = $request->validated();

        $users = User::query()
            ->visibleTo($actor)
            ->with(['introducer', 'province', 'city', 'district', 'roles.permissions', 'permissions'])
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['role'] ?? null), fn ($query) => $query->withRole($filters['role']))
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters) {
                $term = trim($filters['q']);
                $query->where(function ($inner) use ($term) {
                    $inner->where('mobile', 'like', "%{$term}%")
                        ->orWhere('national_code', 'like', "%{$term}%")
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("concat(coalesce(first_name, ''), ' ', coalesce(last_name, '')) like ?", ["%{$term}%"]);
                });
            })
            ->when(filled($filters['name'] ?? null), function ($query) use ($filters) {
                $term = trim($filters['name']);
                $query->where(function ($inner) use ($term) {
                    $inner->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("concat(coalesce(first_name, ''), ' ', coalesce(last_name, '')) like ?", ["%{$term}%"]);
                });
            })
            ->when(filled($filters['first_name'] ?? null), fn ($query) => $query->where('first_name', 'like', '%'.$filters['first_name'].'%'))
            ->when(filled($filters['last_name'] ?? null), fn ($query) => $query->where('last_name', 'like', '%'.$filters['last_name'].'%'))
            ->when(filled($filters['national_code'] ?? null), fn ($query) => $query->where('national_code', $filters['national_code']))
            ->when(filled($filters['mobile'] ?? null), fn ($query) => $query->where('mobile', 'like', '%'.$filters['mobile'].'%'))
            ->when(filled($filters['file_number'] ?? null), fn ($query) => $query->where('file_number', 'like', '%'.$filters['file_number'].'%'))
            ->when(filled($filters['registered_from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $filters['registered_from']))
            ->when(filled($filters['registered_to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $filters['registered_to']))
            ->latest('id')
            ->paginate($this->pageSize($filters));

        return response()->json([
            'data' => UserResource::collection($users)->resolve(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $actor = $request->user();
        $roleInput = $request->input('role', 'athlete');
        $role = UserRole::Athlete;

        if ($roleInput === 'admin') {
            if (! $actor->hasRole(UserRole::SuperAdmin)) {
                abort(403, 'ادمین نمی‌تواند ادمین جدید بسازد.');
            }
            $role = UserRole::Admin;
        } elseif ($roleInput === 'user') {
            $role = UserRole::User;
        } elseif ($roleInput === 'athlete') {
            $role = UserRole::Athlete;
        }

        $mobile = $request->string('mobile')->toString();
        $skipPhone = $request->boolean('skip_phone_verification');
        $phoneVerified = Cache::pull(MobileVerificationController::cacheKey($mobile), false);

        $user = DB::transaction(function () use ($request, $actor, $role, $mobile, $skipPhone, $phoneVerified) {
            $user = new User([
                'mobile' => $mobile,
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'registration_step' => 0,
                'introduced_by' => $actor->id,
                'password' => $role === UserRole::Admin ? $request->input('password') : null,
                'phone_verified_at' => $skipPhone ? null : ($phoneVerified ? now() : null),
            ]);

            $user->status = UserStatus::Active;
            $user->access_granted_at = now();

            $user->save();
            $user->syncRoles([$role]);

            return $user;
        });

        Log::channel('admin')->info('User introduced', [
            'actor_id' => $actor->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => $user->status->value,
            'phone_verified' => $user->phone_verified_at !== null,
            'skip_phone_verification' => $skipPhone,
        ]);

        return response()->json([
            'message' => 'کاربر معرفی شد.',
            'user' => new UserResource($user->load('introducer')),
        ], 201);
    }

    public function report(ReportUsersRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $users = User::query()
            ->visibleTo($request->user())
            ->with(['province', 'city', 'district', 'roles.permissions', 'permissions'])
            ->when(filled($filters['first_name'] ?? null), fn ($query) => $query->where('first_name', 'like', '%'.$filters['first_name'].'%'))
            ->when(filled($filters['last_name'] ?? null), fn ($query) => $query->where('last_name', 'like', '%'.$filters['last_name'].'%'))
            ->when(filled($filters['national_code'] ?? null), fn ($query) => $query->where('national_code', $filters['national_code']))
            ->when(filled($filters['mobile'] ?? null), fn ($query) => $query->where('mobile', 'like', '%'.$filters['mobile'].'%'))
            ->when(filled($filters['file_number'] ?? null), fn ($query) => $query->where('file_number', 'like', '%'.$filters['file_number'].'%'))
            ->when(filled($filters['birth_from'] ?? null), fn ($query) => $query->whereDate('birth_date', '>=', $filters['birth_from']))
            ->when(filled($filters['birth_to'] ?? null), fn ($query) => $query->whereDate('birth_date', '<=', $filters['birth_to']))
            ->when(filled($filters['registered_from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $filters['registered_from']))
            ->when(filled($filters['registered_to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $filters['registered_to']))
            ->when(filled($filters['signed_from'] ?? null), fn ($query) => $query->whereDate('signed_at', '>=', $filters['signed_from']))
            ->when(filled($filters['signed_to'] ?? null), fn ($query) => $query->whereDate('signed_at', '<=', $filters['signed_to']))
            ->when(filled($filters['province_id'] ?? null), fn ($query) => $query->where('province_id', $filters['province_id']))
            ->when(filled($filters['city_id'] ?? null), fn ($query) => $query->where('city_id', $filters['city_id']))
            ->when(filled($filters['district_id'] ?? null), fn ($query) => $query->where('district_id', $filters['district_id']))
            ->when(filled($filters['education_level'] ?? null), fn ($query) => $query->where('education_level', $filters['education_level']))
            ->when(filled($filters['role'] ?? null), fn ($query) => $query->withRole($filters['role']))
            ->when(! empty($filters['courses'] ?? []), function ($query) use ($filters) {
                $map = [
                    'swimming' => 'course_swimming',
                    'water_polo' => 'course_water_polo',
                    'diving' => 'course_diving',
                    'private' => 'course_private',
                    'semi_private' => 'course_semi_private',
                ];
                $query->where(function ($inner) use ($filters, $map) {
                    foreach ($filters['courses'] as $course) {
                        if (isset($map[$course])) {
                            $inner->orWhere($map[$course], true);
                        }
                    }
                });
            })
            ->latest('id')
            ->paginate($this->pageSize($filters));

        return response()->json([
            'data' => UserResource::collection($users)->resolve(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->assertCanView($request->user(), $user);
        $user->load(['introducer', 'roles.permissions', 'permissions', 'province', 'city', 'district', 'kinships.relative']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function updateRegistration(
        SaveUserRegistrationStepRequest $request,
        User $user,
        int $step,
        RegistrationStepSaver $saver,
    ): JsonResponse {
        $this->assertCanManage($request->user(), $user);
        $user = $saver->save($user, $step, $request);

        Log::channel('admin')->info('Admin saved registration step', [
            'actor_id' => $request->user()->id,
            'user_id' => $user->id,
            'step' => $step,
        ]);

        return response()->json([
            'message' => $step === 7 ? 'ثبت‌نام کاربر نهایی شد.' : 'این مرحله ذخیره شد.',
            'user' => new UserResource($user),
        ]);
    }

    public function assignRoles(AssignRolesRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor->hasRole(UserRole::SuperAdmin)) {
            abort(403, 'فقط سوپر ادمین می‌تواند نقش کاربران را تغییر دهد.');
        }

        $roleNames = collect($request->input('roles', []))
            ->map(fn ($role) => (string) $role)
            ->unique()
            ->values()
            ->all();

        $updated = 0;
        $skipped = 0;

        $users = User::query()
            ->with('roles')
            ->whereIn('id', $request->input('user_ids', []))
            ->get();

        foreach ($users as $user) {
            try {
                $this->assertCanManage($actor, $user);
            } catch (\Throwable) {
                $skipped++;
                continue;
            }

            $current = $user->roles
                ->map(fn ($role) => $role->name instanceof UserRole ? $role->name->value : (string) $role->name)
                ->all();

            $merged = collect($current)
                ->merge($roleNames)
                ->unique()
                ->reject(fn ($role) => $role === UserRole::SuperAdmin->value)
                ->values()
                ->all();

            if ($merged === []) {
                $merged = [UserRole::User->value];
            }

            $user->syncRoles($merged);
            $updated++;

            Log::channel('admin')->info('Roles assigned', [
                'actor_id' => $actor->id,
                'user_id' => $user->id,
                'added' => $roleNames,
                'roles' => $merged,
            ]);
        }

        return response()->json([
            'message' => $updated > 0
                ? "نقش برای {$updated} کاربر به‌روزرسانی شد."
                : 'هیچ کاربری به‌روزرسانی نشد.',
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        $this->assertCanDeleteUsers($actor);
        $this->assertCanManage($actor, $user);
        $user->delete();

        Log::channel('admin')->info('User deleted', [
            'actor_id' => $actor->id,
            'user_id' => $user->id,
        ]);

        return response()->json(['message' => 'کاربر حذف شد.']);
    }

    public function destroyMany(BulkIdsRequest $request): JsonResponse
    {
        $actor = $request->user();
        $this->assertCanDeleteUsers($actor);
        $deleted = 0;
        $skipped = 0;

        $users = User::query()->whereIn('id', $request->input('ids', []))->get();
        foreach ($users as $user) {
            try {
                $this->assertCanManage($actor, $user);
            } catch (\Throwable) {
                $skipped++;
                continue;
            }
            $user->delete();
            $deleted++;
            Log::channel('admin')->info('User deleted', [
                'actor_id' => $actor->id,
                'user_id' => $user->id,
            ]);
        }

        return response()->json([
            'message' => $deleted > 0
                ? "{$deleted} کاربر حذف شد."
                : 'هیچ کاربری حذف نشد.',
            'deleted' => $deleted,
            'skipped' => $skipped,
        ]);
    }

    public function updateAccess(UpdateAccessRequest $request, User $user): JsonResponse
    {
        $actor = $request->user();
        $this->assertCanManage($actor, $user);

        $before = [
            'roles' => $user->roles->pluck('name')->map(fn ($name) => $name instanceof UserRole ? $name->value : (string) $name)->all(),
            'status' => $user->status->value,
        ];

        if ($request->exists('roles') || $request->filled('role') || $request->exists('permissions')) {
            if (! $actor->hasRole(UserRole::SuperAdmin)) {
                abort(403, 'فقط سوپر ادمین می‌تواند نقش و دسترسی را تغییر دهد.');
            }
        }

        if ($request->exists('roles')) {
            $user->syncRoles($request->input('roles', []));
        } elseif ($request->filled('role')) {
            $user->syncRoles([UserRole::from($request->string('role')->toString())]);
        }

        if ($request->exists('permissions')) {
            $user->syncPermissions($request->input('permissions', []));
        }

        $status = UserStatus::from($request->string('status')->toString());
        $user->status = $status;

        if ($status === UserStatus::Active && $user->access_granted_at === null) {
            $user->access_granted_at = now();
        }

        $user->save();

        Log::channel('admin')->info('Access updated', [
            'actor_id' => $actor->id,
            'user_id' => $user->id,
            'before' => $before,
            'after' => [
                'roles' => $user->roles()->pluck('roles.name')->all(),
                'status' => $user->status->value,
            ],
        ]);

        return response()->json([
            'message' => 'دسترسی کاربر به‌روزرسانی شد.',
            'user' => new UserResource($user->load('introducer')),
        ]);
    }

    private function pageSize(array $filters): int
    {
        return min(500, max(1, (int) ($filters['per_page'] ?? 25)));
    }

    private function assertCanView(User $actor, User $target): void
    {
        if (! $actor->hasRole(UserRole::SuperAdmin) && $target->hasRole(UserRole::SuperAdmin)) {
            abort(403, 'به این حساب دسترسی ندارید.');
        }
    }

    private function assertCanDeleteUsers(User $actor): void
    {
        if ($actor->hasRole(UserRole::SuperAdmin) || $actor->mobile === SuperAdminSeeder::MOBILE) {
            return;
        }

        abort(403, 'حذف کاربر فقط برای سوپر ادمین مجاز است.');
    }

    private function assertCanManage(User $actor, User $target): void
    {
        $this->assertCanView($actor, $target);

        if ($actor->id === $target->id) {
            abort(403, 'نمی‌توانید دسترسی حساب خود را از این بخش تغییر دهید.');
        }

        if ($target->hasRole(UserRole::SuperAdmin)) {
            abort(403, 'حساب سوپر ادمین قابل تغییر نیست.');
        }

        if ($actor->hasRole(UserRole::Admin) && ! $actor->hasRole(UserRole::SuperAdmin) && ! $target->isMember()) {
            abort(403, 'ادمین فقط می‌تواند کاربران عادی را مدیریت کند.');
        }
    }
}
