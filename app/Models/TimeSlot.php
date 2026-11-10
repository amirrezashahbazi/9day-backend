<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\SlotPeriod;
use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    protected $fillable = [
        'gender',
        'starts_at',
        'ends_at',
        'period',
        'fee',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'period' => SlotPeriod::class,
            'fee' => 'integer',
        ];
    }
}
