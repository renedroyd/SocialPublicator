<?php

namespace App\Filament\Resources\WorkspaceDestinationPermissions\Pages;

use App\Filament\Resources\WorkspaceDestinationPermissions\WorkspaceDestinationPermissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkspaceDestinationPermissions extends ListRecords
{
    protected static string $resource = WorkspaceDestinationPermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
