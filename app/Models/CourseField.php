<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueActiveName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseField extends Model
{
    use HasUniqueActiveName, SoftDeletes;

    protected $fillable = ['name'];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
