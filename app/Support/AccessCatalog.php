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
                Permission::UsersUpdate,
                Permission::ReportsView,
                Permission::CoursesView,
                Permission::CoursesManage,
                Permission::LanesManage,
                Permission::TimeSlotsManage,
            ],
            UserRole::LaneRenter => [
                Permission::LanesMembers,
            ],
            UserRole::User, UserRole::Athlete => [
                Permission::RegistrationManage,
            ],
        };
    }

    /** @return array<string, string> */
    public static function staffPermissionLabels(): array
    {
        return [
            Permission::UsersView->value => 'فهرست کاربران',
            Permission::UsersCreate->value => 'تعریف کاربر',
            Permission::UsersUpdate->value => 'تغییر وضعیت کاربر',
            Permission::TimeSlotsManage->value => 'سانس‌ها',
            Permission::LanesManage->value => 'اجاره لاین',
            Permission::ReportsView->value => 'گزارش‌ها',
            Permission::CoursesView->value => 'مشاهده دوره‌ها',
            Permission::CoursesManage->value => 'تعریف دوره',
        ];
    }
}
