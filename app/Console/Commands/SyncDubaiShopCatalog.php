<?php

namespace App\Console\Commands;

use App\Services\ExternalCatalogSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncDubaiShopCatalog extends Command
{
    protected $signature = 'social-publicator:sync-dubai-catalog';

    protected $description = 'Synchronize and reconcile the external catalog from Dubai Shop';

    public function handle(ExternalCatalogSyncService $syncService): int
    {
        try {
            $result = $syncService->syncAll();
        } catch (Throwable $exception) {
            $this->error('Dubai Shop catalog synchronization failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Dubai Shop catalog synchronization completed.');
        $this->line('Items synchronized: '.$result['items_synced']);
        $this->line('Eligible warehouses synchronized: '.$result['warehouses_synced']);

        return self::SUCCESS;
    }
}
