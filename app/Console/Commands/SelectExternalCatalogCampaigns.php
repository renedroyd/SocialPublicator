<?php

namespace App\Console\Commands;

use App\Models\ExternalCatalogCampaign;
use App\Services\DubaiShopIntegrationManager;
use App\Services\ExternalCatalogCampaignSelectionEngine;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Console\Command;
use Throwable;

class SelectExternalCatalogCampaigns extends Command
{
    protected $signature = 'social-publicator:select-campaign-items';

    protected $description = 'Select eligible Dubai Shop items for enabled workspace campaigns';

    public function handle(
        ExternalCatalogCampaignSelectionEngine $engine,
        DubaiShopIntegrationManager $integrations,
        WorkspaceContext $context,
    ): int {
        $workspaceIds = $integrations->configuredWorkspaceIds(campaignsOnly: true);

        if ($workspaceIds === []) {
            $this->info('No workspace has Dubai Shop campaign dispatch enabled.');

            return self::SUCCESS;
        }

        $groups = 0;
        $items = 0;
        $failures = 0;

        foreach ($workspaceIds as $workspaceId) {
            $context->set($workspaceId);

            try {
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
            } catch (Throwable $exception) {
                $failures++;
                $this->error("Workspace {$workspaceId}: ".$exception->getMessage());
            } finally {
                $context->clear();
            }
        }

        $this->info("Grupos planificados: {$groups}; artículos seleccionados: {$items}");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
