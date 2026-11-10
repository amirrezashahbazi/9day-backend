<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimeSlotChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'time_slot_id' => ['required', 'integer', Rule::exists('time_slots', 'id')],
            'sessions_count' => ['required', 'integer', 'min:1', 'max:500'],
            'amount' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'time_slot_id.required' => 'سانس را انتخاب کنید.',
            'time_slot_id.exists' => 'سانس انتخاب‌شده پیدا نشد.',
            'sessions_count.required' => 'تعداد جلسه الزامی است.',
            'sessions_count.min' => 'تعداد جلسه حداقل ۱ است.',
            'amount.required' => 'مبلغ شارژ الزامی است.',
        ];
    }
}
