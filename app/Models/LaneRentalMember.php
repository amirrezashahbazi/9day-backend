<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaneRentalMember extends Model
{
    protected $fillable = [
        'lane_rental_id',
        'user_id',
        'created_by',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(LaneRental::class, 'lane_rental_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
