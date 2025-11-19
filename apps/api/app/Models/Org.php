<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Org extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'country_code',
        'kyb_data',
        'payout_channel',
        'payout_identifier',
        'verified',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kyb_data' => 'array',
            'verified' => 'boolean',
        ];
    }

    /**
     * Boot the model and auto-generate slug.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($org) {
            if (empty($org->slug)) {
                $org->slug = Str::slug($org->name);

                // Ensure unique slug
                $count = 1;
                while (static::where('slug', $org->slug)->exists()) {
                    $org->slug = Str::slug($org->name) . '-' . $count;
                    $count++;
                }
            }
        });
    }

    /**
     * Get the users that belong to this organization.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'org_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the members of this organization.
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrgMember::class);
    }

    /**
     * Check if user is a member of this organization.
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if user has specific role(s) in this organization.
     */
    public function hasMemberWithRole(User $user, array $roles): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->whereIn('role', $roles)
            ->exists();
    }

    /**
     * Get the role of a user in this organization.
     */
    public function getMemberRole(User $user): ?string
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->value('role');
    }

    /**
     * Add a member to this organization.
     */
    public function addMember(User $user, string $role): OrgMember
    {
        return $this->members()->create([
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    /**
     * Remove a member from this organization.
     */
    public function removeMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->delete() > 0;
    }

    /**
     * Update a member's role in this organization.
     */
    public function updateMemberRole(User $user, string $role): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->update(['role' => $role]) > 0;
    }

    /**
     * Check if this is the last owner.
     */
    public function isLastOwner(User $user): bool
    {
        $ownerCount = $this->members()->where('role', 'owner')->count();
        $isOwner = $this->hasMemberWithRole($user, ['owner']);

        return $ownerCount === 1 && $isOwner;
    }
}
