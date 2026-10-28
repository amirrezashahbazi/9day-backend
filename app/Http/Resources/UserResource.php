<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['roles.permissions', 'permissions']);

        return [
            'id' => $this->id,
            'file_number' => $this->file_number,
            'mobile' => $this->mobile,
            'roles' => $this->roles->map(fn ($role) => $role->name instanceof \App\Enums\UserRole ? $role->name->value : (string) $role->name)->values(),
            'permissions' => $this->permissionNames(),
            'direct_permissions' => $this->permissions->map(fn ($permission) => $permission->name instanceof \App\Enums\Permission ? $permission->name->value : (string) $permission->name)->values(),
            'role' => $this->primaryRole(),
            'status' => $this->status->value,
            'registration_step' => $this->registration_step,
            'registration_completed_at' => $this->registration_completed_at?->toIso8601String(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'access_granted_at' => $this->access_granted_at?->toIso8601String(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'father_name' => $this->father_name,
            'issued_from' => $this->issued_from,
            'national_code' => $this->national_code,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'gender' => $this->gender?->value,
            'education_level' => $this->education_level?->value,
            'landline' => $this->landline,
            'emergency_phone_1' => $this->emergency_phone_1,
            'emergency_phone_2' => $this->emergency_phone_2,
            'address' => $this->address,
            'province_id' => $this->province_id,
            'province' => $this->province?->name,
            'city_id' => $this->city_id,
            'city' => $this->city?->name,
            'district_id' => $this->district_id,
            'district' => $this->district?->name,
            'course_swimming' => (bool) $this->course_swimming,
            'course_water_polo' => (bool) $this->course_water_polo,
            'course_diving' => (bool) $this->course_diving,
            'course_private' => (bool) $this->course_private,
            'course_semi_private' => (bool) $this->course_semi_private,
            'referral_source' => $this->referral_source?->value,
            'referral_other' => $this->referral_other,
            'has_illness' => $this->has_illness,
            'illness_skin' => (bool) $this->illness_skin,
            'illness_skin_description' => $this->illness_skin_description,
            'illness_respiratory' => (bool) $this->illness_respiratory,
            'illness_respiratory_description' => $this->illness_respiratory_description,
            'illness_cardiac' => (bool) $this->illness_cardiac,
            'illness_cardiac_description' => $this->illness_cardiac_description,
            'illness_other' => (bool) $this->illness_other,
            'illness_other_description' => $this->illness_other_description,
            'takes_medication' => $this->takes_medication,
            'medication' => $this->medication,
            'has_sport_history' => $this->has_sport_history,
            'previous_coach' => $this->previous_coach,
            'history_duration' => $this->history_duration,
            'previous_club' => $this->previous_club,
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signature_name' => $this->signature_name,
            'introduced_by' => $this->introduced_by,
            'introducer' => $this->relationLoaded('introducer') && $this->introducer ? [
                'id' => $this->introducer->id,
                'mobile' => $this->introducer->mobile,
                'first_name' => $this->introducer->first_name,
                'last_name' => $this->introducer->last_name,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function permissionNames(): array
    {
        return $this->permissions
            ->pluck('name')
            ->merge($this->roles->flatMap(fn ($role) => $role->permissions->pluck('name')))
            ->map(fn ($name) => $name instanceof \App\Enums\Permission ? $name->value : (string) $name)
            ->unique()
            ->values()
            ->all();
    }
}
