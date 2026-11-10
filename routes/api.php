<?php

use App\Http\Controllers\Api\AccessControlWebhookController;
use App\Http\Controllers\Api\CourseCatalogController;
use App\Http\Controllers\Api\Admin\AccessCardController as AdminAccessCardController;
use App\Http\Controllers\Api\Admin\AccessControlLogController as AdminAccessControlLogController;
use App\Http\Controllers\Api\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\Admin\CourseEnrollmentController as AdminCourseEnrollmentController;
use App\Http\Controllers\Api\Admin\FederationInquiryController as AdminFederationInquiryController;
use App\Http\Controllers\Api\Admin\CourseSettingController as AdminCourseSettingController;
use App\Http\Controllers\Api\Admin\CourseTaxonomyController as AdminCourseTaxonomyController;
use App\Http\Controllers\Api\Admin\MobileVerificationController as AdminMobileVerificationController;
use App\Http\Controllers\Api\Admin\SiteSettingController as AdminSiteSettingController;
use App\Http\Controllers\Api\Admin\LaneController as AdminLaneController;
use App\Http\Controllers\Api\Admin\LaneRentalController as AdminLaneRentalController;
use App\Http\Controllers\Api\Admin\TimeSlotChargeController as AdminTimeSlotChargeController;
use App\Http\Controllers\Api\Admin\TransitReportController as AdminTransitReportController;
use App\Http\Controllers\Api\Admin\TimeSlotController as AdminTimeSlotController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Rent\LaneMemberController as RentLaneMemberController;
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
    ->middleware(['access-control-webhook', 'throttle:access-control-webhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [SessionController::class, 'me']);
    Route::post('auth/logout', [SessionController::class, 'logout']);

    Route::get('locations', [LocationController::class, 'index']);
    Route::get('locations/cities/{city}/districts', [LocationController::class, 'districts'])->whereNumber('city');
    Route::get('courses/catalog', [CourseCatalogController::class, 'index']);
    Route::get('registration/kin-lookup', [RegistrationController::class, 'lookupKin']);
    Route::get('registration', [RegistrationController::class, 'show']);
    Route::put('registration/steps/{step}', [RegistrationController::class, 'update'])->whereNumber('step');

    Route::middleware('permission:panel.access')->prefix('admin')->group(function () {
        Route::get('stats', [AdminUserController::class, 'stats']);
        Route::get('stats/transits', [AdminUserController::class, 'transitStats']);
        Route::get('reports', [AdminUserController::class, 'report']);
        Route::get('reports/transits', [AdminTransitReportController::class, 'index']);
        Route::get('reports/lane-rentals', [AdminLaneRentalController::class, 'index']);
        Route::get('reports/lane-rentals/{laneRental}', [AdminLaneRentalController::class, 'show'])->whereNumber('laneRental');
        Route::get('time-slot-charges', [AdminTimeSlotChargeController::class, 'index']);
        Route::get('time-slots', [AdminTimeSlotController::class, 'index']);
        Route::post('users/{user}/time-slot-charges', [AdminTimeSlotChargeController::class, 'store'])->whereNumber('user');
        Route::get('access-control-logs', [AdminAccessControlLogController::class, 'index']);
        Route::get('users', [AdminUserController::class, 'index']);
        Route::post('users', [AdminUserController::class, 'store']);
        Route::post('federation/membership-status', AdminFederationInquiryController::class);
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
        Route::post('users/{user}/access-card', [AdminAccessCardController::class, 'enroll'])->whereNumber('user');
        Route::get('users/{user}/enrollments', [AdminCourseEnrollmentController::class, 'index'])->whereNumber('user');
        Route::post('users/{user}/enrollments', [AdminCourseEnrollmentController::class, 'store'])->whereNumber('user');
        Route::get('site-settings', [AdminSiteSettingController::class, 'show']);
        Route::put('site-settings', [AdminSiteSettingController::class, 'update']);

        Route::middleware('permission:lanes.manage')->group(function () {
            Route::get('lane-types', [AdminLaneController::class, 'types']);
            Route::get('lanes', [AdminLaneController::class, 'index']);
            Route::post('lanes', [AdminLaneController::class, 'store']);
            Route::post('lanes/bulk-delete', [AdminLaneController::class, 'destroyMany']);
            Route::patch('lanes/{lane}', [AdminLaneController::class, 'update'])->whereNumber('lane');
            Route::delete('lanes/{lane}', [AdminLaneController::class, 'destroy'])->whereNumber('lane');
            Route::get('lane-rentals', [AdminLaneRentalController::class, 'index']);
            Route::get('lane-renters/lookup', [AdminLaneRentalController::class, 'lookup']);
            Route::post('lane-rentals', [AdminLaneRentalController::class, 'store']);
            Route::get('lane-rentals/{laneRental}', [AdminLaneRentalController::class, 'show'])->whereNumber('laneRental');
            Route::patch('lane-rentals/{laneRental}', [AdminLaneRentalController::class, 'update'])->whereNumber('laneRental');
            Route::delete('lane-rentals/{laneRental}', [AdminLaneRentalController::class, 'destroy'])->whereNumber('laneRental');
        });

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
            Route::post('time-slots', [AdminTimeSlotController::class, 'store']);
            Route::post('time-slots/bulk-delete', [AdminTimeSlotController::class, 'destroyMany']);
            Route::patch('time-slots/{timeSlot}', [AdminTimeSlotController::class, 'update'])->whereNumber('timeSlot');
            Route::delete('time-slots/{timeSlot}', [AdminTimeSlotController::class, 'destroy'])->whereNumber('timeSlot');
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

    Route::middleware('permission:lanes.members')->prefix('rent')->group(function () {
        Route::get('lanes', [RentLaneMemberController::class, 'index']);
        Route::post('lanes/{laneRental}/members', [RentLaneMemberController::class, 'store'])->whereNumber('laneRental');
        Route::delete('lanes/{laneRental}/members/{user}', [RentLaneMemberController::class, 'destroy'])
            ->whereNumber('laneRental')
            ->whereNumber('user');
    });
});
