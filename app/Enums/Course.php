<?php

namespace App\Enums;

enum Course: string
{
    case Swimming = 'swimming';
    case WaterPolo = 'water_polo';
    case Diving = 'diving';
    case Private = 'private';
    case SemiPrivate = 'semi_private';
}
