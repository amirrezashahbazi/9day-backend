<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(UserStatus::class)],
            'role' => ['sometimes', Rule::in(['user', 'admin'])],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(['user', 'admin'])],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::enum(Permission::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'وضعیت دسترسی را مشخص کنید.',
            'role.in' => 'نقش انتخاب‌شده مجاز نیست.',
            'roles.min' => 'حداقل یک نقش را انتخاب کنید.',
            'roles.*.in' => 'نقش انتخاب‌شده مجاز نیست.',
            'permissions.*.enum' => 'دسترسی انتخاب‌شده مجاز نیست.',
        ];
    }
}
