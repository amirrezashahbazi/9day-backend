<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_banner_max_kb' => ['required', 'integer', 'min:100', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_banner_max_kb.required' => 'حداکثر حجم بنر الزامی است.',
            'course_banner_max_kb.min' => 'حداقل حجم مجاز ۱۰۰ کیلوبایت است.',
            'course_banner_max_kb.max' => 'حداکثر حجم مجاز ۱۰ مگابایت است.',
        ];
    }
}
