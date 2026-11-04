<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'access_control_device_serial' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'access_control_device_serial.required' => 'شماره سریال دستگاه را وارد کنید.',
        ];
    }
}
