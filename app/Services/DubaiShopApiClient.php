<?php

namespace App\\Services;

use App\\Services\\Security\\DubaiShopApiUrlPolicy;
use Illuminate\\Http\\Client\\PendingRequest;
use Illuminate\\Support\\Facades\\Http;
use RuntimeException;

class DubaiShopApiClient
{
    private function request(): PendingRequest
    {
        $settings = app(DubaiShopIntegrationManager::class)->currentSettings();
        $endpoint = app(DubaiShopApiUrlPolicy::class)->resolve((string) $settings['api_base_url']);

        $options = [
            'allow_redirects' => false,
        ];

        if ($endpoint['curl_resolve'] !== []) {
            if (! defined('CURLOPT_RESOLVE')) {
                throw new RuntimeException('The cURL extension is required for secure Dubai Shop DNS pinning.');
            }

            $options['curl'] = [
                CURLOPT_RESOLVE => $endpoint['curl_resolve'],
            ];
        }

        $baseUrl = rtrim((string) $settings['api_base_url'], '/');

        return Http::withOptions($options)
            ->baseUrl($baseUrl.'/api/integrations/social-publicator/v1/')
            ->withToken((string) $settings['api_token'])
            ->acceptJson()
            ->timeout((int) $settings['timeout']);
    }

    public function identity(): array
    {
        return $this->request()->get('identity')->throw()->json();
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
