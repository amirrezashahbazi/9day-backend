<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseEnrollment extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'sessions_total',
        'sessions_remaining',
        'status',
        'renewed_from_id',
        'enrolled_by',
        'access_card_number',
        'activated_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'sessions_total' => 'integer',
            'sessions_remaining' => 'integer',
            'status' => EnrollmentStatus::class,
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function transits(): HasMany
    {
        return $this->hasMany(GateTransit::class, 'enrollment_id');
    }

    public function isActive(): bool
    {
        return $this->status === EnrollmentStatus::Active;
    }
}
