<?php

namespace App\Filament\Resources\WorkspaceMembers\Pages;

use App\Filament\Resources\WorkspaceMembers\WorkspaceMembershipResource;
use App\Models\WorkspaceMembership;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;

class CreateWorkspaceMembership extends CreateRecord
{
    protected static string $resource = WorkspaceMembershipResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $workspaceId = app(WorkspaceContext::class)->id();
        $currentMembership = auth()->user()?->activeWorkspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->first();

        if (! $currentMembership?->canManageMembers()) {
            throw new AuthorizationException();
        }

        if (($data['role'] ?? null) === WorkspaceMembership::ROLE_OWNER) {
            throw new AuthorizationException('No se puede asignar el rol de propietario desde esta pantalla.');
        }

        if (($data['role'] ?? null) === WorkspaceMembership::ROLE_ADMIN && ! $currentMembership->isOwner()) {
            throw new AuthorizationException('Solo el propietario puede asignar administradores.');
        }

        $data['workspace_id'] = $workspaceId;
        $data['invited_by_user_id'] = auth()->id();
        $data['joined_at'] = now();
        $data['status'] = $data['status'] ?? 'active';

        if (empty($data['permissions'])) {
            $data['permissions'] = null;
        }

        return $data;
    }
}
