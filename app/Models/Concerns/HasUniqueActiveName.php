<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasUniqueActiveName
{
    public function scopeActiveNamed(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }
}
