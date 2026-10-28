<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCoursesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('created_from'), ['after_or_equal:created_from'])],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'course_type_id' => ['nullable', 'integer', Rule::exists('course_types', 'id')],
            'course_field_id' => ['nullable', 'integer', Rule::exists('course_fields', 'id')],
            'course_level_id' => ['nullable', 'integer', Rule::exists('course_levels', 'id')],
            'title' => ['nullable', 'string', 'max:200'],
            'enrollments_op' => ['nullable', Rule::in(['lt', 'gt', 'eq'])],
            'enrollments_value' => ['nullable', 'integer', 'min:0', 'required_with:enrollments_op'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'created_to.after_or_equal' => 'تاریخ پایان ایجاد نباید قبل از شروع باشد.',
            'enrollments_value.required_with' => 'مقدار تعداد ثبت‌نامی را وارد کنید.',
        ];
    }
}
