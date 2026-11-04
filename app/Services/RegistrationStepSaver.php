<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\City;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RegistrationStepSaver
{
    public function __construct(private CourseEnrollmentService $enrollments)
    {
    }

    public function save(User $user, int $step, Request $request): User
    {
        $data = $request->safe()->except(['signature_confirmed', 'terms_accepted', 'course_id', 'sessions_total']);

        if ($step === 2) {
            $city = City::query()->find($data['city_id'] ?? null);
            $flag = $city?->getAttributes()['has_districts'] ?? null;
            $hasDistricts = in_array($flag, [true, 1, '1', 't', 'true'], true);
            if (! $hasDistricts) {
                $data['district_id'] = null;
            }
        }

        if ($step === 4 && ($data['referral_source'] ?? null) !== 'other') {
            $data['referral_other'] = null;
        }

        if ($step === 5) {
            foreach (['skin', 'respiratory', 'cardiac', 'other'] as $type) {
                $selected = $request->boolean('has_illness') && $request->boolean('illness_'.$type);
                $data['illness_'.$type] = $selected;
                if (! $selected) {
                    $data['illness_'.$type.'_description'] = null;
                }
            }

            if (! $request->boolean('takes_medication')) {
                $data['medication'] = null;
            }
        }

        if ($step === 6 && ! $request->boolean('has_sport_history')) {
            $data['previous_coach'] = null;
            $data['history_duration'] = null;
            $data['previous_club'] = null;
        }

        if ($step === 3) {
            foreach (['course_swimming', 'course_water_polo', 'course_diving', 'course_private', 'course_semi_private'] as $flag) {
                $data[$flag] = (bool) ($data[$flag] ?? false);
            }
        }

        $user->fill($data);

        if ($user->registration_step < $step) {
            $user->registration_step = $step;
        }

        if ($step === 7) {
            $user->signed_at = now();
            $user->registration_completed_at = now();

            $byAdmin = $request->user()
                && $request->user()->id !== $user->id
                && $request->user()->isStaff();

            if ($byAdmin) {
                $user->status = UserStatus::Active;
                $user->access_granted_at ??= now();
            } elseif ($user->status === UserStatus::Draft) {
                $user->status = UserStatus::Pending;
            }
        }

        $user->save();

        if ($step === 3 && $request->filled('course_id')) {
            $course = Course::query()->findOrFail((int) $request->input('course_id'));
            $sessions = $request->filled('sessions_total') ? (int) $request->input('sessions_total') : null;
            $actor = $request->user();
            $this->enrollments->enroll(
                $user,
                $course,
                $sessions,
                $actor && $actor->id !== $user->id ? $actor : null,
            );
        }

        $user->load(['province', 'city', 'district', 'introducer']);

        Log::channel('registration')->info('Registration step saved', [
            'user_id' => $user->id,
            'step' => $step,
            'completed' => $user->registration_completed_at !== null,
            'by_admin' => $request->user()?->id !== $user->id,
            'actor_id' => $request->user()?->id,
        ]);

        return $user;
    }
}
