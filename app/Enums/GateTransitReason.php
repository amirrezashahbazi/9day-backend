<?php

namespace App\Enums;

enum GateTransitReason: string
{
    case Allowed = 'allowed';
    case UnknownCard = 'unknown_card';
    case UserBlocked = 'user_blocked';
    case NoEnrollment = 'no_enrollment';
    case Exhausted = 'exhausted';
}
