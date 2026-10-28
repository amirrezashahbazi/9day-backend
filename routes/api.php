<?php

use App\Http\Controllers\Api\AccessControlWebhookController;
use App\Http\Controllers\Api\Admin\AccessControlLogController as AdminAccessControlLogController;
use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\Admin\CourseSettingController as AdminCourseSettingController;
use App\Http\Controllers\Api\Admin\CourseTaxonomyController as AdminCourseTaxonomyController;
use App\Http\Controllers\Api\Admin\MobileVerificationController as AdminMobileVerificationController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Auth\OtpController;
use App\Http\Controllers\Api\Auth\SessionController;
use App\Http\Controllers\Api\Auth\StaffAuthController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('otp/request', [OtpController::class, 'send'])->middleware('throttle:otp');
    Route::post('otp/verify', [OtpController::class, 'confirm'])->middleware('throttle:otp-verify');
    Route::post('staff/login', [StaffAuthController::class, 'login'])->middleware('throttle:staff-login');
});

Route::post('webhooks/access-control', AccessControlWebhookController::class)
    ->middleware('throttle:access-control-webhook');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [SessionController::class, 'me']);
    Route::post('auth/logout', [SessionController::class, 'logout']);

    Route::get('locations', [LocationController::class, 'index']);
    Route::get('locations/cities/{city}/districts', [LocationController::class, 'districts'])->whereNumber('city');
    Route::get('registration', [RegistrationController::class, 'show']);
    Route::put('registration/steps/{step}', [RegistrationController::class, 'update'])->whereNumber('step');

    Route::middleware('permission:panel.access')->prefix('admin')->group(function () {
        Route::get('stats', [AdminUserController::class, 'stats']);
        Route::get('reports', [AdminUserController::class, 'report']);
        Route::get('access-control-logs', [AdminAccessControlLogController::class, 'index']);
        Route::get('users', [AdminUserController::class, 'index']);
        Route::post('users', [AdminUserController::class, 'store']);
        Route::post('users/mobile/otp', [AdminMobileVerificationController::class, 'send'])->middleware('throttle:otp');
        Route::post('users/mobile/verify', [AdminMobileVerificationController::class, 'confirm'])->middleware('throttle:otp-verify');
        Route::post('users/assign-roles', [AdminUserController::class, 'assignRoles']);
        Route::post('users/bulk-delete', [AdminUserController::class, 'destroyMany']);
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->whereNumber('user');
        Route::get('users/{user}', [AdminUserController::class, 'show']);
        Route::put('users/{user}/registration/steps/{step}', [AdminUserController::class, 'updateRegistration'])
            ->whereNumber('user')
            ->whereNumber('step');
        Route::patch('users/{user}/access', [AdminUserController::class, 'updateAccess']);

        Route::middleware('permission:courses.view')->group(function () {
            Route::get('courses', [AdminCourseController::class, 'index']);
            Route::get('courses/{course}', [AdminCourseController::class, 'show'])->whereNumber('course');
            Route::get('course-settings', [AdminCourseSettingController::class, 'show']);
            Route::get('{taxonomyTable}', [AdminCourseTaxonomyController::class, 'index'])
                ->whereIn('taxonomyTable', ['course_types', 'course_fields', 'course_levels']);
        });

        Route::middleware('permission:courses.manage')->group(function () {
            Route::post('courses', [AdminCourseController::class, 'store']);
            Route::post('courses/bulk-delete', [AdminCourseController::class, 'destroyMany']);
            Route::post('courses/{course}', [AdminCourseController::class, 'update'])->whereNumber('course');
            Route::delete('courses/{course}', [AdminCourseController::class, 'destroy'])->whereNumber('course');
            Route::put('course-settings', [AdminCourseSettingController::class, 'update']);
            Route::post('{taxonomyTable}', [AdminCourseTaxonomyController::class, 'store'])
                ->whereIn('taxonomyTable', ['course_types', 'course_fields', 'course_levels']);
            Route::post('{taxonomyTable}/bulk-delete', [AdminCourseTaxonomyController::class, 'destroyMany'])
                ->whereIn('taxonomyTable', ['course_types', 'course_fields', 'course_levels']);
            Route::patch('{taxonomyTable}/{id}', [AdminCourseTaxonomyController::class, 'update'])
                ->whereIn('taxonomyTable', ['course_types', 'course_fields', 'course_levels'])
                ->whereNumber('id');
            Route::delete('{taxonomyTable}/{id}', [AdminCourseTaxonomyController::class, 'destroy'])
                ->whereIn('taxonomyTable', ['course_types', 'course_fields', 'course_levels'])
                ->whereNumber('id');
        });
    });
});
