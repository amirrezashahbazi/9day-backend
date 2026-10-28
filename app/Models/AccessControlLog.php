<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessControlLog extends Model
{
    protected $fillable = [
        'payload',
        'source_ip',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
