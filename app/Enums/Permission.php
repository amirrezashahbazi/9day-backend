<?php

namespace App\Enums;

enum Permission: string
{
    case PanelAccess = 'panel.access';
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case ReportsView = 'reports.view';
    case RegistrationManage = 'registration.manage';
    case CoursesView = 'courses.view';
    case CoursesManage = 'courses.manage';
    case LanesManage = 'lanes.manage';
    case LanesMembers = 'lanes.members';
    case TimeSlotsManage = 'time_slots.manage';
}
