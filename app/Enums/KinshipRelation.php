<?php

namespace App\Enums;

enum KinshipRelation: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Sister = 'sister';
    case Brother = 'brother';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $relation) => $relation->value, self::cases());
    }
}
