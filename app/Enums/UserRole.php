<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case User = 'user';
    case Athlete = 'athlete';

    /** @return list<self> */
    public static function members(): array
    {
        return [self::User, self::Athlete];
    }

    public function isMember(): bool
    {
        return in_array($this, self::members(), true);
    }
}
