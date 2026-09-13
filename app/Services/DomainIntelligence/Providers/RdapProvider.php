<?php

namespace App\Services\DomainIntelligence\Providers;

use App\Services\DomainIntelligence\Contracts\RdapProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * RDAP provider using the public rdap.org bootstrap redirect service.
 * No API key required; responses are cached to stay well behaved.
 */
class RdapProvider implements RdapProviderInterface
{
    public function domain(string $domain): ?array
    {
        $ttl = (int) config('domain_intelligence.cache_ttl.rdap', 3600);
        $key = 'di.rdap.domain.'.strtolower($domain);

        return Cache::remember($key, $ttl, function () use ($domain) {
            return $this->fetch(config('domain_intelligence.providers.rdap.base_url', 'https://rdap.org').'/domain/'.$domain);
        });
    }

    public function ip(string $ip): ?array
    {
        $ttl = (int) config('domain_intelligence.cache_ttl.ip', 3600);
        $key = 'di.rdap.ip.'.strtolower($ip);

        return Cache::remember($key, $ttl, function () use ($ip) {
            return $this->fetch(config('domain_intelligence.providers.rdap.base_url', 'https://rdap.org').'/ip/'.$ip);
        });
    }

    private function fetch(string $url): ?array
    {
        try {
            $response = Http::timeout((int) config('domain_intelligence.timeouts.request', 12))
                ->connectTimeout((int) config('domain_intelligence.timeouts.connect', 5))
                ->withHeaders(['Accept' => 'application/rdap+json'])
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();
        if (! is_array($body)) {
            return null;
        }

        return $body;
    }
}