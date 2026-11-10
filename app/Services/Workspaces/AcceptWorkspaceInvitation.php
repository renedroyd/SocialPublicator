<?php

namespace App\Services\Workspaces;

use App\Models\User;
use App\Models\WorkspaceAuditLog;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptWorkspaceInvitation
{
    public function accept(User $user, string $token): WorkspaceMembership
    {
        return DB::transaction(function () use ($user, $token): WorkspaceMembership {
            $invitation = WorkspaceInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $invitation || ! $invitation->isUsable()) {
                throw ValidationException::withMessages([
                    'invitation' => 'La invitación no existe, venció o ya fue utilizada.',
                ]);
            }

            if (mb_strtolower($invitation->email) !== mb_strtolower($user->email)) {
                throw new AuthorizationException('La invitación fue emitida para otro correo electrónico.');
            }

            $membership = WorkspaceMembership::query()->firstOrCreate(
                [
                    'workspace_id' => $invitation->workspace_id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $invitation->role,
                    'permissions' => $invitation->permissions,
                    'status' => 'active',
                    'invited_by_user_id' => $invitation->invited_by_user_id,
                    'joined_at' => now(),
                ],
            );

            if (! $membership->isActive()) {
                $membership->update([
                    'role' => $invitation->role,
                    'permissions' => $invitation->permissions,
                    'status' => 'active',
                    'invited_by_user_id' => $invitation->invited_by_user_id,
                    'joined_at' => now(),
                ]);
            }

            $invitation->update(['accepted_at' => now()]);

            WorkspaceAuditLog::record(
                $invitation->workspace,
                'workspace.invitation.accepted',
                $user,
                $membership,
                ['invitation_id' => $invitation->id],
            );

            return $membership->fresh();
        });
    }
}
