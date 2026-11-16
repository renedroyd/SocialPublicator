<?php

namespace Tests\Unit;

use App\Services\Security\DubaiShopApiUrlPolicy;
use RuntimeException;
use Tests\TestCase;

class DubaiShopApiUrlPolicyTest extends TestCase
{
    public function test_it_rejects_localhost_and_private_ip_literals(): void
    {
        $policy = new DubaiShopApiUrlPolicy();

        foreach ([
            'https://localhost',
            'https://127.0.0.1',
            'https://10.0.0.8',
            'https://172.16.0.10',
            'https://192.168.1.20',
            'https://169.254.169.254',
            'https://[::1]',
            'https://[fc00::1]',
            'https://[fe80::1]',
            'https://[::ffff:127.0.0.1]',
        ] as $url) {
            try {
                $policy->resolve($url);
                $this->fail("Expected {$url} to be rejected.");
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_it_rejects_hostnames_that_resolve_to_any_private_or_reserved_address(): void
    {
        $policy = new DubaiShopApiUrlPolicy(
            fn (string $host): array => ['93.184.216.34', '10.1.2.3'],
        );

        $this->expectException(RuntimeException::class);
        $policy->resolve('https://mixed-dns.example.com');
    }

    public function test_it_pins_a_public_dns_answer_to_the_outbound_connection(): void
    {
        $policy = new DubaiShopApiUrlPolicy(
            fn (string $host): array => ['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946'],
        );

        $result = $policy->resolve('https://catalog.example.com');

        $this->assertSame('catalog.example.com', $result['host']);
        $this->assertSame(443, $result['port']);
        $this->assertSame(['catalog.example.com:443:93.184.216.34,[2606:2800:220:1:248:1893:25c8:1946]'], $result['curl_resolve']);
    }

    public function test_it_rejects_unresolvable_hostnames(): void
    {
        $unresolvable = new DubaiShopApiUrlPolicy(fn (string $host): array => []);

        $this->expectException(RuntimeException::class);
        $unresolvable->resolve('https://missing.example.com');
    }

    public function test_it_rejects_urls_with_credentials_queries_or_fragments(): void
    {
        $policy = new DubaiShopApiUrlPolicy();

        foreach ([
            'https://user:password@example.com',
            'https://example.com?target=internal',
            'https://example.com#fragment',
            'file:///etc/passwd',
        ] as $url) {
            try {
                $policy->resolve($url);
                $this->fail("Expected {$url} to be rejected.");
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_it_allows_reserved_test_domains_only_in_the_testing_environment(): void
    {
        $result = app(DubaiShopApiUrlPolicy::class)->resolve('https://first-shop.example.test');

        $this->assertSame('first-shop.example.test', $result['host']);
        $this->assertSame([], $result['curl_resolve']);
    }

    public function test_it_rejects_fully_qualified_hostnames_with_trailing_dots_to_keep_dns_pinning_exact(): void
    {
        $policy = new DubaiShopApiUrlPolicy();

        $this->expectException(RuntimeException::class);
        $policy->resolve('https://catalog.example.com.');
    }

    public function test_public_ip_literals_are_used_directly_without_dns_resolution(): void
    {
        $policy = new DubaiShopApiUrlPolicy();

        $result = $policy->resolve('https://93.184.216.34');

        $this->assertSame('93.184.216.34', $result['host']);
        $this->assertSame([], $result['curl_resolve']);
    }

}
