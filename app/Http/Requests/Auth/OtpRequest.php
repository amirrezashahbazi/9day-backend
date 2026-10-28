<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\RejectsPersianDigits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OtpRequest extends FormRequest
{
    use RejectsPersianDigits;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('mobile')) {
            $this->merge([
                'mobile' => trim((string) $this->input('mobile')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
            'purpose' => ['nullable', Rule::in(['login', 'register'])],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'موبایل را وارد کنید.',
            'mobile.regex' => 'موبایل باید ۱۱ رقم انگلیسی و با 09 شروع شود.',
            'purpose.in' => 'هدف درخواست کد معتبر نیست.',
        ];
    }
}
