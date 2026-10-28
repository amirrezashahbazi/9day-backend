<?php

namespace App\Rules;

use App\Support\NationalCode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IranianNationalCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[0-9]{10}$/', $value)) {
            $fail('کد ملی باید ۱۰ رقم انگلیسی باشد.');

            return;
        }

        if (! NationalCode::isValid($value)) {
            $fail('کد ملی معتبر نیست.');
        }
    }
}
