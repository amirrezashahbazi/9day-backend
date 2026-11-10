<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseSchedule extends Model
{
    protected $fillable = [
        'course_id',
        'weekday',
        'starts_at',
        'ends_at',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
