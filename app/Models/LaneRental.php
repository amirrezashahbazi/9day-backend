<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaneRental extends Model
{
    protected $fillable = [
        'lane_id',
        'renter_user_id',
        'students_count',
        'sessions_total',
        'sessions_remaining',
        'access_card_number',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'students_count' => 'integer',
            'sessions_total' => 'integer',
            'sessions_remaining' => 'integer',
        ];
    }

    public function lane(): BelongsTo
    {
        return $this->belongsTo(Lane::class);
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renter_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(LaneRentalMember::class);
    }
}
