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
            'access_control_display_seconds' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'access_control_device_serial.required' => 'شماره سریال دستگاه را وارد کنید.',
            'access_control_display_seconds.required' => 'مدت نمایش روی مانیتور را وارد کنید.',
            'access_control_display_seconds.min' => 'حداقل مدت نمایش ۱ ثانیه است.',
            'access_control_display_seconds.max' => 'حداکثر مدت نمایش ۱۰ ثانیه است.',
        ];
    }
}
