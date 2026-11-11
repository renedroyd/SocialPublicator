<?php

namespace App\Console\Commands;

use App\Services\ExternalCatalogSyncService;
use App\Services\DubaiShopIntegrationManager;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Console\Command;
use Throwable;

class SyncDubaiShopCatalog extends Command
{
    protected $signature = 'social-publicator:sync-dubai-catalog';

    protected $description = 'Synchronize enabled workspace catalogs from Dubai Shop';

    public function handle(
        ExternalCatalogSyncService $syncService,
        DubaiShopIntegrationManager $integrations,
        WorkspaceContext $context,
    ): int {
        $workspaceIds = $integrations->configuredWorkspaceIds();

        if ($workspaceIds === []) {
            $this->info('No enabled Dubai Shop workspace integrations; independent workspaces are unchanged.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($workspaceIds as $workspaceId) {
            $context->set($workspaceId);

            try {
                $result = $syncService->syncAll();
                $this->info("Workspace {$workspaceId}: catálogo sincronizado.");
                $this->line('Artículos: '.$result['items_synced'].'; almacenes: '.$result['warehouses_synced']);
            } catch (Throwable $exception) {
                $failures++;
                $this->error("Workspace {$workspaceId}: falló la sincronización: ".$exception->getMessage());
            } finally {
                $context->clear();
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
