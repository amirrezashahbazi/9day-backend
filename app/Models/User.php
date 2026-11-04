<?php

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\ReferralSource;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'file_number',
        'mobile',
        'access_card_number',
        'access_card_enrolled_at',
        'password',
        'status',
        'registration_step',
        'phone_verified_at',
        'registration_completed_at',
        'access_granted_at',
        'introduced_by',
        'first_name',
        'last_name',
        'father_name',
        'issued_from',
        'national_code',
        'birth_date',
        'gender',
        'education_level',
        'landline',
        'emergency_phone_1',
        'emergency_phone_2',
        'address',
        'province_id',
        'city_id',
        'district_id',
        'course_swimming',
        'course_water_polo',
        'course_diving',
        'course_private',
        'course_semi_private',
        'referral_source',
        'referral_other',
        'has_illness',
        'illness_skin',
        'illness_skin_description',
        'illness_respiratory',
        'illness_respiratory_description',
        'illness_cardiac',
        'illness_cardiac_description',
        'illness_other',
        'illness_other_description',
        'takes_medication',
        'medication',
        'has_sport_history',
        'previous_coach',
        'history_duration',
        'previous_club',
        'signed_at',
        'signature_name',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->file_number)) {
                $user->file_number = static::nextFileNumber();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'gender' => Gender::class,
            'education_level' => EducationLevel::class,
            'referral_source' => ReferralSource::class,
            'registration_step' => 'integer',
            'access_card_enrolled_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'registration_completed_at' => 'datetime',
            'access_granted_at' => 'datetime',
            'birth_date' => 'date:Y-m-d',
            'signed_at' => 'datetime',
            'course_swimming' => 'boolean',
            'course_water_polo' => 'boolean',
            'course_diving' => 'boolean',
            'course_private' => 'boolean',
            'course_semi_private' => 'boolean',
            'has_illness' => 'boolean',
            'illness_skin' => 'boolean',
            'illness_respiratory' => 'boolean',
            'illness_cardiac' => 'boolean',
            'illness_other' => 'boolean',
            'takes_medication' => 'boolean',
            'has_sport_history' => 'boolean',
        ];
    }

    public function introducer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'introduced_by');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function syncRoles(array $roles): void
    {
        $names = collect($roles)
            ->map(fn (UserRole|string $role) => $role instanceof UserRole ? $role->value : $role)
            ->all();

        $ids = Role::query()->whereIn('name', $names)->pluck('id');
        $this->roles()->sync($ids);
        $this->unsetRelation('roles');
    }

    public function syncPermissions(array $permissions): void
    {
        $ids = Permission::query()->whereIn('name', $permissions)->pluck('id');
        $this->permissions()->sync($ids);
        $this->unsetRelation('permissions');
    }

    public function hasRole(UserRole|string $role): bool
    {
        $name = $role instanceof UserRole ? $role->value : $role;

        return $this->roles()->where('roles.name', $name)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        $names = collect($roles)
            ->map(fn (UserRole|string $role) => $role instanceof UserRole ? $role->value : $role)
            ->all();

        return $this->roles()->whereIn('roles.name', $names)->exists();
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return Permission::query()
            ->whereIn('permissions.name', $permissions)
            ->where(function ($query) {
                $query->whereHas('users', fn ($users) => $users->where('users.id', $this->id))
                    ->orWhereHas('roles.users', fn ($users) => $users->where('users.id', $this->id));
            })
            ->exists();
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole([UserRole::Admin, UserRole::SuperAdmin]);
    }

    public function isMember(): bool
    {
        return $this->hasAnyRole(UserRole::members());
    }

    public function primaryRole(): ?string
    {
        $this->loadMissing('roles');
        $owned = $this->roles->map(fn (Role $role) => $role->name instanceof UserRole ? $role->name->value : (string) $role->name);

        foreach ([UserRole::SuperAdmin, UserRole::Admin, UserRole::Athlete, UserRole::User] as $role) {
            if ($owned->contains($role->value)) {
                return $role->value;
            }
        }

        return $owned->first();
    }

    public function scopeMembers(Builder $query): Builder
    {
        $names = array_map(fn (UserRole $role) => $role->value, UserRole::members());

        return $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('roles.name', $names));
    }

    public function scopeWithRole(Builder $query, UserRole|string $role): Builder
    {
        $name = $role instanceof UserRole ? $role->value : $role;

        return $query->whereHas('roles', fn (Builder $roles) => $roles->where('roles.name', $name));
    }

    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        return $query->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('roles.name', UserRole::SuperAdmin->value));
    }

    public static function nextFileNumber(): string
    {
        $next = DB::selectOne("select nextval('file_number_seq') as n");

        return '9D-'.str_pad((string) $next->n, 5, '0', STR_PAD_LEFT);
    }
}
