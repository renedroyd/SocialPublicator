<?php

namespace App\Filament\Resources\BotAgents\Pages;

use App\Filament\Resources\BotAgents\BotAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBotAgents extends ListRecords
{
    protected static string $resource = BotAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
