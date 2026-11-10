<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceInvitation extends Model
{
    protected $fillable = [
        'workspace_id',
        'email',
        'role',
        'permissions',
        'token_hash',
        'invited_by_user_id',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    protected static function booted(): void
    {
        static::created(function (WorkspaceInvitation $invitation): void {
            WorkspaceAuditLog::record(
                $invitation->workspace,
                'workspace.invitation.created',
                auth()->user() ?? $invitation->inviter,
                $invitation,
                ['email' => $invitation->email, 'role' => $invitation->role],
            );
        });

        static::updated(function (WorkspaceInvitation $invitation): void {
            if ($invitation->wasChanged('revoked_at') && $invitation->revoked_at !== null) {
                WorkspaceAuditLog::record(
                    $invitation->workspace,
                    'workspace.invitation.revoked',
                    auth()->user(),
                    $invitation,
                );
            }
        });
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at?->isFuture() === true;
    }
}
