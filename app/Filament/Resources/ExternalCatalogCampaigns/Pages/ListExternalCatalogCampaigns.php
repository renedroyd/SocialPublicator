<?php

namespace App\Filament\Resources\ExternalCatalogCampaigns\Pages;

use App\Filament\Resources\ExternalCatalogCampaigns\ExternalCatalogCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExternalCatalogCampaigns extends ListRecords
{
    protected static string $resource = ExternalCatalogCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Nueva campaña')),
        ];
    }
}
