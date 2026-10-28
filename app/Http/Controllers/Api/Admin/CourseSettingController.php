<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCourseSettingsRequest;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;

class CourseSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'course_banner_max_kb' => AppSetting::getInt('course_banner_max_kb', 1024),
            'course_banner_max_count' => 4,
        ]);
    }

    public function update(UpdateCourseSettingsRequest $request): JsonResponse
    {
        AppSetting::putValue('course_banner_max_kb', (string) $request->validated('course_banner_max_kb'));

        return response()->json([
            'message' => 'تنظیمات دوره ذخیره شد.',
            'course_banner_max_kb' => AppSetting::getInt('course_banner_max_kb', 1024),
            'course_banner_max_count' => 4,
        ]);
    }
}
