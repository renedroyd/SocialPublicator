<?php

namespace App\Filament\Resources\WorkspaceMembers\Pages;

use App\Filament\Resources\WorkspaceMembers\WorkspaceMembershipResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkspaceMembership extends EditRecord
{
    protected static string $resource = WorkspaceMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => WorkspaceMembershipResource::canDelete($this->record)),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['workspace_id'], $data['user_id'], $data['invited_by_user_id']);

        if (empty($data['permissions'])) {
            $data['permissions'] = null;
        }

        return $data;
    }
}
