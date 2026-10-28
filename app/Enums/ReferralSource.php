<?php

namespace App\Enums;

enum ReferralSource: string
{
    case Website = 'website';
    case Instagram = 'instagram';
    case Ads = 'ads';
    case Friends = 'friends';
    case Other = 'other';
}
