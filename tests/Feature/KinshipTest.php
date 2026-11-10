<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Models\UserKinship;
use App\Support\NationalCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class KinshipTest extends TestCase
{
    use AuthenticatesUsers, RefreshDatabase;

    public function test_lookup_finds_a_user_without_changing_them(): void
    {
        $relative = User::factory()->create([
            'mobile' => '09120000011',
            'first_name' => 'حسین',
            'last_name' => 'محمدی',
        ]);
        $token = $this->loginUser('09120000022');

        $this->withToken($token)->getJson('/api/registration/kin-lookup?mobile=09120000011')
            ->assertOk()
            ->assertJsonPath('user.id', $relative->id)
            ->assertJsonPath('user.first_name', 'حسین');

        $this->withToken($token)->getJson('/api/registration/kin-lookup?mobile=09120000099')
            ->assertOk()
            ->assertJsonPath('user', null);

        $relative->refresh();
        $this->assertSame('حسین', $relative->first_name);
        $this->assertSame('محمدی', $relative->last_name);
    }

    public function test_registration_links_an_existing_relative_and_rejects_a_missing_one(): void
    {
        $relative = User::factory()->create([
            'mobile' => '09120000031',
            'first_name' => 'زهرا',
            'last_name' => 'کریمی',
        ]);
        $token = $this->loginUser('09120000032');
        $this->withToken($token)->putJson('/api/registration/steps/1', [
            'first_name' => 'سارا',
            'last_name' => 'محمدی',
            'father_name' => 'حسین',
            'issued_from' => 'تهران',
            'national_code' => NationalCode::sample(),
            'birth_date' => '1998-04-12',
            'gender' => 'female',
        ])->assertOk();

        $missing = $this->contact();
        $missing['has_first_degree_relative'] = true;
        $missing['relative_mobile'] = '09120000999';
        $missing['relative_relation'] = 'mother';
        $this->withToken($token)->putJson('/api/registration/steps/2', $missing)
            ->assertStatus(422)
            ->assertJsonPath('errors.relative_mobile.0', 'کاربری با این موبایل پیدا نشد.');
        $this->assertSame(0, UserKinship::query()->count());

        $payload = $this->contact();
        $payload['has_first_degree_relative'] = true;
        $payload['relative_mobile'] = $relative->mobile;
        $payload['relative_relation'] = 'mother';
        $this->withToken($token)->putJson('/api/registration/steps/2', $payload)
            ->assertOk()
            ->assertJsonPath('user.first_degree_relative.relation', 'mother')
            ->assertJsonPath('user.first_degree_relative.relative.id', $relative->id);

        $this->assertSame(1, UserKinship::query()->count());
        $relative->refresh();
        $this->assertSame('زهرا', $relative->first_name);
        $this->assertSame('کریمی', $relative->last_name);
    }

    public function test_mobile_and_kinship_indexes_exist(): void
    {
        $names = collect(DB::select('SELECT indexname FROM pg_indexes WHERE schemaname = current_schema()'))
            ->pluck('indexname');

        $this->assertTrue($names->contains('users_mobile_active_lookup'));
        $this->assertTrue($names->contains('user_kinships_pair_unique'));
        $this->assertTrue($names->contains('user_kinships_relative_relation_index'));
    }

    /** @return array<string, mixed> */
    private function contact(): array
    {
        $province = Province::query()->firstOrCreate(['divar_id' => '904'], ['name' => 'تهران']);
        $city = City::query()->firstOrCreate(
            ['divar_id' => '1'],
            ['province_id' => $province->id, 'name' => 'تهران', 'has_districts' => false],
        );

        return [
            'education_level' => 'bachelor',
            'landline' => '02112345678',
            'emergency_phone_1' => '09121110000',
            'emergency_phone_2' => '09121110001',
            'address' => 'خیابان آزادی، کوچه 12، پلاک 4',
            'province_id' => $province->id,
            'city_id' => $city->id,
            'district_id' => null,
        ];
    }
}
