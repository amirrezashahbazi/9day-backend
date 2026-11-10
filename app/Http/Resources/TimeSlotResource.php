<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TimeSlot */
class TimeSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gender' => $this->gender?->value,
            'starts_at' => self::clock($this->starts_at),
            'ends_at' => self::clock($this->ends_at),
            'period' => $this->period?->value,
            'fee' => $this->fee,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private static function clock(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return substr($value, 0, 5);
    }
}
