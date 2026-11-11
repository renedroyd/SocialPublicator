<?php

namespace App\Console\Commands;

use App\Services\CampaignPublicationDispatcher;
use App\Services\DubaiShopIntegrationManager;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Console\Command;
use Throwable;

class DispatchCampaignPublications extends Command
{
    protected $signature = 'social-publicator:dispatch-campaign-publications {--limit=100}';

    protected $description = 'Creates bot publications from enabled workspace campaign selections and triggers';

    public function handle(
        CampaignPublicationDispatcher $dispatcher,
        DubaiShopIntegrationManager $integrations,
        WorkspaceContext $context,
    ): int {
        $workspaceIds = $integrations->configuredWorkspaceIds(campaignsOnly: true);

        if ($workspaceIds === []) {
            $this->info('No workspace has Dubai Shop campaign dispatch enabled; no publications were queued.');

            return self::SUCCESS;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $totals = ['triggers' => 0, 'selections' => 0, 'skipped' => 0, 'blocked' => 0, 'errors' => 0];

        foreach ($workspaceIds as $workspaceId) {
            $context->set($workspaceId);

            try {
                if (! $integrations->campaignsEnabledForCurrentWorkspace()) {
                    continue;
                }

                $counts = $dispatcher->dispatchPending($limit);

                foreach ($totals as $key => $value) {
                    $totals[$key] += (int) ($counts[$key] ?? 0);
                }
            } catch (Throwable $exception) {
                $totals['errors']++;
                $this->error("Workspace {$workspaceId}: ".$exception->getMessage());
            } finally {
                $context->clear();
            }
        }

        $this->info(sprintf(
            'Avisos encolados: %d; grupos encolados: %d; omitidos: %d; bloqueados: %d; errores: %d',
            $totals['triggers'],
            $totals['selections'],
            $totals['skipped'],
            $totals['blocked'],
            $totals['errors'],
        ));

        return $totals['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
