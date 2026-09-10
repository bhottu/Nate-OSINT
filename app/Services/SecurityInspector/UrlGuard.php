<?php

namespace App\Services\SecurityInspector;

use RuntimeException;

class UrlGuard
{
    public function validate(string $url): string
    {
        $url = trim($url);
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Enter a valid HTTP or HTTPS URL.');
        }
        $parts = parse_url($url);
        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user'], $parts['pass'])) {
            throw new RuntimeException('Only public HTTP and HTTPS URLs are allowed.');
        }
        $host = strtolower($parts['host']);
        if ($this->blockedHost($host)) {
            throw new RuntimeException('Private, local, and metadata endpoints are not allowed.');
        }
        foreach ($this->resolve($host) as $ip) {
            if ($this->isPrivate($ip)) {
                throw new RuntimeException('The target resolves to a private or internal address.');
            }
        }

        return $url;
    }

    public function assertSafeRedirect(string $url): void
    {
        $this->validate($url);
    }

    private function blockedHost(string $host): bool
    {
        return $host === 'localhost' || str_ends_with($host, '.localhost') || $host === 'metadata.google.internal' || $host === 'metadata.google.com' || filter_var($host, FILTER_VALIDATE_IP) !== false && $this->isPrivate($host);
    }

    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $ips = [];
        foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($ips));
    }

    private function isPrivate(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
