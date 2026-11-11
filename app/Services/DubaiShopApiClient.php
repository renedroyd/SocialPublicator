<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class DubaiShopApiClient
{
    private function request(): PendingRequest
    {
        $settings = app(DubaiShopIntegrationManager::class)->currentSettings();
        $baseUrl = rtrim((string) $settings['api_base_url'], '/');

        return Http::baseUrl($baseUrl.'/api/integrations/social-publicator/v1/')
            ->withToken((string) $settings['api_token'])
            ->acceptJson()
            ->timeout((int) $settings['timeout']);
    }

    public function items(?string $cursor = null): array
    {
        $query = ['limit' => 100];

        if ($cursor !== null && $cursor !== '') {
            $query['cursor'] = $cursor;
        }

        return $this->request()->get('items', $query)->throw()->json();
    }

    public function item(string $externalId): ?array
    {
        $response = $this->request()->get('items/'.rawurlencode($externalId));

        if ($response->status() === 404) {
            return null;
        }

        return $response->throw()->json('data');
    }

    public function warehouses(): array
    {
        return $this->request()->get('warehouses')->throw()->json('data') ?? [];
    }
}
