<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $users = User::query()
            ->visibleTo($request->user())
            ->withRole(UserRole::Admin)
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($inner) use ($term) {
                    $inner->where('mobile', 'like', "%{$term}%")
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("concat(coalesce(first_name, ''), ' ', coalesce(last_name, '')) like ?", ["%{$term}%"]);
                });
            })
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $users->map(fn (User $user) => $this->payload($user))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateUser($request, null);

        $user = new User([
            'mobile' => $data['mobile'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'password' => $data['password'],
            'registration_step' => 7,
            'phone_verified_at' => now(),
            'registration_completed_at' => now(),
            'access_granted_at' => now(),
            'introduced_by' => $request->user()?->id,
        ]);
        $user->status = UserStatus::Active;
        $user->save();
        $user->syncRoles([UserRole::Admin]);

        return response()->json([
            'message' => 'کاربر سیستم ساخته شد.',
            'user' => $this->payload($user),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->assertSystemUser($user);
        $data = $this->validateUser($request, $user);
        $user->fill([
            'mobile' => $data['mobile'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
        ]);
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();
        $user->syncRoles([UserRole::Admin]);

        return response()->json([
            'message' => 'کاربر سیستم به‌روز شد.',
            'user' => $this->payload($user),
        ]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $this->assertSystemUser($user);
        if ($request->user()?->id === $user->id) {
            return response()->json(['message' => 'نمی‌توانید وضعیت حساب خودتان را تغییر دهید.'], 422);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Blocked->value])],
        ], [
            'status.required' => 'وضعیت را مشخص کنید.',
            'status.in' => 'وضعیت باید فعال یا غیرفعال باشد.',
        ]);

        $user->status = UserStatus::from($data['status']);
        $user->save();
        if ($user->status === UserStatus::Blocked) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => $user->status === UserStatus::Blocked ? 'کاربر غیرفعال شد.' : 'کاربر فعال شد.',
            'user' => $this->payload($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->assertSystemUser($user);
        if ($request->user()?->id === $user->id) {
            return response()->json(['message' => 'نمی‌توانید حساب خودتان را حذف کنید.'], 422);
        }
        $user->delete();

        return response()->json(['message' => 'کاربر سیستم حذف شد.']);
    }

    /** @return array{first_name: string, last_name: string, mobile: string, password: ?string} */
    private function validateUser(Request $request, ?User $user): array
    {
        $request->merge([
            'mobile' => $this->englishDigits((string) $request->input('mobile', '')),
        ]);
        $name = ['required', 'string', 'min:2', 'max:100', 'regex:/^[\p{Arabic}A-Za-z\s\x{200C}]+$/u'];
        $data = $request->validate([
            'first_name' => $name,
            'last_name' => $name,
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/', Rule::unique('users', 'mobile')->ignore($user?->id)->whereNull('deleted_at')],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'role' => ['required', Rule::in([UserRole::Admin->value])],
        ], [
            'first_name.required' => 'نام را وارد کنید.',
            'last_name.required' => 'نام خانوادگی را وارد کنید.',
            'first_name.regex' => 'نام فقط می‌تواند حرف باشد.',
            'last_name.regex' => 'نام خانوادگی فقط می‌تواند حرف باشد.',
            'mobile.regex' => 'موبایل باید ۱۱ رقم و با ۰۹ شروع شود.',
            'mobile.unique' => 'این موبایل قبلاً ثبت شده است.',
            'password.required' => 'رمز عبور را وارد کنید.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر است.',
            'role.in' => 'نقش کاربر سیستم فقط ادمین است.',
        ]);

        return $data;
    }

    private function englishDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace([...$persian, ...$arabic], [...$english, ...$english], trim($value));
    }

    private function assertSystemUser(User $user): void
    {
        if ($user->hasRole(UserRole::SuperAdmin) || ! $user->hasRole(UserRole::Admin)) {
            abort(404);
        }
    }

    /** @return array<string, mixed> */
    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'mobile' => $user->mobile,
            'role' => UserRole::Admin->value,
            'status' => $user->status->value,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
