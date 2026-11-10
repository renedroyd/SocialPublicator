<?php

namespace App\Console\Commands;

use App\Models\ExternalCatalogCampaign;
use App\Services\ExternalCatalogCampaignSelectionEngine;
use Illuminate\Console\Command;

class SelectExternalCatalogCampaigns extends Command
{
    protected $signature = 'social-publicator:select-campaign-items';

    protected $description = 'Selects eligible Dubai Shop items for active social campaigns without publishing them';

    public function handle(ExternalCatalogCampaignSelectionEngine $engine): int
    {
        $groups = 0;
        $items = 0;

        ExternalCatalogCampaign::query()
            ->active()
            ->orderBy('id')
            ->chunkById(100, function ($campaigns) use ($engine, &$groups, &$items): void {
                foreach ($campaigns as $campaign) {
                    $selected = $engine->selectNextGroup($campaign);

                    if ($selected->isNotEmpty()) {
                        $groups++;
                        $items += $selected->count();
                    }
                }
            });

        $this->info("Grupos planificados: {$groups}; artículos seleccionados: {$items}");

        return self::SUCCESS;
    }
}
