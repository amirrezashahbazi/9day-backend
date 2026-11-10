<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lane extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lane_type_id',
        'title',
        'fee',
        'min_people',
        'max_people',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'integer',
            'min_people' => 'integer',
            'max_people' => 'integer',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LaneType::class, 'lane_type_id');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(LaneRental::class);
    }
}
