<?php

namespace App\Services\DomainIntelligence\Support;

use RuntimeException;

/**
 * Strict SSRF protection for outbound requests made by the engine.
 *
 * Blocks loopback, private, link-local, reserved and cloud-metadata targets,
 * validates DNS results before any HTTP request, and re-validates every
 * redirect hop (mitigating DNS rebinding and redirect-to-internal attacks).
 */
class SsrfGuard
{
    private const BLOCKED_HOSTNAMES = [
        'localhost',
        'localhost.localdomain',
        'metadata.google.internal',
        'metadata.google.com',
        'metadata',
        'instance-data',
    ];

    /**
     * Assert a hostname is safe to contact. Resolves DNS and inspects every
     * returned address (IPv4 and IPv6).
     */
    public function assertHostSafe(string $host): void
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            throw new RuntimeException('Empty host.');
        }

        if (in_array($host, self::BLOCKED_HOSTNAMES, true) || str_ends_with($host, '.localhost')) {
            throw new RuntimeException('Private, local, and metadata hosts are not allowed.');
        }

        if (str_ends_with($host, '.internal') || str_ends_with($host, '.local')) {
            throw new RuntimeException('Internal hosts are not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->assertIpSafe($host);

            return;
        }

        foreach ($this->resolveIps($host) as $ip) {
            $this->assertIpSafe($ip);
        }
    }

    /**
     * Assert a URL is safe to request (scheme + host + resolved addresses).
     */
    public function assertUrlSafe(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Only http and https URLs are allowed.');
        }

        if (isset($parts['user'], $parts['pass'])) {
            throw new RuntimeException('Credentials in URLs are not allowed.');
        }

        $host = $parts['host'] ?? '';
        if ($host === '') {
            throw new RuntimeException('URL has no host.');
        }

        $this->assertHostSafe($host);
    }

    /**
     * Assert an IP is public. Covers IPv4, IPv6, IPv4-mapped IPv6 and the
     * cloud metadata ranges.
     */
    public function assertIpSafe(string $ip): void
    {
        // IPv4-mapped IPv6 (::ffff:127.0.0.1) must be evaluated as IPv4.
        $mapped = $this->unmapIpv4($ip);
        if ($mapped !== null) {
            $ip = $mapped;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new RuntimeException('Private, reserved, or internal addresses are not allowed.');
        }

        // Explicit cloud metadata endpoints (some are inside reserved space,
        // others are not covered by the flags above).
        $blocked = ['169.254.169.254', 'fd00:ec2::254', '100.100.100.200'];
        if (in_array($ip, $blocked, true)) {
            throw new RuntimeException('Cloud metadata endpoints are not allowed.');
        }
    }

    /**
     * @return array<int, string>
     */
    public function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private function unmapIpv4(string $ip): ?string
    {
        if (! str_contains($ip, ':')) {
            return null;
        }

        $binary = @inet_pton($ip);
        if ($binary === false || strlen($binary) !== 16) {
            return null;
        }

        if (str_starts_with($binary, "\0\0\0\0\0\0\0\0\0\0\xff\xff")) {
            $ipv4 = substr($binary, 12);

            return inet_ntop($ipv4) ?: null;
        }

        return null;
    }
}