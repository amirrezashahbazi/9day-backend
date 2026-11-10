<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesCourseSchedule
{
    /** @return array<string, mixed> */
    protected function courseScheduleRules(): array
    {
        return [
            'schedules' => ['nullable', 'array', 'max:80'],
            'schedules.*.weekday' => ['required', Rule::in(['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'])],
            'schedules.*.starts_at' => ['required', 'date_format:H:i'],
            'schedules.*.ends_at' => ['required', 'date_format:H:i'],
        ];
    }

    /** @return array<string, string> */
    protected function courseScheduleMessages(): array
    {
        return [
            'schedules.max' => 'حداکثر ۸۰ بازه برای یک دوره مجاز است.',
            'schedules.*.weekday.required' => 'روز هفته را انتخاب کنید.',
            'schedules.*.weekday.in' => 'روز هفته نامعتبر است.',
            'schedules.*.starts_at.required' => 'ساعت شروع را وارد کنید.',
            'schedules.*.starts_at.date_format' => 'ساعت شروع معتبر نیست.',
            'schedules.*.ends_at.required' => 'ساعت پایان را وارد کنید.',
            'schedules.*.ends_at.date_format' => 'ساعت پایان معتبر نیست.',
        ];
    }

    /** @return array{gender: ?string, schedules: list<array{weekday: string, starts_at: string, ends_at: string}>} */
    protected function preparedCourseSchedule(): array
    {
        $raw = $this->input('schedules', []);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        $schedules = [];
        foreach ((array) $raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $schedules[] = [
                'weekday' => isset($row['weekday']) ? (string) $row['weekday'] : '',
                'starts_at' => substr((string) ($row['starts_at'] ?? ''), 0, 5),
                'ends_at' => substr((string) ($row['ends_at'] ?? ''), 0, 5),
            ];
        }

        $gender = $this->input('gender');

        return [
            'gender' => ($gender === '' || $gender === null) ? null : (string) $gender,
            'schedules' => $schedules,
        ];
    }

    protected function assertScheduleOrder(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('schedules', []) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $start = (string) ($row['starts_at'] ?? '');
                $end = (string) ($row['ends_at'] ?? '');
                if ($start !== '' && $end !== '' && $end <= $start) {
                    $validator->errors()->add("schedules.{$index}.ends_at", 'ساعت پایان باید بعد از شروع باشد.');
                }
            }
        });
    }
}
