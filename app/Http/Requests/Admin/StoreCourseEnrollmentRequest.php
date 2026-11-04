<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', Rule::exists('courses', 'id')->whereNull('deleted_at')],
            'sessions_total' => ['nullable', 'integer', 'min:1', 'max:500'],
            'renew_from_id' => ['nullable', 'integer', 'exists:course_enrollments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'دوره را انتخاب کنید.',
            'course_id.exists' => 'دوره معتبر نیست.',
            'sessions_total.min' => 'تعداد جلسات حداقل ۱ است.',
            'sessions_total.max' => 'تعداد جلسات بیش از حد مجاز است.',
        ];
    }
}
