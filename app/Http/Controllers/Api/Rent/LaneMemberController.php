<?php

namespace App\Http\Controllers\Api\Rent;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rent\StoreLaneMemberRequest;
use App\Models\LaneRental;
use App\Models\LaneRentalMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LaneMemberController extends Controller
{
    public function index(): JsonResponse
    {
        $rentals = LaneRental::query()
            ->where('renter_user_id', auth()->id())
            ->with(['lane.type', 'members.user'])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $rentals->map(fn (LaneRental $rental) => $this->rentalPayload($rental))->values(),
        ]);
    }

    public function store(StoreLaneMemberRequest $request, LaneRental $laneRental): JsonResponse
    {
        $this->owns($laneRental);
        $laneRental->load('lane');
        $capacity = (int) $laneRental->lane->max_people;
        if ($laneRental->members()->count() >= $capacity) {
            return response()->json([
                'message' => "ظرفیت این لاین {$capacity} نفر است.",
            ], 422);
        }

        $mobile = $request->string('mobile')->toString();
        $existing = User::query()->where('mobile', $mobile)->first();
        if (! $existing && ($request->validated('first_name') === null || $request->validated('last_name') === null)) {
            return response()->json([
                'message' => 'این موبایل در سیستم نیست. نام و نام خانوادگی را وارد کنید.',
                'errors' => [
                    'first_name' => ['نام را وارد کنید.'],
                    'last_name' => ['نام خانوادگی را وارد کنید.'],
                ],
            ], 422);
        }

        if ($existing && $laneRental->members()->where('user_id', $existing->id)->exists()) {
            return response()->json(['message' => 'این کاربر قبلاً به این لاین اختصاص داده شده است.'], 422);
        }

        DB::transaction(function () use ($request, $laneRental, $existing, $mobile) {
            $user = $existing;
            if (! $user) {
                $user = new User([
                    'mobile' => $mobile,
                    'first_name' => $request->validated('first_name'),
                    'last_name' => $request->validated('last_name'),
                    'national_code' => $request->validated('national_code'),
                    'registration_step' => 0,
                    'introduced_by' => $request->user()?->id,
                ]);
                $user->status = UserStatus::Active;
                $user->phone_verified_at = now();
                $user->access_granted_at = now();
                $user->registration_completed_at = now();
                $user->save();
                $user->syncRoles([UserRole::Athlete]);
            }

            LaneRentalMember::query()->create([
                'lane_rental_id' => $laneRental->id,
                'user_id' => $user->id,
                'created_by' => $request->user()?->id,
            ]);
        });

        return response()->json([
            'message' => $existing ? 'کاربر به لاین اختصاص داده شد.' : 'کاربر ساخته و به لاین اختصاص داده شد.',
            'rental' => $this->rentalPayload($laneRental->fresh()->load(['lane.type', 'members.user'])),
        ], 201);
    }

    public function destroy(LaneRental $laneRental, User $user): JsonResponse
    {
        $this->owns($laneRental);
        $laneRental->members()->where('user_id', $user->id)->delete();

        return response()->json([
            'message' => 'کاربر از این لاین برداشته شد.',
            'rental' => $this->rentalPayload($laneRental->fresh()->load(['lane.type', 'members.user'])),
        ]);
    }

    private function owns(LaneRental $rental): void
    {
        if ((int) $rental->renter_user_id !== (int) auth()->id()) {
            abort(403, 'این لاین به شما تخصیص داده نشده است.');
        }
    }

    /** @return array<string, mixed> */
    private function rentalPayload(LaneRental $rental): array
    {
        return [
            'id' => $rental->id,
            'students_count' => $rental->students_count,
            'sessions_total' => $rental->sessions_total,
            'sessions_remaining' => $rental->sessions_remaining,
            'access_card_number' => $rental->access_card_number,
            'lane' => [
                'id' => $rental->lane?->id,
                'title' => $rental->lane?->title,
                'type' => $rental->lane?->type?->name,
                'min_people' => $rental->lane?->min_people,
                'max_people' => $rental->lane?->max_people,
                'fee' => $rental->lane?->fee,
            ],
            'members' => $rental->members->map(fn (LaneRentalMember $member) => [
                'id' => $member->user?->id,
                'first_name' => $member->user?->first_name,
                'last_name' => $member->user?->last_name,
                'mobile' => $member->user?->mobile,
                'national_code' => $member->user?->national_code,
            ])->values(),
        ];
    }
}
