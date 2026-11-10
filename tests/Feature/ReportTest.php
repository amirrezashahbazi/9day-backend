<?php

namespace Tests\Feature;

use App\Models\GateTransit;
use App\Models\Lane;
use App\Models\LaneRental;
use App\Models\LaneRentalMember;
use App\Models\LaneType;
use App\Models\TimeSlotCharge;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_sum_sessions_list_lane_members_and_filter_transits(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $token = $this->postJson('/api/auth/staff/login', [
            'mobile' => SuperAdminSeeder::MOBILE,
            'password' => SuperAdminSeeder::PASSWORD,
        ])->assertOk()->json('token');

        $person = User::factory()->create([
            'first_name' => 'مهسا',
            'last_name' => 'کاظمی',
            'mobile' => '09191000999',
        ]);
        TimeSlotCharge::query()->create([
            'user_id' => $person->id,
            'sessions_count' => 8,
            'amount' => 2_500_000,
            'slot_label' => 'آقایان 8-10 صبح',
            'gender' => 'male',
            'starts_at' => '08:00:00',
            'ends_at' => '10:00:00',
            'period' => 'morning',
        ]);

        $this->withToken($token)->getJson('/api/admin/time-slot-charges')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_amount', 2_500_000)
            ->assertJsonPath('meta.total_sessions', 8)
            ->assertJsonPath('meta.slots.0.slot_label', 'آقایان 8-10 صبح')
            ->assertJsonPath('meta.slots.0.charges', 1);

        $lane = Lane::query()->create([
            'lane_type_id' => LaneType::query()->firstOrFail()->id,
            'title' => 'لاین گزارش',
            'fee' => 3_500_000,
            'min_people' => 2,
            'max_people' => 10,
        ]);
        $rental = LaneRental::query()->create([
            'lane_id' => $lane->id,
            'renter_user_id' => $person->id,
            'students_count' => 8,
            'sessions_total' => 12,
            'sessions_remaining' => 9,
            'access_card_number' => '88011234',
        ]);
        LaneRentalMember::query()->create([
            'lane_rental_id' => $rental->id,
            'user_id' => $person->id,
        ]);

        $this->withToken($token)->getJson('/api/admin/reports/lane-rentals/'.$rental->id)
            ->assertOk()
            ->assertJsonPath('rental.members_count', 1)
            ->assertJsonPath('rental.members.0.user.last_name', 'کاظمی')
            ->assertJsonPath('rental.lane.fee', 3_500_000);

        GateTransit::query()->create([
            'user_id' => $person->id,
            'card_number' => '88011234',
            'allowed' => true,
            'reason' => 'allowed',
            'course_title' => 'شنای آزاد',
            'sessions_remaining_after' => 6,
            'user_name' => 'مهسا کاظمی',
        ]);
        GateTransit::query()->create([
            'card_number' => '88019999',
            'allowed' => false,
            'reason' => 'unknown_card',
        ]);

        $this->withToken($token)->getJson('/api/admin/reports/transits')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.allowed', 1)
            ->assertJsonPath('meta.denied', 1);

        $this->withToken($token)->getJson('/api/admin/reports/transits?status=allowed&q=مهسا')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user.last_name', 'کاظمی')
            ->assertJsonPath('data.0.course_title', 'شنای آزاد')
            ->assertJsonPath('data.0.sessions_remaining', 6);
    }
}
