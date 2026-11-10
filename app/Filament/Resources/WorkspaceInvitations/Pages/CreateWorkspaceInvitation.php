<?php

namespace App\Filament\Resources\WorkspaceInvitations\Pages;

use App\Filament\Resources\WorkspaceInvitations\WorkspaceInvitationResource;
use App\Models\WorkspaceMembership;
use App\Services\Workspaces\WorkspaceContext;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

class CreateWorkspaceInvitation extends CreateRecord
{
    protected static string $resource = WorkspaceInvitationResource::class;

    protected ?string $plainToken = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $workspaceId = app(WorkspaceContext::class)->id();
        $membership = auth()->user()?->activeWorkspaceMemberships()
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($membership?->canManageMembers() !== true) {
            throw new AuthorizationException;
        }

        if (($data['role'] ?? null) === WorkspaceMembership::ROLE_ADMIN && $membership->isOwner() !== true) {
            throw new AuthorizationException('Solo el propietario puede invitar administradores.');
        }

        $this->plainToken = Str::random(64);

        $data['workspace_id'] = $workspaceId;
        $data['token_hash'] = hash('sha256', $this->plainToken);
        $data['invited_by_user_id'] = auth()->id();
        $data['expires_at'] = now()->addDays(7);

        if (empty($data['permissions'])) {
            $data['permissions'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $url = route('workspace-invitations.accept', ['token' => $this->plainToken]);

        Notification::make()
            ->title('Invitación creada')
            ->body('Comparte este enlace con el usuario invitado. Solo podrá utilizarse una vez y vence en 7 días: '.$url)
            ->persistent()
            ->success()
            ->send();
    }
}
