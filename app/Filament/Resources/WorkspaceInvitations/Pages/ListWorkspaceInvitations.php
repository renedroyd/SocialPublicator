<?php

namespace App\Filament\Resources\WorkspaceInvitations\Pages;

use App\Filament\Resources\WorkspaceInvitations\WorkspaceInvitationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkspaceInvitations extends ListRecords
{
    protected static string $resource = WorkspaceInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
