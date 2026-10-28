<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Support\NationalCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use AuthenticatesUsers, RefreshDatabase;

    public function test_steps_are_saved_in_order_and_can_be_resumed(): void
    {
        $mobile = '09125555555';
        $token = $this->loginUser($mobile);

        $this->withToken($token)->putJson('/api/registration/steps/2', $this->step(2))
            ->assertStatus(422)
            ->assertJsonPath('message', 'ابتدا مرحله قبلی را تکمیل کنید.');

        $this->withToken($token)->putJson('/api/registration/steps/1', [
            'first_name' => 'سارا',
            'last_name' => 'محمدی',
            'father_name' => 'حسین',
            'issued_from' => 'تهران',
            'national_code' => '1234567890',
            'birth_date' => '1998-04-12',
            'gender' => 'female',
        ])->assertStatus(422);

        $this->withToken($token)->putJson('/api/registration/steps/1', $this->step(1))
            ->assertOk()
            ->assertJsonPath('user.registration_step', 1)
            ->assertJsonPath('user.birth_date', '1998-04-12');

        foreach ([2, 3, 4, 5, 6, 7] as $step) {
            $this->withToken($token)->putJson('/api/registration/steps/'.$step, $this->step($step))
                ->assertOk()
                ->assertJsonPath('user.registration_step', $step);
        }

        $this->withToken($token)->getJson('/api/registration')
            ->assertOk()
            ->assertJsonPath('user.status', 'pending')
            ->assertJsonPath('user.first_name', 'سارا');

        $this->travel(61)->seconds();
        $again = $this->loginUser($mobile);
        $this->withToken($again)->getJson('/api/registration')
            ->assertOk()
            ->assertJsonPath('user.registration_step', 7)
            ->assertJsonPath('user.national_code', NationalCode::sample());
        $this->travelBack();

        $edited = $this->step(1);
        $edited['first_name'] = 'نرگس';
        $this->withToken($again)->putJson('/api/registration/steps/1', $edited)
            ->assertOk()
            ->assertJsonPath('user.first_name', 'نرگس')
            ->assertJsonPath('user.status', 'pending');

        User::query()->where('mobile', $mobile)->update(['status' => UserStatus::Active->value]);
        $this->flushHeaders();
        Auth::forgetGuards();

        $this->withToken($again)->putJson('/api/registration/steps/1', $edited)
            ->assertStatus(403);
    }

    public function test_each_illness_needs_a_note_and_a_single_digit_duration_is_enough(): void
    {
        $token = $this->loginUser('09126666666');

        foreach ([1, 2, 3, 4] as $step) {
            $this->withToken($token)->putJson('/api/registration/steps/'.$step, $this->step($step))->assertOk();
        }

        $this->withToken($token)->putJson('/api/registration/steps/5', [
            'has_illness' => true,
            'illness_skin' => true,
            'illness_skin_description' => 'حساسیت پوستی',
            'illness_respiratory' => true,
            'illness_respiratory_description' => '',
            'illness_cardiac' => false,
            'illness_other' => false,
            'takes_medication' => false,
        ])->assertStatus(422);

        $this->withToken($token)->putJson('/api/registration/steps/5', [
            'has_illness' => true,
            'illness_skin' => true,
            'illness_skin_description' => 'حساسیت پوستی',
            'illness_respiratory' => true,
            'illness_respiratory_description' => 'آسم خفیف',
            'illness_cardiac' => false,
            'illness_other' => false,
            'takes_medication' => false,
        ])->assertOk()
            ->assertJsonPath('user.illness_skin_description', 'حساسیت پوستی')
            ->assertJsonPath('user.illness_respiratory_description', 'آسم خفیف');

        $this->withToken($token)->putJson('/api/registration/steps/6', [
            'has_sport_history' => true,
            'previous_coach' => 'رضا کریمی',
            'history_duration' => '2',
            'previous_club' => 'باشگاه دریا',
        ])->assertOk()
            ->assertJsonPath('user.history_duration', '2');
    }

    public function test_guest_cannot_open_registration(): void
    {
        $this->getJson('/api/registration')->assertStatus(401);
    }

    private function step(int $step): array
    {
        return match ($step) {
            1 => [
                'first_name' => 'سارا',
                'last_name' => 'محمدی',
                'father_name' => 'حسین',
                'issued_from' => 'تهران',
                'national_code' => NationalCode::sample(),
                'birth_date' => '1998-04-12',
                'gender' => 'female',
            ],
            2 => $this->contactStep(),
            3 => [
                'course_swimming' => true,
                'course_water_polo' => false,
                'course_diving' => false,
                'course_private' => true,
                'course_semi_private' => false,
            ],
            4 => [
                'referral_source' => 'other',
                'referral_other' => 'بنر مجموعه',
            ],
            5 => [
                'has_illness' => false,
                'illness_skin' => false,
                'illness_respiratory' => false,
                'illness_cardiac' => false,
                'illness_other' => false,
                'takes_medication' => true,
                'medication' => 'آهن',
            ],
            6 => [
                'has_sport_history' => true,
                'previous_coach' => 'رضا کریمی',
                'history_duration' => '2 سال',
                'previous_club' => 'باشگاه دریا',
            ],
            7 => [
                'signature_confirmed' => true,
                'terms_accepted' => true,
            ],
        };
    }

    private function contactStep(): array
    {
        $province = Province::query()->firstOrCreate(
            ['divar_id' => '904'],
            ['name' => 'تهران'],
        );
        $city = City::query()->firstOrCreate(
            ['divar_id' => '1'],
            ['province_id' => $province->id, 'name' => 'تهران', 'has_districts' => false],
        );

        return [
            'education_level' => 'bachelor',
            'landline' => '02112345678',
            'address' => 'خیابان آزادی، کوچه 12، پلاک 4',
            'province_id' => $province->id,
            'city_id' => $city->id,
            'district_id' => null,
        ];
    }
}
