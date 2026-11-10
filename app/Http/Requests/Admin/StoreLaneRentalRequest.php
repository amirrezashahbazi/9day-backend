<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLaneRentalRequest extends FormRequest
{
    public const DEFAULT_PASSWORD = '123456Aa@';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'lane_id' => ['required', 'integer', Rule::exists('lanes', 'id')->whereNull('deleted_at')],
            'students_count' => ['required', 'integer', 'min:1', 'max:500'],
            'sessions_count' => ['required', 'integer', 'min:1', 'max:500'],
            'access_card_number' => ['required', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'موبایل اجاره‌کننده را وارد کنید.',
            'mobile.regex' => 'موبایل باید ۱۱ رقم و با ۰۹ شروع شود.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر است.',
            'lane_id.required' => 'لاین را انتخاب کنید.',
            'students_count.required' => 'تعداد شاگردها را وارد کنید.',
            'sessions_count.required' => 'تعداد جلسات را وارد کنید.',
            'access_card_number.required' => 'شماره کارت را وارد کنید.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $password = (string) $this->input('password', '');
        $this->merge([
            'mobile' => trim((string) $this->input('mobile')),
            'first_name' => trim((string) $this->input('first_name', '')) ?: null,
            'last_name' => trim((string) $this->input('last_name', '')) ?: null,
            'password' => $password === '' ? self::DEFAULT_PASSWORD : $password,
            'access_card_number' => trim((string) $this->input('access_card_number')),
        ]);
    }
}
