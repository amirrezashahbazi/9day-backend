<?php

namespace App\Http\Requests\Admin;

use App\Enums\EducationLevel;
use App\Enums\UserRole;
use App\Http\Requests\Concerns\RejectsPersianDigits;
use App\Rules\IranianNationalCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportUsersRequest extends FormRequest
{
    use RejectsPersianDigits;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'national_code' => ['nullable', 'string', new IranianNationalCode],
            'file_number' => ['nullable', 'string', 'max:20'],
            'birth_from' => ['nullable', 'date_format:Y-m-d'],
            'birth_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('birth_from'), ['after_or_equal:birth_from'])],
            'registered_from' => ['nullable', 'date_format:Y-m-d'],
            'registered_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('registered_from'), ['after_or_equal:registered_from'])],
            'signed_from' => ['nullable', 'date_format:Y-m-d'],
            'signed_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('signed_from'), ['after_or_equal:signed_from'])],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')->whereNull('deleted_at')],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')->whereNull('deleted_at')],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->whereNull('deleted_at')],
            'education_level' => ['nullable', Rule::enum(EducationLevel::class)],
            'role' => ['nullable', Rule::in([UserRole::User->value, UserRole::Admin->value])],
            'courses' => ['nullable', 'array'],
            'courses.*' => ['in:swimming,water_polo,diving,private,semi_private'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'birth_from.date_format' => 'تاریخ شروع تولد معتبر نیست.',
            'birth_to.date_format' => 'تاریخ پایان تولد معتبر نیست.',
            'birth_to.after_or_equal' => 'بازه تاریخ تولد درست نیست.',
            'registered_from.date_format' => 'تاریخ شروع ثبت‌نام معتبر نیست.',
            'registered_to.date_format' => 'تاریخ پایان ثبت‌نام معتبر نیست.',
            'registered_to.after_or_equal' => 'بازه تاریخ ثبت‌نام درست نیست.',
            'signed_from.date_format' => 'تاریخ شروع تایید معتبر نیست.',
            'signed_to.date_format' => 'تاریخ پایان تایید معتبر نیست.',
            'signed_to.after_or_equal' => 'بازه تاریخ تایید درست نیست.',
            'role.in' => 'نقش انتخاب‌شده مجاز نیست.',
            'national_code' => 'کد ملی معتبر نیست.',
            'per_page.max' => 'حداکثر ۵۰۰ مورد در هر صفحه مجاز است.',
        ];
    }
}
