<?php

namespace App\Models;

use App\Enums\KinshipRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserKinship extends Model
{
    protected $fillable = [
        'user_id',
        'relative_user_id',
        'relation',
    ];

    protected function casts(): array
    {
        return [
            'relation' => KinshipRelation::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'relative_user_id');
    }
}
