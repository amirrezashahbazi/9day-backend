<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CourseEnrollmentService
{
    /**
     * Create a new enrollment (register or renew). Always inserts a new row.
     * Closes any previous active enrollment for the user inside the same transaction.
     */
    public function enroll(
        User $user,
        Course $course,
        ?int $sessionsOverride,
        ?User $actor,
        ?CourseEnrollment $renewFrom = null,
    ): CourseEnrollment {
        $sessions = $sessionsOverride ?? (int) $course->sessions_count;
        if ($sessions < 1 || $sessions > 500) {
            throw new InvalidArgumentException('تعداد جلسات معتبر نیست.');
        }

        return DB::transaction(function () use ($user, $course, $sessions, $actor, $renewFrom) {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $active = CourseEnrollment::query()
                ->where('user_id', $lockedUser->id)
                ->where('status', EnrollmentStatus::Active->value)
                ->lockForUpdate()
                ->get();

            foreach ($active as $existing) {
                $existing->forceFill([
                    'status' => EnrollmentStatus::Superseded,
                    'closed_at' => now(),
                ])->save();
            }

            if ($renewFrom && $renewFrom->user_id !== $lockedUser->id) {
                throw new InvalidArgumentException('تمدید فقط برای همان کاربر مجاز است.');
            }

            $enrollment = CourseEnrollment::query()->create([
                'user_id' => $lockedUser->id,
                'course_id' => $course->id,
                'sessions_total' => $sessions,
                'sessions_remaining' => $sessions,
                'status' => EnrollmentStatus::Active,
                'renewed_from_id' => $renewFrom?->id,
                'enrolled_by' => $actor?->id,
                'access_card_number' => $lockedUser->access_card_number,
                'activated_at' => now(),
            ]);

            Course::query()->whereKey($course->id)->increment('enrollments_count');

            return $enrollment->load(['course.type', 'course.field', 'course.level', 'user']);
        });
    }
}
