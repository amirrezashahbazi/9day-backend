<?php

namespace App\Http\Requests\Registration;

use App\Http\Requests\Concerns\RejectsPersianDigits;
use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\KinshipRelation;
use App\Enums\ReferralSource;
use App\Enums\UserStatus;
use App\Models\City;
use App\Models\District;
use App\Models\User;
use App\Rules\IranianNationalCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveRegistrationStepRequest extends FormRequest
{
    use RejectsPersianDigits {
        withValidator as rejectPersianDigitsValidator;
    }

    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor || ! $actor->isMember() || $actor->isStaff()) {
            return false;
        }

        $user = $this->subject();

        if ($user->status === UserStatus::Active || $user->status === UserStatus::Blocked) {
            throw new HttpResponseException(response()->json([
                'message' => 'پس از تایید دسترسی، ویرایش پرونده ممکن نیست.',
            ], 403));
        }

        $this->assertStepOrder($user);

        return true;
    }

    protected function subject(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }

    protected function assertStepOrder(User $user): void
    {
        $step = (int) $this->route('step');

        if ($step < 1 || $step > 7) {
            throw new HttpResponseException(response()->json([
                'message' => 'مرحله نامعتبر است.',
            ], 422));
        }

        if ($step > $user->registration_step + 1) {
            throw new HttpResponseException(response()->json([
                'message' => 'ابتدا مرحله قبلی را تکمیل کنید.',
            ], 422));
        }
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'این بخش مخصوص ثبت‌نام کاربران است.',
        ], 403));
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value);
            }
        }

        foreach ([
            'landline',
            'emergency_phone_1',
            'emergency_phone_2',
            'district_id',
            'referral_other',
            'illness_skin_description',
            'illness_respiratory_description',
            'illness_cardiac_description',
            'illness_other_description',
            'medication',
            'previous_coach',
            'history_duration',
            'previous_club',
        ] as $field) {
            if (array_key_exists($field, $input) && $input[$field] === '') {
                $input[$field] = null;
            }
        }

        $this->replace($input);
    }

    public function rules(): array
    {
        $name = ['required', 'string', 'min:2', 'max:100', 'regex:/^[\p{Arabic}A-Za-z\s\x{200C}]+$/u'];
        $optionalName = ['nullable', 'string', 'min:2', 'max:100', 'regex:/^[\p{Arabic}A-Za-z\s\x{200C}]+$/u'];
        $optionalText = ['nullable', 'string', 'min:2', 'max:150', 'regex:/^[\p{Arabic}A-Za-z0-9\s\x{200C}\-]+$/u'];

        return match ((int) $this->route('step')) {
            1 => [
                'first_name' => $name,
                'last_name' => $name,
                'father_name' => $name,
                'issued_from' => $name,
                'national_code' => ['required', 'string', new IranianNationalCode, Rule::unique('users', 'national_code')->ignore($this->subject()->id)->whereNull('deleted_at')],
                'federation_inquiry' => ['sometimes', 'boolean'],
                'birth_date' => ['required', 'date_format:Y-m-d', 'after:1920-01-01', 'before:today'],
                'gender' => ['required', Rule::enum(Gender::class)],
            ],
            2 => [
                'education_level' => ['required', Rule::enum(EducationLevel::class)],
                'landline' => ['nullable', 'regex:/^0[1-8][0-9]{9}$/'],
                'emergency_phone_1' => ['required', 'regex:/^09[0-9]{9}$/'],
                'emergency_phone_2' => ['required', 'regex:/^09[0-9]{9}$/', 'different:emergency_phone_1'],
                'address' => ['required', 'string', 'min:5', 'max:255', 'regex:/^[\p{Arabic}A-Za-z0-9\s\x{200C}\-،,.\/]+$/u'],
                'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')->whereNull('deleted_at')],
                'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->whereNull('deleted_at')],
                'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->whereNull('deleted_at')],
                'has_first_degree_relative' => ['sometimes', 'boolean'],
                'relative_mobile' => [Rule::requiredIf(fn () => $this->boolean('has_first_degree_relative')), 'nullable', 'regex:/^09[0-9]{9}$/'],
                'relative_relation' => [Rule::requiredIf(fn () => $this->boolean('has_first_degree_relative')), 'nullable', Rule::in(KinshipRelation::values())],
            ],
            3 => [
                'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->whereNull('deleted_at')],
                'sessions_total' => ['nullable', 'integer', 'min:1', 'max:500'],
                'course_swimming' => ['sometimes', 'boolean'],
                'course_water_polo' => ['sometimes', 'boolean'],
                'course_diving' => ['sometimes', 'boolean'],
                'course_private' => ['sometimes', 'boolean'],
                'course_semi_private' => ['sometimes', 'boolean'],
            ],
            4 => [
                'referral_source' => ['required', Rule::enum(ReferralSource::class)],
                'referral_other' => [Rule::requiredIf(fn () => $this->input('referral_source') === 'other'), 'nullable', 'string', 'min:2', 'max:255'],
            ],
            5 => $this->illnessRules(),
            6 => [
                'has_sport_history' => ['required', 'boolean'],
                'previous_coach' => [Rule::requiredIf(fn () => $this->boolean('has_sport_history')), ...$optionalName],
                'history_duration' => [Rule::requiredIf(fn () => $this->boolean('has_sport_history')), 'nullable', 'string', 'min:1', 'max:150', 'regex:/^[\p{Arabic}A-Za-z0-9\s\x{200C}\-]+$/u'],
                'previous_club' => [Rule::requiredIf(fn () => $this->boolean('has_sport_history')), ...$optionalText],
            ],
            7 => [
                'signature_confirmed' => ['accepted'],
                'terms_accepted' => ['accepted'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'نام را وارد کنید.',
            'last_name.required' => 'نام خانوادگی را وارد کنید.',
            'father_name.required' => 'نام پدر را وارد کنید.',
            'issued_from.required' => 'محل صدور را وارد کنید.',
            'first_name.regex' => 'نام فقط می‌تواند حرف باشد.',
            'last_name.regex' => 'نام خانوادگی فقط می‌تواند حرف باشد.',
            'father_name.regex' => 'نام پدر فقط می‌تواند حرف باشد.',
            'issued_from.regex' => 'محل صدور فقط می‌تواند حرف باشد.',
            'birth_date.required' => 'تاریخ تولد را وارد کنید.',
            'birth_date.date_format' => 'تاریخ تولد باید میلادی و با ارقام انگلیسی باشد.',
            'birth_date.before' => 'تاریخ تولد باید قبل از امروز باشد.',
            'birth_date.after' => 'تاریخ تولد معتبر نیست.',
            'gender.required' => 'جنسیت را انتخاب کنید.',
            'education_level.required' => 'مقطع را انتخاب کنید.',
            'landline.regex' => 'تلفن ثابت باید ۱۱ رقم انگلیسی باشد و با صفر شروع شود.',
            'emergency_phone_1.required' => 'شماره ضروری اول را وارد کنید.',
            'emergency_phone_1.regex' => 'شماره ضروری اول باید ۱۱ رقم انگلیسی و با 09 شروع شود.',
            'emergency_phone_2.required' => 'شماره ضروری دوم را وارد کنید.',
            'emergency_phone_2.regex' => 'شماره ضروری دوم باید ۱۱ رقم انگلیسی و با 09 شروع شود.',
            'emergency_phone_2.different' => 'شماره‌های ضروری نباید یکسان باشند.',
            'address.required' => 'نشانی را وارد کنید.',
            'address.min' => 'نشانی را کامل‌تر بنویسید.',
            'address.regex' => 'نشانی فقط می‌تواند حرف، عدد و نشانه‌های نشانی باشد.',
            'province_id.required' => 'استان را انتخاب کنید.',
            'province_id.exists' => 'استان معتبر نیست.',
            'city_id.required' => 'شهر را انتخاب کنید.',
            'city_id.exists' => 'شهر معتبر نیست.',
            'district_id.exists' => 'منطقه معتبر نیست.',
            'relative_mobile.required' => 'موبایل خویشاوند را وارد کنید.',
            'relative_mobile.regex' => 'موبایل خویشاوند باید ۱۱ رقم و با ۰۹ شروع شود.',
            'relative_relation.required' => 'نسبت را انتخاب کنید.',
            'relative_relation.in' => 'نسبت انتخاب‌شده مجاز نیست.',
            'course_id.required' => 'دوره را انتخاب کنید.',
            'course_id.exists' => 'دوره معتبر نیست.',
            'referral_source.required' => 'نحوه آشنایی را انتخاب کنید.',
            'referral_other.required' => 'سایر روش آشنایی را بنویسید.',
            'has_illness.required' => 'وضعیت بیماری را مشخص کنید.',
            'illness_skin_description.required' => 'شرح این بیماری را بنویسید.',
            'illness_respiratory_description.required' => 'شرح این بیماری را بنویسید.',
            'illness_cardiac_description.required' => 'شرح این بیماری را بنویسید.',
            'illness_other_description.required' => 'شرح این بیماری را بنویسید.',
            'history_duration.min' => 'مدت سابقه را وارد کنید.',
            'terms_accepted.accepted' => 'پذیرش شرایط و قوانین لازم است.',
            'takes_medication.required' => 'وضعیت مصرف دارو را مشخص کنید.',
            'medication.required' => 'نام دارو را بنویسید.',
            'has_sport_history.required' => 'سابقه ورزشی را مشخص کنید.',
            'previous_coach.required' => 'نام مربی قبلی را وارد کنید.',
            'history_duration.required' => 'مدت سابقه را وارد کنید.',
            'previous_club.required' => 'باشگاه قبلی را وارد کنید.',
            'signature_confirmed.accepted' => 'تایید صحت اطلاعات لازم است.',
            'national_code.unique' => 'این کد ملی قبلاً ثبت شده است.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectPersianDigitsValidator($validator);

        $validator->after(function (Validator $validator): void {
            $step = (int) $this->route('step');

            if ($step === 2) {
                $city = City::query()
                    ->where('province_id', $this->input('province_id'))
                    ->whereKey($this->input('city_id'))
                    ->first();

                if (! $city) {
                    $validator->errors()->add('city_id', 'شهر با استان انتخاب‌شده هم‌خوان نیست.');
                } elseif ($city && $this->filled('district_id') && ! District::query()->where('city_id', $city->id)->whereKey($this->input('district_id'))->exists()) {
                    $validator->errors()->add('district_id', 'منطقه با شهر انتخاب‌شده هم‌خوان نیست.');
                }

                if ($this->boolean('has_first_degree_relative') && $this->filled('relative_mobile')) {
                    $relative = User::query()->where('mobile', $this->string('relative_mobile')->toString())->first();
                    if (! $relative) {
                        $validator->errors()->add('relative_mobile', 'کاربری با این موبایل پیدا نشد.');
                    } elseif ($relative->id === $this->subject()->id) {
                        $validator->errors()->add('relative_mobile', 'نمی‌توانید خودتان را به عنوان خویشاوند ثبت کنید.');
                    }
                }
            }

            if ($step === 5 && $this->boolean('has_illness') && ! $this->hasSelectedIllness()) {
                $validator->errors()->add('illness_skin', 'نوع بیماری را انتخاب کنید.');
            }
        });
    }

    private function illnessRules(): array
    {
        $rules = [
            'has_illness' => ['required', 'boolean'],
            'takes_medication' => ['required', 'boolean'],
            'medication' => [Rule::requiredIf(fn () => $this->boolean('takes_medication')), 'nullable', 'string', 'min:2', 'max:500'],
        ];

        foreach (['skin', 'respiratory', 'cardiac', 'other'] as $type) {
            $flag = 'illness_'.$type;
            $rules[$flag] = ['required', 'boolean'];
            $rules[$flag.'_description'] = [
                Rule::requiredIf(fn () => $this->boolean('has_illness') && $this->boolean($flag)),
                'nullable',
                'string',
                'min:2',
                'max:500',
            ];
        }

        return $rules;
    }

    private function hasSelectedIllness(): bool
    {
        foreach (['illness_skin', 'illness_respiratory', 'illness_cardiac', 'illness_other'] as $field) {
            if ($this->boolean($field)) {
                return true;
            }
        }

        return false;
    }
}
