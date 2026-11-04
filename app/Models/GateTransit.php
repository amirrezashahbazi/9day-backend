<?php

namespace App\Models;

use App\Enums\GateTransitReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GateTransit extends Model
{
    protected $fillable = [
        'user_id',
        'enrollment_id',
        'course_id',
        'access_control_log_id',
        'card_number',
        'allowed',
        'reason',
        'sessions_remaining_after',
        'course_title',
        'user_name',
        'source_ip',
    ];

    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
            'reason' => GateTransitReason::class,
            'sessions_remaining_after' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CourseEnrollment::class, 'enrollment_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function accessControlLog(): BelongsTo
    {
        return $this->belongsTo(AccessControlLog::class);
    }
}
