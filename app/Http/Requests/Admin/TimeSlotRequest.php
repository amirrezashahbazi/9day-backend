<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Enums\SlotPeriod;
use App\Models\TimeSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimeSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gender' => ['required', Rule::enum(Gender::class)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'period' => ['required', Rule::enum(SlotPeriod::class)],
            'fee' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'gender.required' => 'جنسیت سانس را انتخاب کنید.',
            'starts_at.required' => 'ساعت شروع سانس الزامی است.',
            'starts_at.date_format' => 'ساعت شروع سانس معتبر نیست.',
            'ends_at.required' => 'ساعت پایان سانس الزامی است.',
            'ends_at.date_format' => 'ساعت پایان سانس معتبر نیست.',
            'ends_at.after' => 'ساعت پایان سانس باید بعد از ساعت شروع باشد.',
            'period.required' => 'صبح یا ظهر بودن سانس را مشخص کنید.',
            'fee.required' => 'هزینه سانس الزامی است.',
            'starts_at.unique' => 'این سانس قبلاً ثبت شده است.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ignore = $this->route('timeSlot');
            $ignoreId = $ignore instanceof TimeSlot ? $ignore->id : (is_numeric($ignore) ? (int) $ignore : null);

            $exists = TimeSlot::query()
                ->where('gender', $this->input('gender'))
                ->whereTime('starts_at', $this->input('starts_at'))
                ->whereTime('ends_at', $this->input('ends_at'))
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();

            if ($exists) {
                $validator->errors()->add('starts_at', 'این سانس قبلاً ثبت شده است.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $times = [];
        foreach (['starts_at', 'ends_at'] as $key) {
            $value = $this->input($key);
            if (is_string($value) && preg_match('/^\d{2}:\d{2}/', $value)) {
                $value = substr($value, 0, 5);
            }
            $times[$key] = ($value === '' || $value === null) ? null : $value;
        }

        $this->merge($times);
    }
}
