<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    use HasUuids;

    /**
     * Disable updated_at timestamp (audit logs are immutable).
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'org_id',
        'action',
        'entity_type',
        'entity_id',
        'changes',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'changes' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the organization associated with the action.
     */
    public function org(): BelongsTo
    {
        return $this->belongsTo(Org::class);
    }

    /**
     * Static helper to create an audit log entry.
     */
    public static function log(
        string $action,
        string $entityType,
        string $entityId,
        ?array $changes = null,
        ?array $metadata = null,
        ?string $userId = null,
        ?string $orgId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        /** @var User|null $authUser */
        $authUser = Auth::user();

        return self::create([
            'user_id' => $userId ?? $authUser?->id,
            'org_id' => $orgId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes,
            'metadata' => $metadata,
            'ip_address' => $ipAddress ?? request()?->ip(),
            'user_agent' => $userAgent ?? request()?->userAgent(),
        ]);
    }
}
