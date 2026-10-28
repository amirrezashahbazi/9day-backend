<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(['user', 'admin'])],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'حداقل یک کاربر را انتخاب کنید.',
            'user_ids.min' => 'حداقل یک کاربر را انتخاب کنید.',
            'roles.required' => 'حداقل یک نقش را انتخاب کنید.',
            'roles.min' => 'حداقل یک نقش را انتخاب کنید.',
            'roles.*.in' => 'نقش انتخاب‌شده مجاز نیست.',
        ];
    }
}
