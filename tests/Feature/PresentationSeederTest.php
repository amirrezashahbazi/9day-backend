<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\GateTransit;
use App\Models\Lane;
use App\Models\TimeSlotCharge;
use App\Models\User;
use App\Support\NationalCode;
use Database\Seeders\PresentationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresentationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_fills_people_payments_and_transits_without_creating_courses(): void
    {
        $courses = Course::query()->count();

        $this->seed(PresentationSeeder::class);
        $this->seed(PresentationSeeder::class);

        $this->assertSame($courses, Course::query()->count());
        $people = User::query()->where('mobile', 'like', '09191%')->get();
        $this->assertCount(54, $people);
        $this->assertTrue(NationalCode::isValid($people->first()->national_code));
        $this->assertGreaterThan(40, TimeSlotCharge::query()->count());
        $this->assertGreaterThan(200, GateTransit::query()->count());
        $this->assertGreaterThan(0, Lane::query()->where('title', 'like', '%ارائه')->count());
    }
}
