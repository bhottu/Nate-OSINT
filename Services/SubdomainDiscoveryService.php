<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class SubdomainDiscoveryService
{
    private array $discoveryCache = [];
    
    public function __construct()
    {
        Cache::remember('subdomains_discovery', 300, function () {
            return $this->discoverSubdomains();
        });
    }

    public function discoverSubdomains(): Collection
    {
        $subdomains = [];
        
        // Source: Certificate Transparency
        foreach (Cache::get('ct_subdomains') ?? [] as $hostname) {
            if (!empty($hostname)) {
                $subdomains[] = ['hostname' => $hostname, 'source' => 'Certificate Transparency'];
            }
        }

        // Source: Public DNS datasets
        foreach (Cache::get('dns_subdomains') ?? [] as $hostname) {
            if (!empty($hostname)) {
                $subdomains[] = ['hostname' => $hostname, 'source' => 'Public DNS'];
            }
        }

        // Sort and deduplicate
        return collect($subdomains)->sort()->unique()->values();
    }

    public function analyzeSubdomains(Collection $subdomains): void
    {
        foreach ($subdomains as $domain) {
            // Initialize new domain scan if not done yet
            if (!DB::table('domains')->where('domain', $domain->hostname)->exists()) {
                DB::table('domains')->insert([
                    'domain' => $domain->hostname,
                    'security_score' => 0,
                    'security_status' => 'NEEDS_SCAN',
                ]);
            }

            // Add DNS records if discovered
            if (Cache::get($domain->hostname . '_dns_records') === null) {
                DB::table('domain_dns_records')->insert([
                    'hostname' => $domain->hostname,
                    'type' => '',
                    'value' => '',
                    'observed_at' => now(),
                    'ttl' => 0,
                    'source' => '',
                ]);
            }

            // Add metadata if discovered
            DB::table('domains')->update([
                'updated_at' => now()
            ]);
        }
    }
}
