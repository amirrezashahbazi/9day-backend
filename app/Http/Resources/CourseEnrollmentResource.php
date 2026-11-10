<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CourseEnrollment */
class CourseEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'sessions_total' => $this->sessions_total,
            'sessions_remaining' => $this->sessions_remaining,
            'status' => $this->status?->value,
            'renewed_from_id' => $this->renewed_from_id,
            'access_card_number' => $this->access_card_number,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course?->id,
                'title' => $this->course?->title,
                'sessions_count' => $this->course?->sessions_count,
                'slot_starts_at' => is_string($this->course?->slot_starts_at) ? substr($this->course->slot_starts_at, 0, 5) : null,
                'slot_ends_at' => is_string($this->course?->slot_ends_at) ? substr($this->course->slot_ends_at, 0, 5) : null,
                'fee' => $this->course?->fee,
                'capacity' => $this->course?->capacity,
                'type' => $this->course?->relationLoaded('type') ? [
                    'id' => $this->course->type?->id,
                    'name' => $this->course->type?->name,
                ] : null,
                'field' => $this->course?->relationLoaded('field') ? [
                    'id' => $this->course->field?->id,
                    'name' => $this->course->field?->name,
                ] : null,
                'level' => $this->course?->relationLoaded('level') ? [
                    'id' => $this->course->level?->id,
                    'name' => $this->course->level?->name,
                ] : null,
            ]),
            'enrolled_by' => $this->whenLoaded('enrolledBy', fn () => $this->enrolledBy ? [
                'id' => $this->enrolledBy->id,
                'first_name' => $this->enrolledBy->first_name,
                'last_name' => $this->enrolledBy->last_name,
            ] : null),
        ];
    }
}
