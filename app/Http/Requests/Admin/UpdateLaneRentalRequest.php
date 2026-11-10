<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLaneRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'students_count' => ['required', 'integer', 'min:1', 'max:500'],
            'sessions_remaining' => ['required', 'integer', 'min:0', 'max:500'],
            'access_card_number' => ['required', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'students_count.required' => 'ظرفیت را وارد کنید.',
            'sessions_remaining.required' => 'جلسات باقی‌مانده را وارد کنید.',
            'access_card_number.required' => 'شماره کارت را وارد کنید.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'access_card_number' => trim((string) $this->input('access_card_number')),
        ]);
    }
}
