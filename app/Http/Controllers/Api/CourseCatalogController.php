<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;

class CourseCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = Course::query()
            ->with(['type', 'field', 'level'])
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'title', 'course_type_id', 'course_field_id', 'course_level_id', 'sessions_count', 'slot_starts_at', 'slot_ends_at', 'fee', 'capacity', 'enrollments_count', 'gender', 'starts_on', 'ends_on']);

        return response()->json([
            'data' => $courses->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'sessions_count' => $course->sessions_count,
                'slot_starts_at' => $course->slot_starts_at ? substr((string) $course->slot_starts_at, 0, 5) : null,
                'slot_ends_at' => $course->slot_ends_at ? substr((string) $course->slot_ends_at, 0, 5) : null,
                'fee' => $course->fee,
                'capacity' => $course->capacity,
                'enrollments_count' => $course->enrollments_count,
                'gender' => $course->gender?->value,
                'type' => $course->type ? ['id' => $course->type->id, 'name' => $course->type->name] : null,
                'field' => $course->field ? ['id' => $course->field->id, 'name' => $course->field->name] : null,
                'level' => $course->level ? ['id' => $course->level->id, 'name' => $course->level->name] : null,
            ]),
        ]);
    }
}
