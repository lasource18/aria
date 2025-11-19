<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'phone',
        'password',
        'full_name',
        'preferences',
        'locale',
        'is_platform_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'is_platform_admin' => 'boolean',
        ];
    }

    /**
     * Get the organizations this user belongs to.
     */
    public function orgs(): BelongsToMany
    {
        return $this->belongsToMany(Org::class, 'org_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the org memberships for this user.
     */
    public function orgMemberships(): HasMany
    {
        return $this->hasMany(OrgMember::class);
    }

    /**
     * Check if user is platform admin.
     */
    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin;
    }

    /**
     * Check if user has role in organization.
     */
    public function hasRoleInOrg(Org $org, array $roles): bool
    {
        return $org->hasMemberWithRole($this, $roles);
    }

    /**
     * Get user's current organization from session or first org.
     */
    public function currentOrg(): ?Org
    {
        return $this->orgs()->first();
    }
}
