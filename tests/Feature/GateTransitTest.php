<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseField;
use App\Models\CourseLevel;
use App\Models\CourseType;
use App\Models\GateTransit;
use App\Models\Lane;
use App\Models\LaneRental;
use App\Models\LaneType;
use App\Models\User;
use App\Services\CourseEnrollmentService;
use App\Services\GateTransitService;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GateTransitTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(int $sessions = 3): Course
    {
        $type = CourseType::query()->create(['name' => 'عمومی']);
        $field = CourseField::query()->create(['name' => 'شنا']);
        $level = CourseLevel::query()->create(['name' => 'مبتدی']);

        return Course::query()->create([
            'course_type_id' => $type->id,
            'course_field_id' => $field->id,
            'course_level_id' => $level->id,
            'title' => 'دوره تست گیت',
            'gender' => 'female',
            'min_age' => 7,
            'max_age' => 60,
            'registration_from' => '2026-01-01',
            'registration_to' => '2026-12-31',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
            'fee' => 1000,
            'capacity' => 200,
            'sessions_count' => $sessions,
            'enrollments_count' => 0,
        ]);
    }

    private function makeAthlete(string $card): User
    {
        $user = User::factory()->create([
            'mobile' => '0912'.random_int(1000000, 9999999),
            'status' => UserStatus::Active,
            'first_name' => 'مریم',
            'last_name' => 'تستی',
            'access_card_number' => $card,
            'access_card_enrolled_at' => now(),
            'access_granted_at' => now(),
            'registration_step' => 7,
            'registration_completed_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $user->syncRoles([UserRole::Athlete]);

        return $user;
    }

    public function test_allowed_swipe_decrements_remaining_and_writes_transit(): void
    {
        $course = $this->makeCourse(3);
        $user = $this->makeAthlete('55550001');
        app(CourseEnrollmentService::class)->enroll($user, $course, null, null);

        $result = app(GateTransitService::class)->process([
            'enrollid' => '55550001',
            'username' => 'device-name',
        ], '127.0.0.1');

        $this->assertTrue($result['allowed']);
        $this->assertSame('allowed', $result['reason']);
        $this->assertSame(2, $result['sessions_remaining']);
        $this->assertSame('دوره تست گیت', $result['course_title']);

        $enrollment = CourseEnrollment::query()->where('user_id', $user->id)->where('status', EnrollmentStatus::Active)->first();
        $this->assertNotNull($enrollment);
        $this->assertSame(2, $enrollment->sessions_remaining);
        $this->assertDatabaseCount('gate_transits', 1);
        $this->assertTrue(GateTransit::query()->first()->allowed);
    }

    public function test_cannot_overconsume_sessions_under_concurrent_pressure(): void
    {
        $course = $this->makeCourse(2);
        $user = $this->makeAthlete('55550002');
        app(CourseEnrollmentService::class)->enroll($user, $course, 2, null);

        $service = app(GateTransitService::class);
        $allowed = 0;
        $denied = 0;

        for ($i = 0; $i < 5; $i++) {
            $result = $service->process(['carddata' => '55550002'], '127.0.0.1');
            if ($result['allowed']) {
                $allowed++;
            } else {
                $denied++;
            }
        }

        $this->assertSame(2, $allowed);
        $this->assertSame(3, $denied);
        $this->assertSame(0, (int) CourseEnrollment::query()->where('user_id', $user->id)->value('sessions_remaining'));
        $this->assertSame(
            EnrollmentStatus::Exhausted,
            CourseEnrollment::query()->where('user_id', $user->id)->first()->status,
        );
        $this->assertSame(2, GateTransit::query()->where('allowed', true)->count());
        $this->assertSame(3, GateTransit::query()->where('allowed', false)->count());
    }

    public function test_card_with_leading_zeros_still_matches_and_decrements(): void
    {
        $course = $this->makeCourse(4);
        $user = $this->makeAthlete('005957224');
        app(CourseEnrollmentService::class)->enroll($user, $course, 4, null);

        $result = app(GateTransitService::class)->process([
            'enrollid' => '5957224',
            'username' => 'device',
        ], '127.0.0.1');

        $this->assertTrue($result['allowed']);
        $this->assertSame($user->id, $result['user_id']);
        $this->assertSame(3, $result['sessions_remaining']);
        $this->assertSame('دوره تست گیت', $result['course_title']);
    }

    public function test_unknown_card_is_denied_and_logged(): void
    {
        $result = app(GateTransitService::class)->process(['CardNo' => '99998888'], '10.0.0.1');

        $this->assertFalse($result['allowed']);
        $this->assertSame('unknown_card', $result['reason']);
        $this->assertDatabaseCount('gate_transits', 1);
        $this->assertFalse(GateTransit::query()->first()->allowed);
    }

    public function test_webhook_endpoint_processes_gate_transit(): void
    {
        config(['services.access_control_webhook.key' => 'test-webhook-key']);

        $course = $this->makeCourse(1);
        $user = $this->makeAthlete('55550003');
        app(CourseEnrollmentService::class)->enroll($user, $course, 1, null);

        $this->postJson('/api/webhooks/access-control?key=test-webhook-key', [
            'enrollid' => '55550003',
        ])->assertCreated()
            ->assertJsonPath('allowed', true)
            ->assertJsonPath('sessions_remaining', 0);

        $this->assertSame(
            EnrollmentStatus::Exhausted,
            CourseEnrollment::query()->where('user_id', $user->id)->first()->status,
        );
    }

    public function test_renew_creates_new_row_and_supersedes_previous(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $token = $this->postJson('/api/auth/staff/login', [
            'mobile' => SuperAdminSeeder::MOBILE,
            'password' => SuperAdminSeeder::PASSWORD,
        ])->assertOk()->json('token');

        $course = $this->makeCourse(10);
        $user = $this->makeAthlete('55550004');
        $first = app(CourseEnrollmentService::class)->enroll($user, $course, 4, null);

        $this->withToken($token)->postJson("/api/admin/users/{$user->id}/enrollments", [
            'course_id' => $course->id,
            'sessions_total' => 8,
            'renew_from_id' => $first->id,
        ])->assertCreated()->assertJsonPath('enrollment.sessions_remaining', 8);

        $this->assertSame(EnrollmentStatus::Superseded->value, $first->fresh()->status->value);
        $this->assertSame(2, CourseEnrollment::query()->where('user_id', $user->id)->count());
        $this->assertSame(
            1,
            CourseEnrollment::query()->where('user_id', $user->id)->where('status', EnrollmentStatus::Active)->count(),
        );
    }

    public function test_lane_rental_card_is_allowed_even_when_leading_zeros_match_another_user(): void
    {
        $renter = User::factory()->create([
            'mobile' => '09120001111',
            'first_name' => 'امیررضا',
            'last_name' => 'شهبازی کارت',
            'status' => UserStatus::Active,
            'access_card_number' => '005957224',
        ]);
        User::factory()->create([
            'mobile' => '09120002222',
            'first_name' => 'ujgfccy',
            'last_name' => 'khgyuig',
            'status' => UserStatus::Active,
            'access_card_number' => '0005966947',
        ]);
        $type = LaneType::query()->firstOrCreate(['name' => 'طولی']);
        $lane = Lane::query()->create([
            'lane_type_id' => $type->id,
            'title' => 'لاین طولی استخر',
            'fee' => 4500000,
            'min_people' => 1,
            'max_people' => 10,
        ]);
        $rental = LaneRental::query()->create([
            'lane_id' => $lane->id,
            'renter_user_id' => $renter->id,
            'students_count' => 1,
            'sessions_total' => 5,
            'sessions_remaining' => 5,
            'access_card_number' => '005966947',
        ]);

        $result = app(GateTransitService::class)->process(['enrollid' => '5966947'], '127.0.0.1');

        $this->assertTrue($result['allowed']);
        $this->assertSame('allowed', $result['reason']);
        $this->assertSame(4, $result['sessions_remaining']);
        $this->assertSame('لاین طولی استخر', $result['course_title']);
        $this->assertSame($renter->id, $result['user_id']);
        $this->assertSame('امیررضا شهبازی کارت', $result['user_name']);
        $this->assertSame(4, $rental->fresh()->sessions_remaining);
    }
}
