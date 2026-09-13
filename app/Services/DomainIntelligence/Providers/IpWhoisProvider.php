<?php

namespace App\Services\DomainIntelligence\Providers;

use App\Services\DomainIntelligence\Contracts\IpIntelligenceProviderInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * IP intelligence provider backed by the free ipwho.is endpoint.
 * Geolocation data is approximate by nature and is surfaced as such.
 */
class IpWhoisProvider implements IpIntelligenceProviderInterface
{
    public function lookup(string $ip): ?array
    {
        $base = rtrim((string) config('domain_intelligence.providers.ip.base_url', 'https://ipwho.is'), '/');

        try {
            $response = Http::timeout((int) config('domain_intelligence.timeouts.request', 12))
                ->connectTimeout((int) config('domain_intelligence.timeouts.connect', 5))
                ->get($base.'/'.$ip);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();
        if (! is_array($body) || ($body['success'] ?? true) === false) {
            return null;
        }

        $connection = $body['connection'] ?? [];

        return [
            'ip' => $body['ip'] ?? $ip,
            'version' => str_contains($ip, ':') ? 6 : 4,
            'asn' => isset($connection['asn']) ? 'AS'.$connection['asn'] : null,
            'asn_org' => $connection['org'] ?? null,
            'isp' => $connection['isp'] ?? null,
            'hosting' => $connection['domain'] ?? null,
            'country' => $body['country'] ?? null,
            'country_code' => $body['country_code'] ?? null,
            'region' => $body['region'] ?? null,
            'city' => $body['city'] ?? null,
            'network' => $body['type'] ?? null,
            'rir' => null,
            'source' => 'ipwho.is',
        ];
    }
}