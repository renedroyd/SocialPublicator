<?php

namespace App\Filament\Resources\WorkspaceDubaiShopIntegrations\Pages;

use App\Filament\Resources\WorkspaceDubaiShopIntegrations\WorkspaceDubaiShopIntegrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkspaceDubaiShopIntegrations extends ListRecords
{
    protected static string $resource = WorkspaceDubaiShopIntegrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Configurar conexión'),
        ];
    }
}
