<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseTaxonomyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $table = (string) $this->route('taxonomyTable');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique($table, 'name')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام الزامی است.',
            'name.unique' => 'این نام قبلاً ثبت شده است.',
            'name.max' => 'نام نباید بیشتر از ۱۲۰ کاراکتر باشد.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
