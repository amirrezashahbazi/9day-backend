<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Rules\IranianNationalCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'national_code' => ['nullable', 'string', new IranianNationalCode],
            'file_number' => ['nullable', 'string', 'max:20'],
            'registered_from' => ['nullable', 'date_format:Y-m-d'],
            'registered_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('registered_from'), ['after_or_equal:registered_from'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.enum' => 'وضعیت نامعتبر است.',
            'role.enum' => 'نقش نامعتبر است.',
            'registered_from.date_format' => 'تاریخ شروع ثبت‌نام معتبر نیست.',
            'registered_to.date_format' => 'تاریخ پایان ثبت‌نام معتبر نیست.',
            'registered_to.after_or_equal' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.',
            'per_page.max' => 'حداکثر ۵۰۰ مورد در هر صفحه مجاز است.',
        ];
    }
}
