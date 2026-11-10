<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLaneRentalRequest;
use App\Models\Lane;
use App\Models\LaneRental;
use App\Models\User;
use App\Support\AccessControlPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaneRentalController extends Controller
{
    public function index(): JsonResponse
    {
        $rentals = LaneRental::query()
            ->with(['lane.type', 'renter'])
            ->withCount('members')
            ->latest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rentals->map(fn (LaneRental $rental) => $this->payload($rental))->values(),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $mobile = trim((string) $request->query('mobile', ''));
        if (! preg_match('/^09[0-9]{9}$/', $mobile)) {
            return response()->json(['message' => 'موبایل باید ۱۱ رقم و با ۰۹ شروع شود.'], 422);
        }

        $user = User::query()->where('mobile', $mobile)->first();

        return response()->json([
            'user' => $user ? $this->person($user) : null,
        ]);
    }

    public function store(StoreLaneRentalRequest $request): JsonResponse
    {
        $lane = Lane::query()->findOrFail($request->integer('lane_id'));
        $students = $request->integer('students_count');
        if ($students < $lane->min_people || $students > $lane->max_people) {
            return response()->json([
                'message' => "تعداد شاگردها باید بین {$lane->min_people} و {$lane->max_people} باشد.",
                'errors' => ['students_count' => ["تعداد شاگردها باید بین {$lane->min_people} و {$lane->max_people} باشد."]],
            ], 422);
        }

        $card = $request->string('access_card_number')->toString();
        $existing = User::query()->where('mobile', $request->string('mobile')->toString())->first();
        $cardOwner = $this->cardOwner($card, $existing?->id);
        if ($cardOwner) {
            return response()->json([
                'message' => 'این شماره کارت برای کاربر دیگری ثبت شده است.',
                'errors' => ['access_card_number' => ['این شماره کارت برای کاربر دیگری ثبت شده است.']],
            ], 422);
        }

        if (! $existing && ($request->validated('first_name') === null || $request->validated('last_name') === null)) {
            return response()->json([
                'message' => 'این موبایل در سیستم نیست. نام و نام خانوادگی را وارد کنید.',
                'errors' => [
                    'first_name' => ['نام را وارد کنید.'],
                    'last_name' => ['نام خانوادگی را وارد کنید.'],
                ],
            ], 422);
        }

        $rental = DB::transaction(function () use ($request, $lane, $existing, $students, $card) {
            $user = $existing ?? new User([
                'mobile' => $request->string('mobile')->toString(),
                'first_name' => $request->validated('first_name'),
                'last_name' => $request->validated('last_name'),
                'registration_step' => 0,
                'introduced_by' => $request->user()?->id,
            ]);

            if (! $existing) {
                $user->status = UserStatus::Active;
                $user->phone_verified_at = now();
                $user->access_granted_at = now();
                $user->registration_completed_at = now();
                $user->password = $request->string('password')->toString();
            }

            $user->access_card_number = $card;
            $user->access_card_enrolled_at = now();
            $user->save();
            $this->grantRenter($user);

            $sessions = $request->integer('sessions_count');

            return LaneRental::query()->create([
                'lane_id' => $lane->id,
                'renter_user_id' => $user->id,
                'students_count' => $students,
                'sessions_total' => $sessions,
                'sessions_remaining' => $sessions,
                'access_card_number' => $card,
                'created_by' => $request->user()?->id,
            ]);
        });

        return response()->json([
            'message' => 'لاین به اجاره‌کننده تخصیص داده شد.',
            'rental' => $this->payload($rental->load(['lane.type', 'renter'])->loadCount('members')),
        ], 201);
    }

    private function grantRenter(User $user): void
    {
        $user->load('roles');
        $names = $user->roles
            ->map(fn ($role) => $role->name instanceof UserRole ? $role->name->value : (string) $role->name)
            ->push(UserRole::LaneRenter->value)
            ->toBase()
            ->unique()
            ->values()
            ->all();
        $user->syncRoles($names);
    }

    private function cardOwner(string $card, ?int $ignoreUserId): ?User
    {
        $canonical = AccessControlPayload::canonicalCard($card);
        if ($canonical === '0') {
            return null;
        }

        return User::query()
            ->when($ignoreUserId, fn ($query) => $query->whereKeyNot($ignoreUserId))
            ->whereRaw("ltrim(access_card_number, '0') = ?", [$canonical])
            ->first();
    }

    /** @return array<string, mixed> */
    private function person(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'mobile' => $user->mobile,
        ];
    }

    /** @return array<string, mixed> */
    private function payload(LaneRental $rental): array
    {
        return [
            'id' => $rental->id,
            'students_count' => $rental->students_count,
            'sessions_total' => $rental->sessions_total,
            'sessions_remaining' => $rental->sessions_remaining,
            'access_card_number' => $rental->access_card_number,
            'members_count' => (int) ($rental->members_count ?? $rental->members()->count()),
            'created_at' => $rental->created_at?->toIso8601String(),
            'lane' => $rental->lane ? [
                'id' => $rental->lane->id,
                'title' => $rental->lane->title,
                'min_people' => $rental->lane->min_people,
                'max_people' => $rental->lane->max_people,
                'fee' => $rental->lane->fee,
                'type' => $rental->lane->type?->name,
            ] : null,
            'renter' => $rental->renter ? $this->person($rental->renter) : null,
        ];
    }
}
