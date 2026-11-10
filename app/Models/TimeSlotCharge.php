<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\SlotPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeSlotCharge extends Model
{
    protected $fillable = [
        'user_id',
        'time_slot_id',
        'sessions_count',
        'amount',
        'slot_label',
        'gender',
        'starts_at',
        'ends_at',
        'period',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sessions_count' => 'integer',
            'amount' => 'integer',
            'gender' => Gender::class,
            'period' => SlotPeriod::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
