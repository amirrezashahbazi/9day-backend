<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaneType extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

    public function lanes(): HasMany
    {
        return $this->hasMany(Lane::class);
    }
}
