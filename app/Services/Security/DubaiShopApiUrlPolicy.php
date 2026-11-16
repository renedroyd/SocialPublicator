<?php

namespace App\Services\Security;

use Closure;
use RuntimeException;

class DubaiShopApiUrlPolicy
{
    public function __construct(private readonly ?Closure $resolver = null) {}

    /**
     * @return array{host: string, port: int, curl_resolve: array<int, string>}
     */
    public function resolve(string $url): array
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new RuntimeException('The Dubai Shop API URL is invalid.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = trim(strtolower((string) $parts['host']), '[]');

        if (! in_array($scheme, ['https', 'http'], true)
            || ($scheme !== 'https' && ! app()->environment(['local', 'testing']))) {
            throw new RuntimeException('Dubai Shop API connections must use HTTPS.');
        }

        if ($host === ''
            || str_ends_with($host, '.')
            || str_contains($host, '%')
            || $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.home')
            || str_ends_with($host, '.lan')) {
            throw new RuntimeException('Dubai Shop API hostnames must resolve to public internet addresses.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('The Dubai Shop API URL contains an invalid port.');
        }

        if ($scheme === 'https' && $port !== 443 && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Dubai Shop API connections must use the standard HTTPS port.');
        }

        if ($scheme === 'http' && $port !== 80 && ! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Dubai Shop API connections must use the standard HTTP port.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = [$host];
            $isIpLiteral = true;
        } elseif (app()->environment('testing') && str_ends_with($host, '.test')) {
            $addresses = [];
            $isIpLiteral = false;
        } else {
            if (! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/D', $host)) {
                throw new RuntimeException('The Dubai Shop API hostname is invalid.');
            }

            $addresses = $this->lookupAddresses($host);
            $isIpLiteral = false;
        }

        if ($addresses === [] && ! (app()->environment('testing') && str_ends_with($host, '.test'))) {
            throw new RuntimeException('The Dubai Shop API hostname did not resolve to a public IP address.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new RuntimeException('Dubai Shop API hostnames cannot resolve to private, local, or reserved IP addresses.');
            }
        }

        $resolve = [];

        if ($addresses !== [] && ! $isIpLiteral) {
            $address = $addresses[0];
            $formattedHost = str_contains($host, ':') ? '['.$host.']' : $host;
            $formattedAddress = str_contains($address, ':') ? '['.$address.']' : $address;
            $resolve[] = $formattedHost.':'.$port.':'.$formattedAddress;
        }

        return [
            'host' => $host,
            'port' => $port,
            'curl_resolve' => $resolve,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function lookupAddresses(string $host): array
    {
        if ($this->resolver !== null) {
            return array_values(array_unique(($this->resolver)($host)));
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            throw new RuntimeException('The Dubai Shop API hostname could not be resolved safely.');
        }

        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address) && $address !== '') {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    private function isPublicAddress(string $address): bool
    {
        $packed = @inet_pton($address);

        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            $address = inet_ntop(substr($packed, 12));

            if ($address === false) {
                return false;
            }
        }

        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
