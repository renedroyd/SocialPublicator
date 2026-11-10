<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WorkspaceMembershipSocialAccountPermission extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'membership_id',
        'social_account_id',
        'permissions',
    ];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $grant): void {
            $permissions = $grant->permissions ?? [];

            if ($permissions === [] || array_diff($permissions, ['view', 'publish']) !== []) {
                throw new LogicException('Los permisos por cuenta solo pueden permitir consulta y publicación.');
            }

            if (in_array('publish', $permissions, true) && ! in_array('view', $permissions, true)) {
                $grant->permissions = [...$permissions, 'view'];
            }
        });

        static::created(function (self $grant): void {
            WorkspaceAuditLog::record(
                $grant->workspace,
                'social_account.permission.granted',
                auth()->user(),
                $grant,
                [
                    'membership_id' => $grant->membership_id,
                    'social_account_id' => $grant->social_account_id,
                    'permissions' => $grant->permissions,
                ],
            );
        });

        static::updated(function (self $grant): void {
            WorkspaceAuditLog::record(
                $grant->workspace,
                'social_account.permission.updated',
                auth()->user(),
                $grant,
                ['permissions' => $grant->permissions],
            );
        });

        static::deleted(function (self $grant): void {
            WorkspaceAuditLog::record(
                $grant->workspace,
                'social_account.permission.revoked',
                auth()->user(),
                $grant,
            );
        });
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMembership::class, 'membership_id');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
