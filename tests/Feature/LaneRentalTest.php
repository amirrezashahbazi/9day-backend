<?php

namespace Tests\Feature;

use App\Models\Lane;
use App\Models\LaneRental;
use App\Models\LaneRentalMember;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LaneRentalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_defines_a_lane_and_assigns_a_renter_without_calling_the_device(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $token = $this->staffToken();

        $lane = $this->withToken($token)->postJson('/api/admin/lanes', [
            'title' => 'لاین طولی استخر ۵۰ متر',
            'type_name' => 'طولی',
            'fee' => 4500000,
            'min_people' => 1,
            'max_people' => 2,
        ])->assertCreated()->json('lane');

        $this->assertSame('طولی', $lane['type']['name']);

        $rental = $this->withToken($token)->postJson('/api/admin/lane-rentals', [
            'mobile' => '09123330000',
            'first_name' => 'رضا',
            'last_name' => 'اجاره‌کننده',
            'password' => '',
            'lane_id' => $lane['id'],
            'students_count' => 2,
            'sessions_count' => 8,
            'access_card_number' => 'LANE-100',
        ])->assertCreated()->json('rental');

        $renter = User::query()->findOrFail($rental['renter']['id']);
        $this->assertTrue($renter->hasRole('lane_renter'));
        $this->assertTrue(Hash::check('123456Aa@', $renter->password));
        $this->assertSame('LANE-100', $renter->access_card_number);
        $this->assertSame(8, $rental['sessions_remaining']);
    }

    public function test_renter_can_assign_existing_users_only_up_to_lane_capacity(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $token = $this->staffToken();
        $laneId = $this->withToken($token)->postJson('/api/admin/lanes', [
            'title' => 'لاین عرضی',
            'type_name' => 'عرضی',
            'fee' => 3500000,
            'min_people' => 1,
            'max_people' => 2,
        ])->assertCreated()->json('lane.id');

        $rentalId = $this->withToken($token)->postJson('/api/admin/lane-rentals', [
            'mobile' => '09123330001',
            'first_name' => 'مریم',
            'last_name' => 'اجاره',
            'lane_id' => $laneId,
            'students_count' => 2,
            'sessions_count' => 4,
            'access_card_number' => 'LANE-200',
        ])->assertCreated()->json('rental.id');

        $existing = User::factory()->create(['mobile' => '09123330002', 'first_name' => 'موجود', 'last_name' => 'قبلی']);
        $renter = User::query()->where('mobile', '09123330001')->firstOrFail();
        $renterToken = $renter->createToken('test')->plainTextToken;
        Auth::forgetGuards();

        $this->withToken($renterToken)->postJson("/api/rent/lanes/{$rentalId}/members", [
            'mobile' => $existing->mobile,
        ])->assertCreated()->assertJsonPath('rental.members.0.id', $existing->id);

        $this->withToken($renterToken)->postJson("/api/rent/lanes/{$rentalId}/members", [
            'mobile' => '09123330003',
        ])->assertStatus(422);

        $this->withToken($renterToken)->postJson("/api/rent/lanes/{$rentalId}/members", [
            'mobile' => '09123330003',
            'first_name' => 'شاگرد',
            'last_name' => 'جدید',
        ])->assertCreated();

        $this->withToken($renterToken)->postJson("/api/rent/lanes/{$rentalId}/members", [
            'mobile' => '09123330004',
            'first_name' => 'اضافه',
            'last_name' => 'ظرفیت',
        ])->assertStatus(422);

        $this->assertSame(2, LaneRentalMember::query()->where('lane_rental_id', $rentalId)->count());
        $this->assertSame(1, Lane::query()->count());
    }

    public function test_existing_user_keeps_their_password_when_a_lane_is_assigned(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $token = $this->staffToken();
        $existing = User::factory()->create([
            'mobile' => '09123330009',
            'first_name' => 'کاربر',
            'last_name' => 'موجود',
            'password' => 'KeepMe-99',
        ]);
        $laneId = $this->withToken($token)->postJson('/api/admin/lanes', [
            'title' => 'لاین موجود',
            'type_name' => 'طولی',
            'fee' => 1000,
            'min_people' => 1,
            'max_people' => 4,
        ])->assertCreated()->json('lane.id');

        $this->withToken($token)->postJson('/api/admin/lane-rentals', [
            'mobile' => $existing->mobile,
            'password' => '123456Aa@',
            'lane_id' => $laneId,
            'students_count' => 1,
            'sessions_count' => 3,
            'access_card_number' => '7001001',
        ])->assertCreated();

        $existing->refresh();
        $this->assertTrue(Hash::check('KeepMe-99', $existing->password));
        $this->assertSame('7001001', $existing->access_card_number);
    }

    public function test_staff_can_search_edit_and_list_registered_members(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $token = $this->staffToken();
        $laneId = $this->withToken($token)->postJson('/api/admin/lanes', [
            'title' => 'لاین جستجو',
            'type_name' => 'طولی',
            'fee' => 1000,
            'min_people' => 1,
            'max_people' => 4,
        ])->assertCreated()->json('lane.id');

        $rentalId = $this->withToken($token)->postJson('/api/admin/lane-rentals', [
            'mobile' => '09124440001',
            'first_name' => 'مریم',
            'last_name' => 'اجاره',
            'lane_id' => $laneId,
            'students_count' => 2,
            'sessions_count' => 4,
            'access_card_number' => 'LANE-310',
        ])->assertCreated()->json('rental.id');

        $member = User::factory()->create(['mobile' => '09124440002', 'first_name' => 'شاگرد', 'last_name' => 'ثبت']);
        $renter = User::query()->where('mobile', '09124440001')->firstOrFail();
        $renterToken = $renter->createToken('test')->plainTextToken;
        Auth::forgetGuards();
        $this->withToken($renterToken)->postJson("/api/rent/lanes/{$rentalId}/members", [
            'mobile' => $member->mobile,
        ])->assertCreated();
        $this->withToken($renterToken)->postJson("/api/rent/lanes/{$rentalId}/members", [
            'mobile' => '09124440003',
            'first_name' => 'دوم',
            'last_name' => 'شاگرد',
        ])->assertCreated();
        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/admin/lane-rentals?q=مریم')
            ->assertOk()
            ->assertJsonPath('data.0.students_count', 2)
            ->assertJsonPath('data.0.members_count', 2);

        $this->withToken($token)->getJson("/api/admin/lane-rentals/{$rentalId}")
            ->assertOk()
            ->assertJsonPath('rental.members.0.user.first_name', 'شاگرد')
            ->assertJsonPath('rental.members.0.registered_by.id', $renter->id);

        $this->withToken($token)->patchJson("/api/admin/lane-rentals/{$rentalId}", [
            'students_count' => 1,
            'sessions_remaining' => 4,
            'access_card_number' => 'LANE-310',
        ])->assertStatus(422);

        $this->withToken($token)->patchJson("/api/admin/lane-rentals/{$rentalId}", [
            'students_count' => 3,
            'sessions_remaining' => 2,
            'access_card_number' => 'LANE-311',
        ])->assertOk()->assertJsonPath('rental.sessions_remaining', 2);

        $this->withToken($token)->deleteJson("/api/admin/lane-rentals/{$rentalId}")->assertOk();
        $this->assertSame(0, LaneRental::query()->count());
    }

    private function staffToken(): string
    {
        return $this->postJson('/api/auth/staff/login', [
            'mobile' => SuperAdminSeeder::MOBILE,
            'password' => SuperAdminSeeder::PASSWORD,
        ])->assertOk()->json('token');
    }
}
