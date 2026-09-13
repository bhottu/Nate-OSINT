<?php

namespace App\Services\DomainIntelligence;

use App\Services\DomainIntelligence\Contracts\DnsProviderInterface;
use Illuminate\Support\Collection;

/**
 * Passive subdomain discovery.
 *
 * Primary source: Certificate Transparency logs. Optional enrichment resolves
 * DNS for each discovered hostname. No brute forcing is performed.
 */
class SubdomainDiscoveryService
{
    public function __construct(
        private CertificateTransparencyService $ct,
        private DnsProviderInterface $dns,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function discover(string $domain, bool $resolveDns = true): Collection
    {
        $max = (int) config('domain_intelligence.limits.max_subdomains', 200);
        $hostnames = collect($this->ct->discoveredHostnames($domain))
            ->unique()
            ->sort()
            ->take($max)
            ->values();

        return $hostnames->map(function (string $hostname) use ($resolveDns) {
            $entry = [
                'hostname' => $hostname,
                'source' => 'CERTIFICATE TRANSPARENCY',
                'confidence' => 'HIGH',
                'dns_status' => 'UNKNOWN',
                'a' => [],
                'aaaa' => [],
                'cname' => [],
                'ip' => null,
            ];

            if ($resolveDns) {
                $records = $this->dns->resolve($hostname, ['A', 'AAAA', 'CNAME']);
                $entry['a'] = collect($records['A'] ?? [])->pluck('value')->values()->all();
                $entry['aaaa'] = collect($records['AAAA'] ?? [])->pluck('value')->values()->all();
                $entry['cname'] = collect($records['CNAME'] ?? [])->pluck('value')->values()->all();
                $entry['ip'] = $entry['a'][0] ?? null;
                $entry['dns_status'] = ($entry['a'] !== [] || $entry['aaaa'] !== [] || $entry['cname'] !== [])
                    ? 'RESOLVED'
                    : 'UNRESOLVED';
            }

            return $entry;
        });
    }
}