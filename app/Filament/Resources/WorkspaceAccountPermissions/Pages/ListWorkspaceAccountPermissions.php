<?php

namespace App\Filament\Resources\WorkspaceAccountPermissions\Pages;

use App\Filament\Resources\WorkspaceAccountPermissions\WorkspaceAccountPermissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkspaceAccountPermissions extends ListRecords
{
    protected static string $resource = WorkspaceAccountPermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
