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
            'access_control_display_ms' => ['required', 'integer', 'min:200', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'access_control_device_serial.required' => 'شماره سریال دستگاه را وارد کنید.',
            'access_control_display_ms.required' => 'مدت نمایش روی مانیتور را وارد کنید.',
            'access_control_display_ms.min' => 'حداقل مدت نمایش ۲۰۰ میلی‌ثانیه است.',
            'access_control_display_ms.max' => 'حداکثر مدت نمایش ۱۰٬۰۰۰ میلی‌ثانیه است.',
        ];
    }
}
