<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\GateTransitReason;
use App\Enums\UserStatus;
use App\Models\AccessControlLog;
use App\Models\CourseEnrollment;
use App\Models\GateTransit;
use App\Models\LaneRental;
use App\Models\User;
use App\Support\AccessControlPayload;
use Illuminate\Support\Facades\DB;

class GateTransitService
{
    /**
     * Process one access-control swipe with atomic remaining-session update.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *   log: AccessControlLog,
     *   transit: GateTransit,
     *   allowed: bool,
     *   reason: string,
     *   user_name: ?string,
     *   card_number: ?string,
     *   course_title: ?string,
     *   sessions_remaining: ?int,
     *   user_id: ?int,
     *   enrollment_id: ?int,
     *   course_id: ?int
     * }
     */
    public function process(array $payload, ?string $sourceIp = null): array
    {
        $resolved = AccessControlPayload::resolve($payload);
        $cardNumber = $resolved['card_number'] ?? null;
        $userId = $resolved['user_id'] ?? null;
        $userName = $resolved['user_name'] ?? null;

        return DB::transaction(function () use ($payload, $sourceIp, $cardNumber, $userId, $userName, $resolved) {
            $user = null;
            if ($userId) {
                $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            }
            if (! $user && is_string($cardNumber) && $cardNumber !== '') {
                $user = User::query()
                    ->where('access_card_number', $cardNumber)
                    ->lockForUpdate()
                    ->first();
            }
            if (! $user && is_string($cardNumber) && $cardNumber !== '') {
                $canonical = AccessControlPayload::canonicalCard($cardNumber);
                if ($canonical !== '0') {
                    $user = User::query()
                        ->whereRaw("ltrim(access_card_number, '0') = ?", [$canonical])
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->first();
                }
            }

            if ($user) {
                $name = trim(collect([$user->first_name, $user->last_name])->filter()->implode(' '));
                $userName = $name !== '' ? $name : $user->mobile;
                $cardNumber = $cardNumber ?: $user->access_card_number;
            }

            $allowed = false;
            $reason = GateTransitReason::UnknownCard;
            $enrollment = null;
            $sessionsRemainingAfter = null;
            $courseTitle = null;

            if ($user && $user->status === UserStatus::Blocked) {
                $reason = GateTransitReason::UserBlocked;
            } else {
                if ($user) {
                    $enrollment = CourseEnrollment::query()
                        ->with('course')
                        ->where('user_id', $user->id)
                        ->where('status', EnrollmentStatus::Active->value)
                        ->lockForUpdate()
                        ->orderByDesc('id')
                        ->first();
                }

                if (! $enrollment) {
                    $rental = $this->consumeLaneRental($cardNumber);
                    if ($rental === null) {
                        $reason = $user ? GateTransitReason::NoEnrollment : GateTransitReason::UnknownCard;
                    } else {
                        $allowed = $rental['allowed'];
                        $reason = $rental['reason'];
                        $sessionsRemainingAfter = $rental['sessions_remaining'];
                        $courseTitle = $rental['title'];
                        if ($rental['user']) {
                            $user = $rental['user'];
                            $userName = $rental['user_name'] ?: $userName;
                            $cardNumber = $cardNumber ?: $user->access_card_number;
                        }
                    }
                } elseif ($enrollment->sessions_remaining < 1) {
                    $enrollment->forceFill([
                        'status' => EnrollmentStatus::Exhausted,
                        'closed_at' => now(),
                    ])->save();
                    $reason = GateTransitReason::Exhausted;
                    $courseTitle = $enrollment->course?->title;
                    $sessionsRemainingAfter = 0;
                } else {
                    // Atomic conditional decrement — refuse if another request already consumed last session.
                    $affected = CourseEnrollment::query()
                        ->whereKey($enrollment->id)
                        ->where('status', EnrollmentStatus::Active->value)
                        ->where('sessions_remaining', '>', 0)
                        ->update([
                            'sessions_remaining' => DB::raw('sessions_remaining - 1'),
                            'updated_at' => now(),
                        ]);

                    if ($affected !== 1) {
                        $enrollment->refresh();
                        $reason = GateTransitReason::Exhausted;
                        $courseTitle = $enrollment->relationLoaded('course')
                            ? $enrollment->course?->title
                            : CourseEnrollment::query()->with('course')->find($enrollment->id)?->course?->title;
                        $sessionsRemainingAfter = (int) $enrollment->sessions_remaining;
                    } else {
                        $enrollment->refresh();
                        if ($enrollment->sessions_remaining === 0) {
                            $enrollment->forceFill([
                                'status' => EnrollmentStatus::Exhausted,
                                'closed_at' => now(),
                            ])->save();
                        }

                        if (! $enrollment->relationLoaded('course')) {
                            $enrollment->load('course');
                        }

                        $allowed = true;
                        $reason = GateTransitReason::Allowed;
                        $sessionsRemainingAfter = (int) $enrollment->sessions_remaining;
                        $courseTitle = $enrollment->course?->title;
                    }
                }

                if ($enrollment && ! $enrollment->relationLoaded('course')) {
                    $enrollment->load('course');
                    $courseTitle = $courseTitle ?: $enrollment->course?->title;
                }
            }

            $payload['resolved'] = array_merge($resolved, [
                'user_id' => $user?->id,
                'user_name' => $userName,
                'card_number' => $cardNumber,
                'allowed' => $allowed,
                'reason' => $reason->value,
                'course_title' => $courseTitle,
                'sessions_remaining' => $sessionsRemainingAfter,
                'enrollment_id' => $enrollment?->id,
                'course_id' => $enrollment?->course_id,
            ]);

            $log = AccessControlLog::query()->create([
                'payload' => $payload,
                'source_ip' => $sourceIp,
            ]);

            $transit = GateTransit::query()->create([
                'user_id' => $user?->id,
                'enrollment_id' => $enrollment?->id,
                'course_id' => $enrollment?->course_id,
                'access_control_log_id' => $log->id,
                'card_number' => $cardNumber,
                'allowed' => $allowed,
                'reason' => $reason,
                'sessions_remaining_after' => $sessionsRemainingAfter,
                'course_title' => $courseTitle,
                'user_name' => $userName,
                'source_ip' => $sourceIp,
            ]);

            return [
                'log' => $log,
                'transit' => $transit,
                'allowed' => $allowed,
                'reason' => $reason->value,
                'user_name' => $userName,
                'card_number' => $cardNumber,
                'course_title' => $courseTitle,
                'sessions_remaining' => $sessionsRemainingAfter,
                'user_id' => $user?->id,
                'enrollment_id' => $enrollment?->id,
                'course_id' => $enrollment?->course_id,
            ];
        });
    }

    /**
     * A lane rental is charged on its own card. Leading zeros do not make it a different card.
     *
     * @return array{allowed: bool, reason: GateTransitReason, sessions_remaining: int, title: ?string, user: ?User, user_name: ?string}|null
     */
    private function consumeLaneRental(?string $cardNumber): ?array
    {
        if (! is_string($cardNumber) || $cardNumber === '') {
            return null;
        }

        $canonical = AccessControlPayload::canonicalCard($cardNumber);
        if ($canonical === '0') {
            return null;
        }

        $rental = LaneRental::query()
            ->with(['lane', 'renter'])
            ->whereRaw("ltrim(access_card_number, '0') = ?", [$canonical])
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        if (! $rental) {
            return null;
        }

        $renter = $rental->renter;
        $name = trim(collect([$renter?->first_name, $renter?->last_name])->filter()->implode(' '));
        $title = $rental->lane?->title;

        if ($rental->sessions_remaining < 1) {
            return [
                'allowed' => false,
                'reason' => GateTransitReason::Exhausted,
                'sessions_remaining' => 0,
                'title' => $title,
                'user' => $renter,
                'user_name' => $name !== '' ? $name : $renter?->mobile,
            ];
        }

        $affected = LaneRental::query()
            ->whereKey($rental->id)
            ->where('sessions_remaining', '>', 0)
            ->update([
                'sessions_remaining' => DB::raw('sessions_remaining - 1'),
                'updated_at' => now(),
            ]);
        $rental->refresh();

        return [
            'allowed' => $affected === 1,
            'reason' => $affected === 1 ? GateTransitReason::Allowed : GateTransitReason::Exhausted,
            'sessions_remaining' => (int) $rental->sessions_remaining,
            'title' => $title,
            'user' => $renter,
            'user_name' => $name !== '' ? $name : $renter?->mobile,
        ];
    }
}
