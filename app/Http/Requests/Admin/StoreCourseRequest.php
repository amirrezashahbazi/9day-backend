<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Http\Requests\Admin\Concerns\ValidatesCourseSchedule;
use App\Models\AppSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    use ValidatesCourseSchedule;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = AppSetting::getInt('course_banner_max_kb', 1024);

        return [
            'course_type_id' => ['required', 'integer', Rule::exists('course_types', 'id')->whereNull('deleted_at')],
            'course_field_id' => ['required', 'integer', Rule::exists('course_fields', 'id')->whereNull('deleted_at')],
            'course_level_id' => ['required', 'integer', Rule::exists('course_levels', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:200'],
            'registration_from' => ['nullable', 'date_format:Y-m-d'],
            'registration_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:registration_from'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            ...$this->courseScheduleRules(),
            'min_age' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_age' => ['nullable', 'integer', 'min:1', 'max:100', 'gte:min_age'],
            'fee' => ['required', 'integer', 'min:0'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'sessions_count' => ['required', 'integer', 'min:1', 'max:500'],
            'primary_index' => ['nullable', 'integer', 'min:0', 'max:3'],
            'banners' => ['nullable', 'array', 'max:4'],
            'banners.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maxKb],
        ];
    }

    public function messages(): array
    {
        $maxKb = AppSetting::getInt('course_banner_max_kb', 1024);

        return [
            'course_type_id.required' => 'نوع دوره را انتخاب کنید.',
            'course_field_id.required' => 'رشته را انتخاب کنید.',
            'course_level_id.required' => 'سطح را انتخاب کنید.',
            'title.required' => 'عنوان دوره الزامی است.',
            'gender.enum' => 'جنسیت نامعتبر است.',
            ...$this->courseScheduleMessages(),
            'fee.required' => 'هزینه دوره الزامی است.',
            'capacity.required' => 'ظرفیت الزامی است.',
            'sessions_count.required' => 'تعداد جلسات دوره الزامی است.',
            'registration_to.after_or_equal' => 'پایان ثبت‌نام نمی‌تواند قبل از شروع باشد.',
            'ends_on.after_or_equal' => 'پایان دوره نمی‌تواند قبل از شروع باشد.',
            'max_age.gte' => 'حداکثر سن باید بزرگ‌تر یا مساوی حداقل سن باشد.',
            'banners.max' => 'حداکثر ۴ تصویر بنر مجاز است.',
            'banners.*.image' => 'فایل بنر باید تصویر باشد.',
            'banners.*.max' => "حجم هر تصویر بنر حداکثر {$maxKb} کیلوبایت است.",
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullable = [];
        foreach (['registration_from', 'registration_to', 'starts_on', 'ends_on', 'min_age', 'max_age'] as $key) {
            $value = $this->input($key);
            $nullable[$key] = ($value === '' || $value === null) ? null : $value;
        }

        $this->merge([
            'title' => trim((string) $this->input('title')),
            ...$nullable,
            ...$this->preparedCourseSchedule(),
        ]);
    }

    public function withValidator($validator): void
    {
        $this->assertScheduleOrder($validator);
    }
}
