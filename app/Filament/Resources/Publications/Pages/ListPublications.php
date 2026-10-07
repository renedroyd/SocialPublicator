<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Filament\Resources\Publications\PublicationsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPublications extends ListRecords
{
    protected static string $resource = PublicationsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva publicación'),
        ];
    }
}
