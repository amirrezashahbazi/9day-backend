<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EnrollAccessCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('card_number')) {
            $this->merge([
                'card_number' => preg_replace('/\D+/', '', (string) $this->input('card_number')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'card_number' => ['required', 'string', 'min:4', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_number.required' => 'شماره کارت را وارد کنید.',
            'card_number.min' => 'شماره کارت معتبر نیست.',
            'card_number.max' => 'شماره کارت معتبر نیست.',
        ];
    }
}
