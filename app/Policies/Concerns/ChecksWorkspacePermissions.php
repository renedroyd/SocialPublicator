<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Services\Workspaces\WorkspaceContext;

trait ChecksWorkspacePermissions
{
    protected function allows(User $user, string $permission): bool
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        if (! $workspaceId) {
            return false;
        }

        return $user->activeWorkspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->first()?->hasPermission($permission) ?? false;
    }
}
