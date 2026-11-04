<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Exhausted = 'exhausted';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';
}
