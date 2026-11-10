<?php

namespace App\Filament\Resources\WorkspaceMembers\Pages;

use App\Filament\Resources\WorkspaceMembers\WorkspaceMembershipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkspaceMemberships extends ListRecords
{
    protected static string $resource = WorkspaceMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
