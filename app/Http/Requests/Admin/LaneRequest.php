<?php

namespace App\Http\Requests\Admin;

use App\Models\LaneType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'lane_type_id' => ['nullable', 'integer', Rule::exists('lane_types', 'id')->whereNull('deleted_at'), 'required_without:type_name'],
            'type_name' => ['nullable', 'string', 'max:80', 'required_without:lane_type_id'],
            'fee' => ['required', 'integer', 'min:0'],
            'min_people' => ['required', 'integer', 'min:1', 'max:500'],
            'max_people' => ['required', 'integer', 'min:1', 'max:500', 'gte:min_people'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'عنوان لاین را وارد کنید.',
            'lane_type_id.required_without' => 'نوع لاین را انتخاب کنید.',
            'type_name.required_without' => 'نام نوع لاین را وارد کنید.',
            'fee.required' => 'مبلغ را وارد کنید.',
            'min_people.required' => 'حداقل نفرات را وارد کنید.',
            'max_people.required' => 'حداکثر نفرات را وارد کنید.',
            'max_people.gte' => 'حداکثر نفرات نباید از حداقل کمتر باشد.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $typeName = trim((string) $this->input('type_name', ''));
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'type_name' => $typeName === '' ? null : $typeName,
            'lane_type_id' => $this->filled('lane_type_id') ? $this->input('lane_type_id') : null,
        ]);
    }

    public function typeId(): int
    {
        $name = $this->validated('type_name');
        if (is_string($name) && $name !== '') {
            return (int) LaneType::query()->firstOrCreate(['name' => $name])->id;
        }

        return (int) $this->validated('lane_type_id');
    }
}
