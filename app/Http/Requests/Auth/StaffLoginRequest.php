<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\RejectsPersianDigits;
use Illuminate\Foundation\Http\FormRequest;

class StaffLoginRequest extends FormRequest
{
    use RejectsPersianDigits;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => trim((string) $this->input('mobile')),
        ]);
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'موبایل را وارد کنید.',
            'mobile.regex' => 'موبایل باید ۱۱ رقم انگلیسی و با 09 شروع شود.',
            'password.required' => 'رمز عبور را وارد کنید.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر است.',
        ];
    }
}
