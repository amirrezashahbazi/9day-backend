<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCourseEnrollmentRequest;
use App\Http\Resources\CourseEnrollmentResource;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Services\CourseEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseEnrollmentController extends Controller
{
    public function index(Request $request, User $user): JsonResponse
    {
        $this->authorizeActor($request->user(), $user);

        $enrollments = CourseEnrollment::query()
            ->with(['course.type', 'course.field', 'course.level', 'enrolledBy'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        return response()->json([
            'data' => CourseEnrollmentResource::collection($enrollments),
            'active' => $enrollments->first(fn (CourseEnrollment $row) => $row->isActive())
                ? new CourseEnrollmentResource($enrollments->first(fn (CourseEnrollment $row) => $row->isActive()))
                : null,
            'user' => [
                'id' => $user->id,
                'access_card_number' => $user->access_card_number,
                'access_card_enrolled_at' => $user->access_card_enrolled_at?->toIso8601String(),
            ],
        ]);
    }

    public function store(
        StoreCourseEnrollmentRequest $request,
        User $user,
        CourseEnrollmentService $enrollments,
    ): JsonResponse {
        $this->authorizeActor($request->user(), $user);

        $course = Course::query()->findOrFail($request->integer('course_id'));
        $sessions = $request->filled('sessions_total')
            ? $request->integer('sessions_total')
            : null;

        $renewFrom = null;
        if ($request->filled('renew_from_id')) {
            $renewFrom = CourseEnrollment::query()
                ->where('user_id', $user->id)
                ->whereKey($request->integer('renew_from_id'))
                ->firstOrFail();
        }

        $enrollment = $enrollments->enroll(
            $user,
            $course,
            $sessions,
            $request->user(),
            $renewFrom,
        );

        $message = $renewFrom
            ? 'تمدید دوره با رکورد جدید ثبت شد.'
            : 'کاربر در دوره ثبت شد.';

        return response()->json([
            'message' => $message,
            'enrollment' => new CourseEnrollmentResource($enrollment),
        ], 201);
    }

    private function authorizeActor(?User $actor, User $target): void
    {
        if (! $actor) {
            abort(403);
        }
        if (! $actor->hasRole(UserRole::SuperAdmin) && ! $actor->hasRole(UserRole::Admin)) {
            abort(403, 'دسترسی ندارید.');
        }
        if (! $actor->hasRole(UserRole::SuperAdmin) && $target->hasRole(UserRole::SuperAdmin)) {
            abort(403, 'به این حساب دسترسی ندارید.');
        }
        if ($actor->hasRole(UserRole::Admin) && ! $actor->hasRole(UserRole::SuperAdmin) && ! $target->isMember()) {
            abort(403, 'ادمین فقط می‌تواند کاربران عادی را مدیریت کند.');
        }
    }
}
