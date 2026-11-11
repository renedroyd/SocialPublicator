<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected static function booted(): void
    {
        static::created(function (WorkspaceMembership $membership): void {
            WorkspaceAuditLog::record(
                $membership->workspace,
                'workspace.member.added',
                auth()->user() ?? $membership->inviter,
                $membership,
                ['role' => $membership->role],
            );
        });

        static::updated(function (WorkspaceMembership $membership): void {
            WorkspaceAuditLog::record(
                $membership->workspace,
                'workspace.member.updated',
                auth()->user(),
                $membership,
                ['changes' => $membership->getChanges()],
            );
        });

        static::deleted(function (WorkspaceMembership $membership): void {
            WorkspaceAuditLog::record(
                $membership->workspace,
                'workspace.member.removed',
                auth()->user(),
                $membership,
            );
        });
    }

    public function socialAccountPermissions(): HasMany
    {
        return $this->hasMany(WorkspaceMembershipSocialAccountPermission::class, 'membership_id');
    }

    public function destinationPermissions(): HasMany
    {
        return $this->hasMany(WorkspaceMembershipDestinationPermission::class, 'membership_id');
    }

    public function canUseSocialAccount(SocialAccount $account, string $permission = 'view'): bool
    {
        if (! $this->isActive() || (int) $this->workspace_id !== (int) $account->workspace_id) {
            return false;
        }

        if ($permission === 'publish' && ! $this->hasPermission('publications.publish')) {
            return false;
        }

        if ($this->isOwner() || $this->hasPermission('accounts.manage')) {
            return true;
        }

        if (! $this->hasPermission('accounts.view')) {
            return false;
        }

        if ($permission === 'manage') {
            return false;
        }

        $grant = $this->socialAccountPermissions()
            ->where('social_account_id', $account->id)
            ->first();

        return $grant !== null && (
            in_array('*', $grant->permissions ?? [], true)
            || in_array($permission, $grant->permissions ?? [], true)
        );
    }

    public function canPublishToDestination(string $destinationType, int $destinationId): bool
    {
        if (! $this->canUseDestination($destinationType, $destinationId, 'publish')) {
            return false;
        }

        $destinationClass = match ($destinationType) {
            WorkspaceMembershipDestinationPermission::DESTINATION_FACEBOOK_GROUP => FacebookGroup::class,
            WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP => WhatsAppGroup::class,
            WorkspaceMembershipDestinationPermission::DESTINATION_PUBLICATION => PublicationDestination::class,
            default => null,
        };

        if (! $destinationClass) {
            return false;
        }

        $destination = (new $destinationClass)->newQuery()->find($destinationId);

        if (! $destination) {
            return false;
        }

        if ($destination->social_account_id === null) {
            return ! SocialAccount::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $destination->workspace_id)
                ->exists();
        }

        $account = SocialAccount::query()->find($destination->social_account_id);

        if ($account === null) {
            return false;
        }

        $expectedAccount = match ($destinationType) {
            WorkspaceMembershipDestinationPermission::DESTINATION_WHATSAPP_GROUP => [
                SocialAccount::PLATFORM_WHATSAPP,
                SocialAccount::TYPE_WHATSAPP,
            ],
            WorkspaceMembershipDestinationPermission::DESTINATION_FACEBOOK_GROUP => [
                SocialAccount::PLATFORM_FACEBOOK,
                SocialAccount::TYPE_FACEBOOK_PROFILE,
            ],
            default => [$account->platform, $account->account_type],
        };

        if ([$account->platform, $account->account_type] !== $expectedAccount) {
            return false;
        }

        return $this->canUseSocialAccount($account, 'publish');
    }

    public function canUseDestination(string $destinationType, int $destinationId, string $permission = 'view'): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($permission === 'publish' && ! $this->hasPermission('publications.publish')) {
            return false;
        }

        if ($this->isOwner() || $this->hasPermission('destinations.manage')) {
            return true;
        }

        if (! $this->hasPermission('destinations.view')) {
            return false;
        }

        if ($permission === 'manage') {
            return false;
        }

        $grant = $this->destinationPermissions()
            ->where('destination_type', $destinationType)
            ->where('destination_id', $destinationId)
            ->first();

        return $grant !== null && (
            in_array('*', $grant->permissions ?? [], true)
            || in_array($permission, $grant->permissions ?? [], true)
        );
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
        return $this->hasPermission('members.manage');
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->role === self::ROLE_OWNER) {
            return true;
        }

        if (is_array($this->permissions) && $this->permissions !== []) {
            if (
                in_array('accounts.manage', $this->permissions, true)
                && in_array($permission, ['accounts.view', 'accounts.create', 'accounts.update', 'accounts.delete'], true)
            ) {
                return true;
            }

            if (
                in_array('destinations.manage', $this->permissions, true)
                && in_array($permission, ['destinations.view', 'destinations.create', 'destinations.update', 'destinations.delete'], true)
            ) {
                return true;
            }

            if (
                in_array('integrations.manage', $this->permissions, true)
                && in_array($permission, ['integrations.view', 'integrations.create', 'integrations.update', 'integrations.delete'], true)
            ) {
                return true;
            }

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
                'accounts.view',
                'destinations.view',
                'executions.view',
                'analytics.view',
            ],
            self::ROLE_COLLABORATOR => [
                'publications.view',
                'accounts.view',
                'destinations.view',
                'executions.view',
                'analytics.view',
            ],
        ];

        $granted = $rolePermissions[$this->role] ?? [];

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }
}
