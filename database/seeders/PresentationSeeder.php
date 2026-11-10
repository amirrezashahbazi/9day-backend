<?php

namespace Database\Seeders;

use App\Enums\EducationLevel;
use App\Enums\GateTransitReason;
use App\Enums\Gender;
use App\Enums\KinshipRelation;
use App\Enums\ReferralSource;
use App\Enums\SlotPeriod;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\GateTransit;
use App\Models\Lane;
use App\Models\LaneRental;
use App\Models\LaneRentalMember;
use App\Models\LaneType;
use App\Models\Province;
use App\Models\TimeSlot;
use App\Models\TimeSlotCharge;
use App\Models\User;
use App\Models\UserKinship;
use App\Support\NationalCode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PresentationSeeder extends Seeder
{
    private const MOBILE_PREFIX = '09191';

    public function run(): void
    {
        DB::transaction(function () {
            $this->clearPreviousDemo();
            $staffId = User::query()->where('mobile', SuperAdminSeeder::MOBILE)->value('id');
            $place = $this->place();
            $people = $this->people();
            $users = [];

            foreach ($people as $index => $person) {
                $users[] = $this->makeUser($index + 1, $person, $staffId, $place);
            }

            $slots = $this->timeSlots();
            $this->charges($users, $slots, $staffId);
            $this->lanes($users, $staffId);
            $this->kinships($users);
            $this->transits($users);
        });

        $this->command?->info('دادهٔ ارائه آماده شد: کاربر، پرداخت، تردد و لاین. دوره‌ای ساخته نشد.');
    }

    private function clearPreviousDemo(): void
    {
        $ids = User::withTrashed()->where('mobile', 'like', self::MOBILE_PREFIX.'%')->pluck('id');

        GateTransit::query()
            ->where(function ($query) use ($ids) {
                $query->where('card_number', 'like', '8801%');
                if ($ids->isNotEmpty()) {
                    $query->orWhereIn('user_id', $ids);
                }
            })
            ->delete();

        if ($ids->isEmpty()) {
            return;
        }

        LaneRental::query()->whereIn('renter_user_id', $ids)->delete();
        User::withTrashed()->whereIn('id', $ids)->forceDelete();
    }

    /** @return list<array{first: string, last: string, gender: Gender, status: UserStatus, renter: bool}> */
    private function people(): array
    {
        $men = ['علی', 'حسین', 'محمد', 'امیر', 'رضا', 'مهدی', 'آرین', 'کیان', 'پارسا', 'سهیل', 'نیما', 'آرمین', 'یاسین', 'سام', 'بردیا', 'شایان', 'پویا', 'عرفان'];
        $women = ['زهرا', 'فاطمه', 'مریم', 'سارا', 'نرگس', 'هستی', 'لیلا', 'نگار', 'آیدا', 'رؤیا', 'ملیکا', 'ستایش', 'درسا', 'بهار', 'یاسمین', 'النا', 'ترانه', 'مهسا'];
        $last = ['احمدی', 'محمدی', 'حسینی', 'کریمی', 'رضایی', 'موسوی', 'جعفری', 'نوری', 'اکبری', 'قاسمی', 'یوسفی', 'صادقی', 'باقری', 'نجفی', 'شریفی', 'طاهری', 'ملکی', 'کاظمی'];
        $people = [];

        for ($index = 0; $index < 54; $index++) {
            $gender = $index % 2 === 0 ? Gender::Male : Gender::Female;
            $first = $gender === Gender::Male ? $men[$index % count($men)] : $women[$index % count($women)];
            $status = match (true) {
                $index < 36 => UserStatus::Active,
                $index < 44 => UserStatus::Pending,
                $index < 50 => UserStatus::Draft,
                default => UserStatus::Blocked,
            };
            $people[] = [
                'first' => $first,
                'last' => $last[$index % count($last)],
                'gender' => $gender,
                'status' => $status,
                'renter' => $index < 3,
            ];
        }

        return $people;
    }

    /** @param array{first: string, last: string, gender: Gender, status: UserStatus, renter: bool} $person
     * @param array{province_id: ?int, city_id: ?int, district_id: ?int} $place */
    private function makeUser(int $number, array $person, ?int $staffId, array $place): User
    {
        $registered = Carbon::now('Asia/Tehran')->subDays(40 - ($number % 28))->setTime(9 + ($number % 8), ($number * 7) % 60);
        $active = $person['status'] === UserStatus::Active;
        $user = new User([
            'mobile' => self::MOBILE_PREFIX.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'status' => $person['status'],
            'registration_step' => $active ? 7 : ($person['status'] === UserStatus::Draft ? 2 : 5),
            'first_name' => $person['first'],
            'last_name' => $person['last'],
            'father_name' => $person['gender'] === Gender::Male ? 'محمود' : 'احمد',
            'issued_from' => 'تهران',
            'national_code' => $this->nationalCode($number),
            'birth_date' => Carbon::create(1998 + ($number % 18), ($number % 12) + 1, ($number % 27) + 1)->toDateString(),
            'gender' => $person['gender'],
            'education_level' => $number % 2 === 0 ? EducationLevel::Bachelor : EducationLevel::HighSchool,
            'landline' => '021'.str_pad((string) (88000000 + $number), 8, '0', STR_PAD_LEFT),
            'emergency_phone_1' => '0912'.str_pad((string) (1000000 + $number), 7, '0', STR_PAD_LEFT),
            'emergency_phone_2' => '0935'.str_pad((string) (2000000 + $number), 7, '0', STR_PAD_LEFT),
            'address' => 'تهران، سعادت‌آباد، خیابان سرو، پلاک '.($number + 10),
            'province_id' => $place['province_id'],
            'city_id' => $place['city_id'],
            'district_id' => $place['district_id'],
            'referral_source' => [ReferralSource::Instagram, ReferralSource::Friends, ReferralSource::Website, ReferralSource::Ads][$number % 4],
            'has_illness' => false,
            'has_sport_history' => $number % 3 !== 0,
            'previous_coach' => $number % 3 !== 0 ? 'مربی مجموعه' : null,
            'history_duration' => $number % 3 !== 0 ? (($number % 4) + 1).' سال' : null,
            'previous_club' => $number % 3 !== 0 ? 'استخر ۹ روز' : null,
            'introduced_by' => $staffId,
            'signature_name' => $active ? $person['first'].' '.$person['last'] : null,
        ]);

        if ($active || $person['status'] === UserStatus::Pending || $person['status'] === UserStatus::Blocked) {
            $user->phone_verified_at = $registered->copy()->addMinutes(5);
        }
        if ($active || $person['status'] === UserStatus::Blocked) {
            $user->registration_completed_at = $registered->copy()->addHour();
            $user->signed_at = $registered->copy()->addHour();
            $user->access_card_number = '8801'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $user->access_card_enrolled_at = $registered->copy()->addHours(2);
        }
        if ($active) {
            $user->access_granted_at = $registered->copy()->addHours(3);
        }

        $user->save();
        $user->forceFill([
            'created_at' => $registered,
            'updated_at' => $registered,
        ])->save();

        $roles = [UserRole::Athlete];
        if ($person['renter']) {
            $roles[] = UserRole::LaneRenter;
        }
        if ($person['status'] === UserStatus::Draft) {
            $roles = [UserRole::User];
        }
        $user->syncRoles($roles);

        return $user;
    }

    /** @return list<TimeSlot> */
    private function timeSlots(): array
    {
        $definitions = [
            [Gender::Male, '08:00:00', '10:00:00', SlotPeriod::Morning, 2_500_000],
            [Gender::Male, '16:00:00', '18:00:00', SlotPeriod::Noon, 3_200_000],
            [Gender::Female, '10:00:00', '12:00:00', SlotPeriod::Morning, 2_500_000],
            [Gender::Female, '18:00:00', '20:00:00', SlotPeriod::Noon, 3_200_000],
        ];
        $slots = [];
        foreach ($definitions as [$gender, $start, $end, $period, $fee]) {
            $slots[] = TimeSlot::query()->firstOrCreate(
                ['gender' => $gender->value, 'starts_at' => $start, 'ends_at' => $end],
                ['period' => $period->value, 'fee' => $fee],
            );
        }

        return $slots;
    }

    /** @param list<User> $users @param list<TimeSlot> $slots */
    private function charges(array $users, array $slots, ?int $staffId): void
    {
        foreach ($users as $offset => $user) {
            if ($user->status !== UserStatus::Active && $user->status !== UserStatus::Blocked) {
                continue;
            }
            $visits = 2 + ($offset % 3);
            for ($visit = 0; $visit < $visits; $visit++) {
                $slot = $slots[($offset + $visit) % count($slots)];
                $when = Carbon::now('Asia/Tehran')->subDays(($offset + $visit * 5) % 30)->setTime(11, ($offset * 3) % 60);
                $charge = TimeSlotCharge::query()->create([
                    'user_id' => $user->id,
                    'time_slot_id' => $slot->id,
                    'sessions_count' => [8, 12, 16][$visit % 3],
                    'amount' => (int) $slot->fee,
                    'slot_label' => $this->slotLabel($slot),
                    'gender' => $slot->gender instanceof Gender ? $slot->gender->value : $slot->gender,
                    'starts_at' => $slot->starts_at,
                    'ends_at' => $slot->ends_at,
                    'period' => $slot->period instanceof SlotPeriod ? $slot->period->value : $slot->period,
                    'created_by' => $staffId,
                ]);
                $charge->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
            }
        }
    }

    /** @param list<User> $users */
    private function lanes(array $users, ?int $staffId): void
    {
        $types = LaneType::query()->pluck('id', 'name');
        if ($types->isEmpty()) {
            return;
        }
        $plans = [
            ['title' => 'لاین عرضی ارائه', 'type' => 'عرضی', 'fee' => 3_500_000, 'min' => 4, 'max' => 10, 'students' => 8, 'sessions' => 12, 'members' => 6],
            ['title' => 'لاین طولی ارائه', 'type' => 'طولی', 'fee' => 4_500_000, 'min' => 6, 'max' => 12, 'students' => 10, 'sessions' => 10, 'members' => 7],
            ['title' => 'لاین ورم‌آپ ارائه', 'type' => 'وارم‌آپ', 'fee' => 2_000_000, 'min' => 2, 'max' => 8, 'students' => 6, 'sessions' => 8, 'members' => 4],
        ];
        $renters = array_values(array_filter($users, fn (User $user) => $user->hasRole(UserRole::LaneRenter)));
        $athletes = array_values(array_filter($users, fn (User $user) => $user->status === UserStatus::Active && ! $user->hasRole(UserRole::LaneRenter)));
        $cursor = 0;

        foreach ($plans as $index => $plan) {
            $typeId = $types[$plan['type']] ?? $types->first();
            $lane = Lane::query()->firstOrCreate(
                ['title' => $plan['title']],
                [
                    'lane_type_id' => $typeId,
                    'fee' => $plan['fee'],
                    'min_people' => $plan['min'],
                    'max_people' => $plan['max'],
                    'created_by' => $staffId,
                ],
            );
            $renter = $renters[$index] ?? null;
            if (! $renter) {
                continue;
            }
            $rental = LaneRental::query()->create([
                'lane_id' => $lane->id,
                'renter_user_id' => $renter->id,
                'students_count' => $plan['students'],
                'sessions_total' => $plan['sessions'],
                'sessions_remaining' => max(1, $plan['sessions'] - 3),
                'access_card_number' => $renter->access_card_number,
                'created_by' => $staffId,
            ]);
            for ($member = 0; $member < $plan['members'] && isset($athletes[$cursor]); $member++, $cursor++) {
                LaneRentalMember::query()->create([
                    'lane_rental_id' => $rental->id,
                    'user_id' => $athletes[$cursor]->id,
                    'created_by' => $renter->id,
                ]);
            }
        }
    }

    /** @param list<User> $users */
    private function kinships(array $users): void
    {
        $pairs = [
            [4, 5, KinshipRelation::Father],
            [6, 7, KinshipRelation::Mother],
            [8, 9, KinshipRelation::Brother],
            [10, 11, KinshipRelation::Sister],
        ];
        foreach ($pairs as [$left, $right, $relation]) {
            if (! isset($users[$left], $users[$right])) {
                continue;
            }
            UserKinship::query()->create([
                'user_id' => $users[$left]->id,
                'relative_user_id' => $users[$right]->id,
                'relation' => $relation,
            ]);
        }
    }

    /** @param list<User> $users */
    private function transits(array $users): void
    {
        $cardUsers = array_values(array_filter($users, fn (User $user) => filled($user->access_card_number)));
        if ($cardUsers === []) {
            return;
        }
        $reasons = [
            GateTransitReason::Allowed,
            GateTransitReason::Allowed,
            GateTransitReason::Allowed,
            GateTransitReason::Allowed,
            GateTransitReason::NoEnrollment,
            GateTransitReason::Exhausted,
            GateTransitReason::UserBlocked,
            GateTransitReason::UnknownCard,
        ];

        for ($day = 0; $day < 30; $day++) {
            $count = 10 + (($day * 5) % 9);
            if ($day < 3) {
                $count += 8;
            }
            for ($pass = 0; $pass < $count; $pass++) {
                $reason = $reasons[($day + $pass) % count($reasons)];
                $user = $cardUsers[($day * 3 + $pass) % count($cardUsers)];
                $unknown = $reason === GateTransitReason::UnknownCard;
                $when = Carbon::now('Asia/Tehran')->startOfDay()->subDays($day)->setTime(7 + (($pass * 2) % 13), ($pass * 11) % 60);
                $transit = GateTransit::query()->create([
                    'user_id' => $unknown ? null : $user->id,
                    'card_number' => $unknown ? '88019999' : $user->access_card_number,
                    'allowed' => $reason === GateTransitReason::Allowed,
                    'reason' => $reason,
                    'sessions_remaining_after' => $reason === GateTransitReason::Allowed ? 3 + ($pass % 8) : 0,
                    'course_title' => $reason === GateTransitReason::Allowed ? 'سانس آزاد' : null,
                    'user_name' => $unknown ? null : trim($user->first_name.' '.$user->last_name),
                ]);
                $transit->forceFill(['created_at' => $when, 'updated_at' => $when])->save();
            }
        }
    }

    /** @return array{province_id: ?int, city_id: ?int, district_id: ?int} */
    private function place(): array
    {
        $province = Province::query()->where('name', 'like', '%تهران%')->first() ?? Province::query()->first();
        $city = $province?->cities()->first();
        $district = $city?->districts()->first();

        return [
            'province_id' => $province?->id,
            'city_id' => $city?->id,
            'district_id' => $district?->id,
        ];
    }

    private function nationalCode(int $number): string
    {
        $base = str_pad((string) (200000000 + ($number * 137)), 9, '0', STR_PAD_LEFT);
        $sum = 0;
        for ($index = 0; $index < 9; $index++) {
            $sum += ((int) $base[$index]) * (10 - $index);
        }
        $remainder = $sum % 11;
        $digit = $remainder < 2 ? $remainder : 11 - $remainder;
        $code = $base.$digit;
        if (! NationalCode::isValid($code) || User::withTrashed()->where('national_code', $code)->exists()) {
            return $this->nationalCode($number + 100);
        }

        return $code;
    }

    private function slotLabel(TimeSlot $slot): string
    {
        $gender = ($slot->gender instanceof Gender ? $slot->gender : Gender::tryFrom((string) $slot->gender)) === Gender::Female ? 'بانوان' : 'آقایان';
        $period = ($slot->period instanceof SlotPeriod ? $slot->period : SlotPeriod::tryFrom((string) $slot->period)) === SlotPeriod::Morning ? 'صبح' : 'ظهر';
        $start = substr((string) $slot->starts_at, 0, 5);
        $end = substr((string) $slot->ends_at, 0, 5);

        return $gender.' '.$start.'-'.$end.' '.$period;
    }
}
