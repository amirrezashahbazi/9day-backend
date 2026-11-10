<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\UserRole;

class AccessCatalog
{
    /** @return list<Permission> */
    public static function permissionsFor(UserRole $role): array
    {
        return match ($role) {
            UserRole::SuperAdmin => Permission::cases(),
            UserRole::Admin => [
                Permission::PanelAccess,
                Permission::UsersView,
                Permission::UsersCreate,
                Permission::UsersUpdate,
                Permission::ReportsView,
                Permission::CoursesView,
                Permission::CoursesManage,
                Permission::LanesManage,
            ],
            UserRole::LaneRenter => [
                Permission::LanesMembers,
            ],
            UserRole::User, UserRole::Athlete => [
                Permission::RegistrationManage,
            ],
        };
    }
}
