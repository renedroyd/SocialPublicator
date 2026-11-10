<?php

namespace App\Filament\Resources\Concerns;

use App\Models\WorkspaceMembership;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesWorkspaceResources
{
    protected static function workspacePermissionPrefix(): string
    {
        return '';
    }

    protected static function currentWorkspaceMembership(): ?WorkspaceMembership
    {
        $user = auth()->user();
        $workspaceId = app(WorkspaceContext::class)->id();

        if (! $user || ! $workspaceId) {
            return null;
        }

        return $user->activeWorkspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->first();
    }

    public static function canViewAny(): bool
    {
        return static::currentWorkspaceMembership()?->hasPermission(
            static::workspacePermissionPrefix().'.view',
        ) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::currentWorkspaceMembership()?->hasPermission(
            static::workspacePermissionPrefix().'.view',
        ) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::currentWorkspaceMembership()?->hasPermission(
            static::workspacePermissionPrefix().'.create',
        ) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::currentWorkspaceMembership()?->hasPermission(
            static::workspacePermissionPrefix().'.update',
        ) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::currentWorkspaceMembership()?->hasPermission(
            static::workspacePermissionPrefix().'.delete',
        ) ?? false;
    }
}
