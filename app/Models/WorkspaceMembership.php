<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMembership extends Model
{
    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_COLLABORATOR = 'collaborator';

    public const ROLES = [
        self::ROLE_OWNER,
        self::ROLE_ADMIN,
        self::ROLE_EDITOR,
        self::ROLE_COLLABORATOR,
    ];

    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
        'permissions',
        'status',
        'invited_by_user_id',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'joined_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOwner(): bool
    {
        return $this->isActive() && $this->role === self::ROLE_OWNER;
    }

    public function canManageMembers(): bool
    {
        return $this->isActive() && in_array($this->role, [
            self::ROLE_OWNER,
            self::ROLE_ADMIN,
        ], true);
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->role === self::ROLE_OWNER) {
            return true;
        }

        if (is_array($this->permissions)) {
            return in_array($permission, $this->permissions, true);
        }

        $rolePermissions = [
            self::ROLE_ADMIN => ['*'],
            self::ROLE_EDITOR => [
                'publications.view',
                'publications.create',
                'publications.update',
                'publications.publish',
                'campaigns.view',
                'campaigns.create',
                'campaigns.update',
                'products.view',
                'destinations.view',
                'executions.view',
                'analytics.view',
            ],
            self::ROLE_COLLABORATOR => [
                'publications.view',
                'executions.view',
                'analytics.view',
            ],
        ];

        $granted = $rolePermissions[$this->role] ?? [];

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }
}
