<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DubaiShopApiClient
{
    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.dubai_shop.api_base_url'), '/');
        $token = (string) config('services.dubai_shop.api_token');

        if ($baseUrl === '' || $token === '') {
            throw new RuntimeException('Dubai Shop API URL and token must be configured.');
        }

        return Http::baseUrl($baseUrl.'/api/integrations/social-publicator/v1/')
            ->withToken($token)
            ->acceptJson()
            ->timeout((int) config('services.dubai_shop.timeout', 15));
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
