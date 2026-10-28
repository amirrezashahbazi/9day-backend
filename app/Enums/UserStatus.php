<?php

namespace App\Enums;

enum UserStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Active = 'active';
    case Blocked = 'blocked';
}
