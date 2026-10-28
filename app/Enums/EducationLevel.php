<?php

namespace App\Enums;

enum EducationLevel: string
{
    case Primary = 'primary';
    case LowerSecondary = 'lower_secondary';
    case HighSchool = 'high_school';
    case Associate = 'associate';
    case Bachelor = 'bachelor';
    case Master = 'master';
    case Doctorate = 'doctorate';
    case Other = 'other';
}
