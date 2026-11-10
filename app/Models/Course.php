<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_type_id',
        'course_field_id',
        'course_level_id',
        'title',
        'registration_from',
        'registration_to',
        'starts_on',
        'ends_on',
        'gender',
        'min_age',
        'max_age',
        'fee',
        'capacity',
        'sessions_count',
        'enrollments_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'registration_from' => 'date',
            'registration_to' => 'date',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'gender' => Gender::class,
            'min_age' => 'integer',
            'max_age' => 'integer',
            'fee' => 'integer',
            'capacity' => 'integer',
            'sessions_count' => 'integer',
            'enrollments_count' => 'integer',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(CourseSchedule::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CourseType::class, 'course_type_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CourseField::class, 'course_field_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(CourseLevel::class, 'course_level_id');
    }

    public function banners(): HasMany
    {
        return $this->hasMany(CourseBanner::class)->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
