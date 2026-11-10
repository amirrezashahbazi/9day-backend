<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TimeSlotCharge */
class TimeSlotChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'time_slot_id' => $this->time_slot_id,
            'sessions_count' => $this->sessions_count,
            'amount' => $this->amount,
            'slot_label' => $this->slot_label,
            'gender' => $this->gender?->value,
            'starts_at' => is_string($this->starts_at) ? substr($this->starts_at, 0, 5) : null,
            'ends_at' => is_string($this->ends_at) ? substr($this->ends_at, 0, 5) : null,
            'period' => $this->period?->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $user ? [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'mobile' => $user->mobile,
                'file_number' => $user->file_number,
            ] : null,
        ];
    }
}
