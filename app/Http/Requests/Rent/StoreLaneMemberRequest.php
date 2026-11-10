<?php

namespace App\Http\Requests\Rent;

use App\Rules\IranianNationalCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLaneMemberRequest extends FormRequest
{
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
            'national_code' => ['nullable', 'string', new IranianNationalCode, Rule::unique('users', 'national_code')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'موبایل را وارد کنید.',
            'mobile.regex' => 'موبایل باید ۱۱ رقم و با ۰۹ شروع شود.',
            'national_code.unique' => 'این کد ملی قبلاً ثبت شده است.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $national = trim((string) $this->input('national_code', ''));
        $this->merge([
            'mobile' => trim((string) $this->input('mobile')),
            'first_name' => trim((string) $this->input('first_name', '')) ?: null,
            'last_name' => trim((string) $this->input('last_name', '')) ?: null,
            'national_code' => $national === '' ? null : $national,
        ]);
    }
}
