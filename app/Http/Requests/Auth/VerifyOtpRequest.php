<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\RejectsPersianDigits;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
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
            'code' => trim((string) $this->input('code')),
        ]);
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
            'code' => ['required', 'regex:/^[0-9]{5}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'موبایل را وارد کنید.',
            'mobile.regex' => 'موبایل باید ۱۱ رقم انگلیسی و با 09 شروع شود.',
            'code.required' => 'کد تایید را وارد کنید.',
            'code.regex' => 'کد تایید باید ۵ رقم انگلیسی باشد.',
        ];
    }
}
