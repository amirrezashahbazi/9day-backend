<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Api\Admin\MobileVerificationController;
use App\Http\Requests\Concerns\RejectsPersianDigits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    use RejectsPersianDigits {
        withValidator as rejectPersianDigitsValidator;
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        foreach (['mobile', 'first_name', 'last_name'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }

        foreach (['first_name', 'last_name'] as $field) {
            if (array_key_exists($field, $input) && $input[$field] === '') {
                $input[$field] = null;
            }
        }

        $this->replace($input);
    }

    public function rules(): array
    {
        $name = ['nullable', 'string', 'min:2', 'max:100', 'regex:/^[\p{Arabic}A-Za-z\s\x{200C}]+$/u'];

        return [
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/', Rule::unique('users', 'mobile')->whereNull('deleted_at')],
            'first_name' => $name,
            'last_name' => $name,
            'role' => ['nullable', Rule::in(['user', 'athlete', 'admin'])],
            'password' => [Rule::requiredIf(fn () => $this->input('role') === 'admin'), 'nullable', 'string', 'min:8', 'max:100'],
            'grant_access' => ['required', 'boolean'],
            'skip_phone_verification' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectPersianDigitsValidator($validator);

        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('mobile')) {
                return;
            }

            $mobile = $this->string('mobile')->toString();
            if ($mobile === '') {
                return;
            }

            $skip = $this->boolean('skip_phone_verification');
            $verified = Cache::has(MobileVerificationController::cacheKey($mobile));

            if (! $skip && ! $verified) {
                $validator->errors()->add('mobile', 'ابتدا شماره موبایل را با کد تایید احراز کنید.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'موبایل را وارد کنید.',
            'mobile.regex' => 'موبایل باید ۱۱ رقم انگلیسی و با 09 شروع شود.',
            'mobile.unique' => 'این موبایل قبلاً ثبت شده است.',
            'first_name.regex' => 'نام فقط می‌تواند حرف باشد.',
            'last_name.regex' => 'نام خانوادگی فقط می‌تواند حرف باشد.',
            'role.in' => 'نقش انتخاب‌شده مجاز نیست.',
            'password.required' => 'برای ادمین جدید رمز عبور لازم است.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر است.',
            'grant_access.required' => 'وضعیت دسترسی را مشخص کنید.',
        ];
    }
}
