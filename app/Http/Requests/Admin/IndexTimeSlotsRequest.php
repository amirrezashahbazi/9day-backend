<?php

namespace App\Http\Requests\Admin;

use App\Enums\SlotPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTimeSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::enum(SlotPeriod::class)],
        ];
    }
}
