<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Course */
class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'course_type_id' => $this->course_type_id,
            'course_field_id' => $this->course_field_id,
            'course_level_id' => $this->course_level_id,
            'type' => $this->whenLoaded('type', fn () => [
                'id' => $this->type?->id,
                'name' => $this->type?->name,
            ]),
            'field' => $this->whenLoaded('field', fn () => [
                'id' => $this->field?->id,
                'name' => $this->field?->name,
            ]),
            'level' => $this->whenLoaded('level', fn () => [
                'id' => $this->level?->id,
                'name' => $this->level?->name,
            ]),
            'registration_from' => $this->registration_from?->toDateString(),
            'registration_to' => $this->registration_to?->toDateString(),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'gender' => $this->gender?->value,
            'schedules' => $this->whenLoaded('schedules', fn () => $this->schedulePayload()),
            'min_age' => $this->min_age,
            'max_age' => $this->max_age,
            'fee' => $this->fee,
            'capacity' => $this->capacity,
            'sessions_count' => $this->sessions_count,
            'enrollments_count' => $this->enrollments_count,
            'banners' => $this->whenLoaded('banners', fn () => $this->banners->map(fn ($banner) => [
                'id' => $banner->id,
                'url' => $banner->url(),
                'sort_order' => $banner->sort_order,
                'is_primary' => (bool) $banner->is_primary,
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return list<array{id: int, weekday: string, starts_at: string, ends_at: string}> */
    private function schedulePayload(): array
    {
        $order = ['sat' => 0, 'sun' => 1, 'mon' => 2, 'tue' => 3, 'wed' => 4, 'thu' => 5, 'fri' => 6];

        return $this->schedules
            ->sort(function ($left, $right) use ($order) {
                $day = ($order[$left->weekday] ?? 9) <=> ($order[$right->weekday] ?? 9);

                return $day !== 0 ? $day : strcmp((string) $left->starts_at, (string) $right->starts_at);
            })
            ->values()
            ->map(fn ($row) => [
                'id' => $row->id,
                'weekday' => $row->weekday,
                'starts_at' => substr((string) $row->starts_at, 0, 5),
                'ends_at' => substr((string) $row->ends_at, 0, 5),
            ])
            ->all();
    }
}
