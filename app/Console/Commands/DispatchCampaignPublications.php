<?php

namespace App\\Console\\Commands;

use App\\Services\\CampaignPublicationDispatcher;
use Illuminate\\Console\\Command;

class DispatchCampaignPublications extends Command
{
    protected $signature = 'social-publicator:dispatch-campaign-publications {--limit=100}';

    protected $description = 'Creates bot publications from pending Dubai Shop campaign selections and triggers';

    public function handle(CampaignPublicationDispatcher $dispatcher): int
    {
        if (! filled(config('services.dubai_shop.api_base_url')) || ! filled(config('services.dubai_shop.api_token'))) {
            $this->warn('Dubai Shop API is not configured; campaign dispatch was skipped.');

            return self::SUCCESS;
        }

        $counts = $dispatcher->dispatchPending(max(1, min(500, (int) $this->option('limit'))));

        $this->info(sprintf(
            'Avisos encolados: %d; grupos encolados: %d; omitidos: %d; bloqueados: %d; errores: %d',
            $counts['triggers'],
            $counts['selections'],
            $counts['skipped'],
            $counts['blocked'],
            $counts['errors'],
        ));

        return $counts['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
