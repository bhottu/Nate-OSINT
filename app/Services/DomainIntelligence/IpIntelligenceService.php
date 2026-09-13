<?php

namespace App\Services\DomainIntelligence;

use App\Services\DomainIntelligence\Contracts\DnsProviderInterface;
use App\Services\DomainIntelligence\Contracts\IpIntelligenceProviderInterface;
use Illuminate\Support\Facades\Cache;

/**
 * IP / ASN intelligence with reverse DNS enrichment.
 */
class IpIntelligenceService
{
    public function __construct(
        private IpIntelligenceProviderInterface $provider,
        private DnsProviderInterface $dns,
    ) {}

    /**
     * @param  array<int, string>  $ips
     * @return array<int, array<string, mixed>>
     */
    public function enrich(array $ips): array
    {
        $max = (int) config('domain_intelligence.limits.max_ips', 50);
        $results = [];

        foreach (array_slice(array_unique($ips), 0, $max) as $ip) {
            $results[] = $this->lookup($ip);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $ip): array
    {
        $ttl = (int) config('domain_intelligence.cache_ttl.ip', 3600);
        $data = Cache::remember('di.ip.'.strtolower($ip), $ttl, fn () => $this->provider->lookup($ip));

        $entry = is_array($data) ? $data : [
            'ip' => $ip,
            'version' => str_contains($ip, ':') ? 6 : 4,
            'asn' => null,
            'asn_org' => null,
            'isp' => null,
            'hosting' => null,
            'country' => null,
            'region' => null,
            'city' => null,
            'network' => null,
            'rir' => null,
            'source' => null,
        ];

        $entry['ip'] = $ip;
        $entry['reverse_dns'] = $this->dns->reverseDns($ip);
        $entry['confidence'] = $data !== null ? 'HIGH' : 'UNKNOWN';
        $entry['provider_available'] = $data !== null;

        return $entry;
    }
}