<?php

namespace App\Enums;

enum IllnessType: string
{
    case Skin = 'skin';
    case Respiratory = 'respiratory';
    case Cardiac = 'cardiac';
    case Other = 'other';
}
